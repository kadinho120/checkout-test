<?php
// api/v1/test-evolution.php
header('Content-Type: application/json');

// Allow CORS if needed, or rely on same-origin for admin
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../api/evolution-helper.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Extract fields
$instance = $data['evolution_instance'] ?? '';
$token = $data['evolution_token'] ?? '';
$url = $data['evolution_url'] ?? '';
$phone = $data['test_phone'] ?? '';
$type = $data['deliverable_type'] ?? 'text';
$message = $data['deliverable_text'] ?? 'Teste de mensagem via Admin.';
$file = $data['deliverable_file'] ?? null;

if (empty($instance) || empty($token) || empty($url) || empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Preencha URL, Instância, Token e Telefone de Teste.']);
    exit;
}

// Send
$result = sendEvolutionMessage($instance, $token, $url, $phone, $type, $message, $file);

echo json_encode($result);
