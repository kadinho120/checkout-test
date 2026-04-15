<?php
/**
 * API para reenviar evento do Meta Ads (CAPI) manualmente
 * Local: api/v1/meta-retry.php
 */

session_start();

// Verifica autenticação
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['error' => 'Não autorizado']));
}

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../functions/track_meta_purchase.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['order_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Order ID obrigatório']));
}

try {
    $result = trackMetaPurchase($data['order_id']);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
