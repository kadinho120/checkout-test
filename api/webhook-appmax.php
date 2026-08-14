<?php
/**
 * api/webhook-appmax.php
 * Receptor de Webhook vindo da Appmax para confirmação de pagamento.
 * Atualiza o status do pedido no banco de dados SQLite e dispara entregáveis e rastreamentos.
 */

// --- CONFIGURAÇÕES ---
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions/log_activity.php';

define('FINAL_ORDER_STATUS', 'paid');
define('ENABLE_LOGGING', true);

function log_appmax_webhook($message)
{
    if (ENABLE_LOGGING) {
        log_activity($message, 'webhook_appmax.log', __DIR__);
    }
}

header('Content-Type: application/json');

$payload_json = file_get_contents('php://input');
if (empty($payload_json)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload vazio.']);
    exit;
}

$payload = json_decode($payload_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido.']);
    exit;
}

log_appmax_webhook("INFO: Webhook recebido: " . $payload_json);

// Detecção do evento
$event = $payload['event'] ?? ($payload['event_type'] ?? '');
$event_type = $payload['event_type'] ?? '';

// Eventos que confirmam pagamento
$paid_events = [
    'order_paid_by_pix',
    'order_approved',
    'order_paid',
    'order_up_sold',
    'payment_approved'
];

// Identificação do correlation_id e/ou order_id da Appmax
$client_key = $payload['client_key'] ?? ($payload['external_key'] ?? ($payload['data']['client_key'] ?? ($payload['data']['external_key'] ?? null)));
$appmax_order_id = $payload['data']['order_id'] ?? ($payload['data']['order']['id'] ?? ($payload['data']['id'] ?? ($payload['order_id'] ?? null)));

if (!$client_key && !$appmax_order_id) {
    // Retorna 200 para testes de validação de URL pela Appmax
    log_appmax_webhook("INFO: Webhook recebido sem client_key nem order_id (validação de URL).");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Webhook ativo. Aguardando eventos de pagamento.']);
    exit;
}

// Verifica se é um evento de pagamento
$is_paid_event = in_array($event, $paid_events) || (isset($payload['data']['status']) && in_array(strtolower($payload['data']['status']), ['aprovado', 'pago', 'paid', 'approved']));

if (!$is_paid_event) {
    log_appmax_webhook("INFO: Evento ignorado ou não conclusivo para pagamento: {$event}");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => "Evento '{$event}' recebido sem alteração de status."]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Busca o pedido por correlationID (transaction_id) ou por external_id (ID da Appmax)
    $order = null;

    if ($client_key) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ?");
        $stmt->execute([$client_key]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order && $appmax_order_id) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE external_id = ? OR transaction_id = ?");
        $stmt->execute([(string)$appmax_order_id, (string)$appmax_order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($order) {
        if ($order['status'] !== FINAL_ORDER_STATUS) {
            // Atualiza status no banco de dados SQLite
            $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = datetime('now', '-03:00') WHERE id = ?");
            $updateStmt->execute([FINAL_ORDER_STATUS, $order['id']]);
            $order['status'] = FINAL_ORDER_STATUS;

            log_appmax_webhook("INFO: Pedido ID {$order['id']} atualizado para " . FINAL_ORDER_STATUS);

            $orderJsonData = json_decode($order['json_data'] ?? '{}', true);
            $productsList = $orderJsonData['products'] ?? [];
            $trackingData = $orderJsonData['tracking'] ?? [];
            $externalID = $order['external_id'] ?? '';

            // 1. Disparo de Webhooks Customizados
            require_once __DIR__ . '/functions/trigger_custom_webhooks.php';
            trigger_custom_webhooks('order.paid', $order['id']);

            // 2. Processamento de Entregáveis (E-mail e WhatsApp)
            require_once __DIR__ . '/functions/process_order_deliverables.php';
            require_once __DIR__ . '/functions/replace_shortcodes.php';
            require_once __DIR__ . '/functions/send_evolution_message.php';
            require_once __DIR__ . '/functions/send_order_email.php';
            require_once __DIR__ . '/functions/send_utmify_event.php';

            $customerDataForHelper = [
                'name' => $order['customer_name'] ?? '',
                'email' => $order['customer_email'] ?? '',
                'phone' => $order['customer_phone'] ?? '',
                'document' => $order['customer_cpf'] ?? ''
            ];

            // 3. Disparo para UTMIFY
            sendUtmifyEvent($order, 'paid');

            // 4. Envio de entregáveis automáticos
            log_appmax_webhook("INFO: Iniciando entrega de produtos para Pedido ID {$order['id']}");
            $deliveryResult = processOrderDeliverables($productsList, $customerDataForHelper, $db);
            log_appmax_webhook("INFO: Resultado entrega: " . json_encode($deliveryResult));

            // 5. Meta Conversions API (CAPI)
            log_appmax_webhook("INFO: Iniciando trackMetaPurchase para Pedido #" . $order['id']);
            require_once __DIR__ . '/functions/track_meta_purchase.php';
            $metaResult = trackMetaPurchase($order['id'], $db);
            log_appmax_webhook("INFO: Resultado trackMetaPurchase: " . json_encode($metaResult));

            log_appmax_webhook("SUCCESS: Pedido ID {$order['id']} (Appmax) processado com sucesso.");
            echo json_encode(['status' => 'ok', 'message' => 'Status atualizado e fluxos disparados.']);
        } else {
            log_appmax_webhook("INFO: Pedido ID {$order['id']} já estava com status pago.");
            echo json_encode(['status' => 'ok', 'message' => 'Já atualizado.']);
        }
    } else {
        log_appmax_webhook("WARNING: Pedido não encontrado para ClientKey: '{$client_key}' / AppmaxOrderID: '{$appmax_order_id}'");
        http_response_code(404);
        echo json_encode(['status' => 'not_found', 'message' => 'Pedido não encontrado.']);
    }

} catch (Exception $e) {
    log_appmax_webhook("ERROR FATAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno BD: ' . $e->getMessage()]);
}
