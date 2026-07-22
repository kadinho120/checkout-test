<?php
require_once __DIR__ . '/log_activity.php';

/**
 * Cria uma nova chave Pix na Woovi (padrão: EVP / Chave Aleatória).
 *
 * @param string $type Tipo da chave Pix ('EVP' ou 'CNPJ')
 * @return array Array estruturado com ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function woovi_create_pix_key($type = 'EVP')
{
    $api_key = getenv('WOOVI_APP_ID') ?: (defined('WOOVI_APP_ID') ? WOOVI_APP_ID : '');

    if (empty($api_key)) {
        $msg = 'Configuração ausente: WOOVI_APP_ID não definida.';
        log_activity("woovi_create_pix_key: {$msg}", 'woovi_pix_key_rotation.log', __DIR__ . '/..');
        return ['success' => false, 'error' => $msg];
    }

    $url = 'https://api.woovi.com/api/v1/pix-keys';
    $payload = json_encode([
        'type' => $type
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $msg = "cURL error: {$curl_error}";
        log_activity("woovi_create_pix_key erro: {$msg}", 'woovi_pix_key_rotation.log', __DIR__ . '/..');
        return ['success' => false, 'error' => $msg];
    }

    $data = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        log_activity("woovi_create_pix_key sucesso: Chave criada. Code: {$http_code}", 'woovi_pix_key_rotation.log', __DIR__ . '/..');
        return ['success' => true, 'data' => $data, 'http_code' => $http_code];
    }

    $error_msg = $data['error'] ?? "HTTP Status {$http_code}";
    log_activity("woovi_create_pix_key falha: {$error_msg} | Body: {$response}", 'woovi_pix_key_rotation.log', __DIR__ . '/..');
    return ['success' => false, 'error' => $error_msg, 'http_code' => $http_code, 'raw_response' => $response];
}
