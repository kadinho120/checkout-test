<?php
// /taboola-s2s-tracking/taboola_webhook_endpoint.php

/**
 * Endpoint para buscar dados de correlação do Taboola e enviá-los para um webhook do n8n.
 */

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['correlationId'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Requisição inválida. Esperado um JSON com "correlationId".']));
}
$correlationId = $data['correlationId'];

$correlation_file_path = __DIR__ . '/taboola_correlation_data.json';

if (!file_exists($correlation_file_path)) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Arquivo de dados de correlação Taboola não encontrado.']));
}

$correlation_data = json_decode(file_get_contents($correlation_file_path), true);
$found_data = null;

if (is_array($correlation_data) && isset($correlation_data[$correlationId])) {
    $found_data = $correlation_data[$correlationId];
}

if ($found_data) {
    
    // Adiciona o correlationId ao payload que será enviado para o n8n
    $found_data['correlation_id'] = $correlationId;

    // Nota: Ao contrário do Facebook (que precisa extrair fbc), o Taboola usa o click_id direto.
    // O campo 'click_id' já foi salvo no passo anterior, então ele será enviado automaticamente.

    // URL do Webhook do n8n para Taboola
    // ATENÇÃO: Certifique-se de criar este webhook no n8n com o método POST
    $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/taboola-s2s-tracking-ebook';

    $ch = curl_init($n8n_webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($found_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($found_data))
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode(['success' => true, 'message' => 'Dados de rastreamento Taboola encontrados e encaminhados com sucesso para o n8n.']);
    } else {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => 'Erro ao encaminhar os dados Taboola para o n8n.', 'n8n_response_code' => $http_code]);
    }

} else {
    // Se não encontrou dados, pode ser que o usuário não veio pelo Taboola (não tinha cookie tblci)
    // Retorna 404 mas isso é esperado em vendas orgânicas ou de outras fontes
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Dados de rastreamento Taboola para este correlationId não foram encontrados.']);
}
?>