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
$event_clean = strtolower(str_replace(['_', '-'], '', (string)$event));
$status_clean = strtolower(trim((string)($payload['data']['status'] ?? '')));

// Eventos que confirmam pagamento
$paid_events_clean = [
    'orderpaid',
    'orderpaidbypix',
    'orderapproved',
    'orderauthorized',
    'orderintegrated',
    'orderupsold',
    'paymentapproved',
    'paymentauthorized',
    'orderauthorizedwithdelay',
    'paymentauthorizedwithdelay'
];

$paid_statuses = ['aprovado', 'pago', 'paid', 'approved', 'autorizado', 'authorized'];

// Identificação do correlation_id e/ou order_id da Appmax
$client_key = $payload['client_key'] ?? ($payload['external_key'] ?? ($payload['data']['client_key'] ?? ($payload['data']['external_key'] ?? null)));
$appmax_order_id = $payload['data']['order_id'] ?? ($payload['data']['order']['id'] ?? ($payload['data']['id'] ?? ($payload['order_id'] ?? ($payload['id'] ?? null))));
$pix_emv = $payload['data']['pix_emv'] ?? null;

if (!$client_key && !$appmax_order_id && empty($pix_emv)) {
    // Retorna 200 para testes de validação de URL pela Appmax
    log_appmax_webhook("INFO: Webhook recebido sem client_key nem order_id (validação de URL).");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Webhook ativo. Aguardando eventos de pagamento.']);
    exit;
}

// Verifica se é um evento de pagamento
$is_paid_event = in_array($event_clean, $paid_events_clean) 
    || str_contains($event_clean, 'paid') 
    || str_contains($event_clean, 'approved') 
    || in_array($status_clean, $paid_statuses);

if (!$is_paid_event) {
    log_appmax_webhook("INFO: Evento ignorado ou não conclusivo para pagamento: {$event} (status: {$status_clean})");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => "Evento '{$event}' recebido sem alteração de status."]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Busca o pedido com múltiplos níveis de fallback resiliente
    $order = null;

    // 1. Busca direta por client_key (transaction_id ou external_id)
    if (!empty($client_key)) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ? OR external_id = ? LIMIT 1");
        $stmt->execute([$client_key, $client_key]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Busca por ID da Appmax nas colunas external_id e transaction_id
    if (!$order && !empty($appmax_order_id)) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE external_id = ? OR transaction_id = ? LIMIT 1");
        $stmt->execute([(string)$appmax_order_id, (string)$appmax_order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Busca por appmax_order_id dentro de json_data (caso external_id estivesse vazio no cadastro)
    if (!$order && !empty($appmax_order_id)) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE json_data LIKE ? OR json_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([
            '%"appmax_order_id":' . $appmax_order_id . '%',
            '%"appmax_order_id":"' . $appmax_order_id . '"%'
        ]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 4. Busca por pix_emv (código Pix copia-e-cola gravado dentro de pix_data no json_data)
    if (!$order && !empty($pix_emv)) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE json_data LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['%' . $pix_emv . '%']);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 5. Busca por sufixo de correlação no nome do cliente (ex: "Cliente #FBS1")
    $customer_fullname = $payload['data']['customer']['fullname'] ?? ($payload['data']['customer']['lastname'] ?? '');
    if (!$order && preg_match('/#([A-Za-z0-9_-]+)/', $customer_fullname, $matches)) {
        $corr_suffix = $matches[1];
        $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id LIKE ? AND gateway = 'appmax' ORDER BY id DESC LIMIT 1");
        $stmt->execute(['%' . $corr_suffix]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 6. Busca por CPF e/ou E-mail do cliente (pedidos recentes não pagos da Appmax)
    $customer_cpf = preg_replace('/[^0-9]/', '', $payload['data']['customer']['document_number'] ?? '');
    $customer_email = trim($payload['data']['customer']['email'] ?? '');
    if (!$order && (!empty($customer_cpf) || !empty($customer_email))) {
        if (!empty($customer_cpf) && !empty($customer_email)) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE (customer_cpf = ? OR customer_email = ?) AND gateway = 'appmax' AND status != 'paid' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$customer_cpf, $customer_email]);
        } elseif (!empty($customer_cpf)) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE customer_cpf = ? AND gateway = 'appmax' AND status != 'paid' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$customer_cpf]);
        } else {
            $stmt = $db->prepare("SELECT * FROM orders WHERE customer_email = ? AND gateway = 'appmax' AND status != 'paid' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$customer_email]);
        }
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($order) {
        // Auto-recuperação: Sincroniza external_id no banco com o ID da Appmax caso esteja vazio
        if (empty($order['external_id']) && !empty($appmax_order_id)) {
            $syncStmt = $db->prepare("UPDATE orders SET external_id = ? WHERE id = ?");
            $syncStmt->execute([(string)$appmax_order_id, $order['id']]);
            $order['external_id'] = (string)$appmax_order_id;
            log_appmax_webhook("INFO: external_id sincronizado para '{$appmax_order_id}' no Pedido ID {$order['id']}");
        }

        // Se o webhook trouxe e-mail ou telefone real e no pedido estava placeholder ou vazio, atualiza
        $webhook_email = trim($payload['data']['customer']['email'] ?? '');
        $webhook_phone = preg_replace('/[^0-9]/', '', $payload['data']['customer']['telephone'] ?? '');
        $updateFields = [];
        $updateValues = [];

        if (!empty($webhook_email) && (empty($order['customer_email']) || str_contains($order['customer_email'], '@naoinformado.com'))) {
            $updateFields[] = "customer_email = ?";
            $updateValues[] = $webhook_email;
            $order['customer_email'] = $webhook_email;
        }
        if (!empty($webhook_phone) && empty($order['customer_phone'])) {
            $updateFields[] = "customer_phone = ?";
            $updateValues[] = $webhook_phone;
            $order['customer_phone'] = $webhook_phone;
        }
        if (!empty($updateFields)) {
            $updateValues[] = $order['id'];
            $db->prepare("UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($updateValues);
        }

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

            // 3. Disparo para UTMIFY estruturado
            $utmifyOrderData = [
                'correlation_id' => $order['transaction_id'] ?? $order['id'],
                'value' => (int) round(($order['total_amount'] ?? 0) * 100),
                'customer' => $customerDataForHelper,
                'products' => $productsList,
                'tracking' => $trackingData,
                'status' => 'paid'
            ];
            sendUtmifyEvent($utmifyOrderData, 'paid');

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
            exit;
        } else {
            log_appmax_webhook("INFO: Pedido ID {$order['id']} já estava com status pago.");
            echo json_encode(['status' => 'ok', 'message' => 'Já atualizado.']);
            exit;
        }
    } else {
        log_appmax_webhook("WARNING: Pedido não encontrado para ClientKey: '{$client_key}' / AppmaxOrderID: '{$appmax_order_id}'");
        http_response_code(404);
        echo json_encode(['status' => 'not_found', 'message' => 'Pedido não encontrado.']);
        exit;
    }

} catch (Exception $e) {
    log_appmax_webhook("ERROR FATAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno BD: ' . $e->getMessage()]);
    exit;
}
