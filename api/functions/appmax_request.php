<?php
/**
 * api/functions/appmax_request.php
 * Função isolada para realizar chamadas HTTP à API da Appmax.
 */

require_once __DIR__ . '/log_activity.php';

function appmax_request($url, $method = 'POST', $payload = [], $headers = [])
{
    $ch = curl_init($url);

    $default_headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $merged_headers = array_merge($default_headers, $headers);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $merged_headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
