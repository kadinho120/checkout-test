<?php
// api/process-card.php
// Endpoint para processamento de pagamentos com Cartão de Crédito Appmax

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/functions/handle_appmax_card_payment.php';
require_once __DIR__ . '/functions/handle_infinitepay_payment.php';

header('Content-Type: application/json');

$json_input = file_get_contents('php://input');
$params = json_decode($json_input, true);

$gateway = 'appmax';
if (isset($params['gateway']) && !empty($params['gateway'])) {
    $gateway = strtolower(trim($params['gateway']));
} elseif (isset($params['products'][0]['id']) && !empty($params['products'][0]['id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT payment_gateway FROM products WHERE id = ?");
        $stmt->execute([(int)$params['products'][0]['id']]);
        $prodGateway = $stmt->fetchColumn();
        if ($prodGateway) {
            $gateway = strtolower(trim($prodGateway));
        }
    } catch (Exception $e) {
        $gateway = 'appmax';
    }
} elseif (isset($params['products'][0]['sku']) && !empty($params['products'][0]['sku'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT payment_gateway FROM products WHERE slug = ?");
        $stmt->execute([$params['products'][0]['sku']]);
        $prodGateway = $stmt->fetchColumn();
        if ($prodGateway) {
            $gateway = strtolower(trim($prodGateway));
        }
    } catch (Exception $e) {
        $gateway = 'appmax';
    }
}

if ($gateway === 'infinitepay' || $gateway === 'infinitepay_card' || str_starts_with($gateway, 'infinitepay_card_')) {
    handle_infinitepay_payment('credit_card');
} else {
    handle_appmax_card_payment();
}

