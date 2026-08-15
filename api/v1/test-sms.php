<?php
// api/v1/test-sms.php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/send_sms_disparopro.php';
require_once __DIR__ . '/../functions/replace_shortcodes.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$smsToken = $data['sms_token'] ?? '';
$phone = $data['test_phone'] ?? '';
$message = $data['sms_message'] ?? 'Teste de entrega via SMS DisparoPro.';

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Informe o telefone de teste com DDD.']);
    exit;
}

if (empty(trim($message))) {
    echo json_encode(['success' => false, 'error' => 'Configure o texto da mensagem de SMS antes de testar.']);
    exit;
}

// Prepare sample dummy data for shortcode test
$dummyCustomer = [
    'name' => 'Cliente Teste',
    'email' => 'cliente@exemplo.com',
    'phone' => $phone,
    'product_name' => $data['name'] ?? 'Produto de Teste'
];

// Replace shortcodes in message
$finalMessage = replaceShortcodes($message, $dummyCustomer, 'PIX_TESTE_COPIA_E_COLA_123');

$result = sendSmsDisparoPro(
    $smsToken,
    $phone,
    $finalMessage,
    'test-' . substr(md5(uniqid()), 0, 6)
);

echo json_encode($result);
