<?php
/**
 * api/check-order-status.php
 * Endpoint para verificar o status de um pedido (pending ou paid).
 * Recebe um correlationId via POST JSON ou GET e retorna o status.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/connection.php';

$correlationId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $correlationId = $data['correlationId'] ?? ($data['correlation_id'] ?? null);
} else {
    $correlationId = $_GET['correlationId'] ?? ($_GET['correlation_id'] ?? null);
}

if (empty($correlationId)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'correlationId ausente.']));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, status, total_amount, payment_method, gateway FROM orders WHERE transaction_id = ? OR external_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$correlationId, $correlationId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        echo json_encode([
            'success' => true,
            'status' => $order['status'],
            'order_id' => $order['id'],
            'gateway' => $order['gateway'] ?? 'woovi'
        ]);
        exit;
    }

    // Fallback para arquivo legada se existir
    $orders_file_path = __DIR__ . '/database/detailed_orders.json';
    if (file_exists($orders_file_path)) {
        $orders = json_decode(file_get_contents($orders_file_path), true);
        if (is_array($orders)) {
            foreach ($orders as $o) {
                if (isset($o['correlationId']) && $o['correlationId'] === $correlationId) {
                    echo json_encode(['success' => true, 'status' => $o['status']]);
                    exit;
                }
            }
        }
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido com o correlationId fornecido não foi encontrado.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao consultar status: ' . $e->getMessage()]);
}
