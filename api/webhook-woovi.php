<?php
/**
 * Receptor de Webhook vindo do n8n/Woovi para confirmação de pagamento.
 * Atualiza o status do pedido no banco de dados SQLite.
 */

// --- CONFIGURAÇÕES ---
require_once __DIR__ . '/connection.php';
define('FINAL_ORDER_STATUS', 'paid');
define('LOG_FILE_PATH', __DIR__ . '/webhook_n8n_woovi.log');
define('ENABLE_LOGGING', true);

// --- LOGGING ISOLADO ---
require_once __DIR__ . '/functions/log_activity.php';

function log_message($message)
{
    if (ENABLE_LOGGING) {
        log_activity($message, 'webhook_n8n_woovi.log', __DIR__);
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

// Detecção robusta do correlationID (pode vir no root, dentro de 'charge' ou dentro de 'pix.charge')
$correlation_id = $payload['correlationID'] ?? $payload['correlationId'] ?? null;
if (!$correlation_id && isset($payload['charge']['correlationID'])) {
    $correlation_id = $payload['charge']['correlationID'];
}
if (!$correlation_id && isset($payload['pix']['charge']['correlationID'])) {
    $correlation_id = $payload['pix']['charge']['correlationID'];
}

if (!$correlation_id) {
    // Retornamos 200 aqui para permitir que a Woovi valide a URL no painel administrativo
    log_message("INFO: Webhook recebido sem correlationID (provavelmente um teste de conexão do gateway).");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => "Webhook ativo. Aguardando eventos reais."]);
    exit;
}

log_message("INFO: Recebida confirmação para: " . $correlation_id);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check current status
    $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ?");
    $stmt->execute([$correlation_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        if ($order['status'] !== FINAL_ORDER_STATUS) {
            // Prepara e executa o update (Corrigindo o bug anterior)
            $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = datetime('now', '-03:00') WHERE id = ?");
            $updateStmt->execute([FINAL_ORDER_STATUS, $order['id']]);

            // 1. Disparo de Webhooks Customizados (Integrações externas)
            require_once __DIR__ . '/functions/trigger_custom_webhooks.php';
            trigger_custom_webhooks('order.paid', $order['id']);

            // 2. Processamento de Entregáveis (E-mail e WhatsApp para o cliente)
            require_once __DIR__ . '/functions/process_order_deliverables.php';
            require_once __DIR__ . '/functions/replace_shortcodes.php';
            require_once __DIR__ . '/functions/send_evolution_message.php';
            require_once __DIR__ . '/functions/send_order_email.php';
            require_once __DIR__ . '/functions/send_utmify_event.php';

            $orderJsonData = json_decode($order['json_data'] ?? '{}', true);
            $productsList = $orderJsonData['products'] ?? [];
            $customerData = [
                'name' => $order['customer_name'] ?? '',
                'email' => $order['customer_email'] ?? '',
                'phone' => $order['customer_phone'] ?? '',
                'document' => $order['customer_cpf'] ?? ''
            ];

            // Dispara para UTMIFY se configurado
            sendUtmifyEvent($order, 'paid');

            // Envia os produtos
            log_message("INFO: Iniciando entrega de produtos para Pedido ID {$order['id']}");
            $deliveryResult = processOrderDeliverables($productsList, $customerData, $db);
            log_message("INFO: Resultado entrega: " . json_encode($deliveryResult));

            log_message("SUCCESS: Pedido ID {$order['id']} atualizado para " . FINAL_ORDER_STATUS);
            echo json_encode(['status' => 'ok', 'message' => 'Status atualizado e produtos entregues.']);
        } else {
            log_message("INFO: Pedido já estava pago.");
            echo json_encode(['status' => 'ok', 'message' => 'Já atualizado.']);
        }
    } else {
        log_message("WARNING: Pedido não encontrado para CorrelationID: $correlation_id");
        http_response_code(404);
        echo json_encode(['status' => 'not_found', 'message' => 'Pedido não encontrado.']);
    }

} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno BD: ' . $e->getMessage()]);
}
?>