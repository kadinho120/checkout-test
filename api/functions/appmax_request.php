<?php
/**
 * api/functions/appmax_request.php
 * Função isolada para realizar chamadas HTTP à API da Appmax.
 */

require_once __DIR__ . '/log_activity.php';
require_once __DIR__ . '/get_appmax_token.php';

function appmax_request($url, $method = 'POST', $payload = [], $headers = [])
{
    $ch = curl_init($url);

    $default_headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppmaxIntegration/1.0'
    ];

    // Checa se já existe header de Authorization informado
    $has_auth = false;
    foreach ($headers as $h) {
        if (stripos($h, 'Authorization:') === 0) {
            $has_auth = true;
            break;
        }
    }

    if (!$has_auth) {
        $token = get_appmax_token();
        if ($token) {
            $default_headers[] = 'Authorization: Bearer ' . $token;
        }
    }

    $merged_headers = array_merge($default_headers, $headers);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $merged_headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if (!empty($payload) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        log_activity("Appmax Request Curl Error: {$curl_error} | URL: {$url}", 'appmax_errors.log', __DIR__ . '/..');
    }

    $json_data = json_decode($response_body, true);

    return [
        'http_code' => $http_code,
        'body' => $response_body,
        'data' => $json_data,
        'error' => $curl_error
    ];
}
