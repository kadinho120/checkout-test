<?php
// api/process-pix.php
// Router central para pagamentos PIX (Woovi vs Appmax)

// 1. Carrega as configurações e banco
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connection.php';

// 2. Carrega as funções isoladas
require_once __DIR__ . '/functions/make_http_request.php';
require_once __DIR__ . '/functions/generate_valid_cpf.php';
require_once __DIR__ . '/functions/generate_random_phone.php';
require_once __DIR__ . '/functions/send_utmify_event.php';
require_once __DIR__ . '/functions/handle_woovi_pix_payment.php';
require_once __DIR__ . '/functions/handle_appmax_pix_payment.php';
require_once __DIR__ . '/functions/handle_manual_pix_payment.php';

header('Content-Type: application/json');

// 3. Determina qual gateway utilizar
$json_input = file_get_contents('php://input');
$params = json_decode($json_input, true);

$gateway = 'woovi';

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
        // Fallback default
        $gateway = 'woovi';
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
        // Fallback default
        $gateway = 'woovi';
    }
}

// 4. Executa a função do gateway apropriado
if ($gateway === 'appmax') {
    handle_appmax_pix_payment();
} elseif ($gateway === 'manual_pix' || $gateway === 'pix_manual' || $gateway === 'direct_pix') {
    handle_manual_pix_payment();
} else {
    handle_woovi_pix_payment();
}
