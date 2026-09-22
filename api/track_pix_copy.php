<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/functions/record_pix_copy.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$correlationId = $data['correlation_id'] ?? $data['correlationId'] ?? $_REQUEST['correlation_id'] ?? $_REQUEST['correlationId'] ?? null;
$orderId = $data['order_id'] ?? $data['orderId'] ?? $_REQUEST['order_id'] ?? $_REQUEST['orderId'] ?? null;

if (empty($correlationId) && empty($orderId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'correlation_id ou order_id é obrigatório.'
    ]);
    exit;
}

$result = record_pix_copy($correlationId, $orderId);

if (!$result['success']) {
    http_response_code(400);
} else {
    http_response_code(200);
}

echo json_encode($result);
