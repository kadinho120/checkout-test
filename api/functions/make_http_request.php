<?php
/**
 * Função auxiliar para fazer requisições HTTP sem depender do WordPress
 */
function make_http_request($url, $method = 'GET', $data = null, $headers = [])
{
    $ch = curl_init($url);

    // Configurações básicas do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Configura o corpo da requisição se houver dados
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
    }

    // Configura os cabeçalhos
    $formatted_headers = [];
    foreach ($headers as $key => $value) {
        if (is_numeric($key)) {
            $formatted_headers[] = $value;
        } else {
            $formatted_headers[] = "$key: $value";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted_headers);

    // Executa a requisição
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    return [
        'body' => $response,
        'http_code' => $http_code,
        'error' => $curl_error
    ];
}
