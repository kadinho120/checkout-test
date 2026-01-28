<?php
/**
 * Endpoint para buscar dados de correlação do Google Ads e enviá-los para o n8n.
 * Caminho: /google-s2s-tracking/google_webhook_endpoint.php
 */

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['correlationId'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Requisição inválida. Esperado um JSON com "correlationId".']));
}
$correlationId = $data['correlationId'];

// Caminho para o JSON do Google
$correlation_file_path = __DIR__ . '/google_correlation_data.json';

if (!file_exists($correlation_file_path)) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Arquivo de dados de correlação do Google não encontrado.']));
}

$correlation_data = json_decode(file_get_contents($correlation_file_path), true);
$found_data = null;

if (is_array($correlation_data) && isset($correlation_data[$correlationId])) {
    $found_data = $correlation_data[$correlationId];
}

if ($found_data) {
    
    // Adiciona o correlationId ao payload que será enviado para o n8n
    $found_data['correlation_id'] = $correlationId;

    // IMPORTANTE: Substitua pela URL do seu webhook no n8n que fará o disparo do Google Ads
    // Estou colocando uma URL placeholder, altere para a sua real do n8n
    $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/google-tracking-receba';

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
        echo json_encode(['success' => true, 'message' => 'Dados Google Ads encontrados e encaminhados para o n8n.']);
    } else {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => 'Erro ao encaminhar para o n8n.', 'n8n_response_code' => $http_code]);
    }

} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'CorrelationId não encontrado na base do Google.']);
}
?>