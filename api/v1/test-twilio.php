<?php
// api/v1/test-twilio.php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions/send_twilio_message.php';
require_once __DIR__ . '/../functions/replace_shortcodes.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$accountSid = $data['twilio_account_sid'] ?? '';
$authToken = $data['twilio_auth_token'] ?? '';
$from = $data['twilio_from'] ?? '';
$phone = $data['test_phone'] ?? '';
$contentSid = $data['twilio_content_sid'] ?? '';
$contentVariables = $data['twilio_content_variables'] ?? '';
$body = $data['twilio_message'] ?? 'Teste de mensagem via Twilio Admin.';
$mediaUrl = $data['twilio_media_url'] ?? null;

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Informe o telefone de teste com DDD.']);
    exit;
}

// Prepare sample dummy data for shortcode test
$dummyCustomer = [
    'name' => 'Cliente Teste',
    'email' => 'cliente@exemplo.com',
    'phone' => $phone,
    'product_name' => $data['name'] ?? 'Produto de Teste'
];

// Replace shortcodes in message/body and content variables if any
if (!empty($body)) {
    $body = replaceShortcodes($body, $dummyCustomer, 'PIX_TESTE_COPIA_E_COLA_123');
}

if (!empty($contentVariables)) {
    if (is_string($contentVariables)) {
        $contentVariables = replaceShortcodes($contentVariables, $dummyCustomer, 'PIX_TESTE_COPIA_E_COLA_123');
        // Validate if json
        $decoded = json_decode($contentVariables, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $contentVariables = $decoded;
        }
    }
}

$result = sendTwilioMessage(
    $accountSid,
    $authToken,
    $from,
    $phone,
    $contentSid,
    $contentVariables,
    $body,
    $mediaUrl
);

echo json_encode($result);
