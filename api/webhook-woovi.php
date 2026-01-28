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

function log_message($message)
{
    if (ENABLE_LOGGING) {
        file_put_contents(LOG_FILE_PATH, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
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
if (json_last_error() !== JSON_ERROR_NONE || !isset($payload['correlationId'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "JSON inválido ou 'correlationId' ausente."]);
    exit;
}

$correlation_id = $payload['correlationId'];
log_message("INFO: Recebida confirmação para: " . $correlation_id);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check current status
    $stmt = $db->prepare("SELECT id, status FROM orders WHERE transaction_id = ?");
    $stmt->execute([$correlation_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        if ($order['status'] !== FINAL_ORDER_STATUS) {
            $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = datetime('now') WHERE id = ?");
            $updateStmt->execute([FINAL_ORDER_STATUS, $order['id']]);
            log_message("SUCCESS: Pedido ID {$order['id']} atualizado para " . FINAL_ORDER_STATUS);
            echo json_encode(['status' => 'ok', 'message' => 'Status atualizado.']);
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
    echo json_encode(['status' => 'error', 'message' => 'Erro interno BD.']);
}
?>