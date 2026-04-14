<?php
/**
 * Endpoint para testar o envio de e-mail de um produto
 */
require_once __DIR__ . '/../../admin/auth.php'; // Proteção básica
require_once __DIR__ . '/../email-helper.php';
require_once __DIR__ . '/../functions/replace_shortcodes.php';

// Recebe os dados
$data = json_decode(file_get_contents('php://input'), true);

$to = $data['test_email'] ?? '';
$subject = $data['deliverable_email_subject'] ?? '';
$body = $data['deliverable_email_body'] ?? '';

if (empty($to) || empty($subject) || empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Preencha todos os campos do e-mail.']);
    exit;
}

// Mock de dados para o teste de shortcodes
$customerData = [
    'primeiro_nome' => 'João',
    'nome_completo' => 'João Teste',
    'email' => $to,
    'telefone' => '5511999999999',
    'pix_copia_cola' => '00020101021226830014br.gov.bcb.pix013661eb9c08-8e6b-4e63-bd63-0979402636f25204000053039865802BR5915Joao Teste6009Sao Paulo62070503***6304E2D8',
    'product_name' => $data['name'] ?? 'Produto de Teste'
];

// Substitui shortcodes
$finalSubject = replaceShortcodes($subject, $customerData, '');
$finalBody = replaceShortcodes($body, $customerData, '');

// Envia o e-mail
$result = sendOrderEmail($to, $finalSubject, $finalBody);

echo json_encode($result);
