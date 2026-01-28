<?php
/**
 * Endpoint para verificar o status de um pedido (PENDING ou PAID).
 * Recebe um correlationId via POST JSON e retorna o status.
 * @version 1.0
 */

header('Content-Type: application/json');

// NOTA DE SEGURANÇA: Este endpoint não possui autenticação, conforme solicitado.

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['correlationId'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['success' => false, 'message' => 'correlationId ausente ou JSON inválido.']));
}

$correlationId = $data['correlationId'];
$orders_file_path = __DIR__ . '/database/detailed_orders.json';

if (!file_exists($orders_file_path)) {
    http_response_code(404); // Not Found
    die(json_encode(['success' => false, 'message' => 'Arquivo de pedidos não encontrado.']));
}

$orders = json_decode(file_get_contents($orders_file_path), true);
$order_status = null;

if (is_array($orders)) {
    // Procura o pedido pelo correlationId
    foreach ($orders as $order) {
        if (isset($order['correlationId']) && $order['correlationId'] === $correlationId) {
            $order_status = $order['status'];
            break;
        }
    }
}

if ($order_status) {
    // Se encontrou o pedido, retorna o status
    echo json_encode(['success' => true, 'status' => $order_status]);
} else {
    // Se não encontrou, retorna erro
    http_response_code(404); // Not Found
    echo json_encode(['success' => false, 'message' => 'Pedido com o correlationId fornecido não foi encontrado.']);
}

?>
