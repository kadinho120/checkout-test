<?php
// api/meta-capi-helper.php

/**
 * Normaliza e hash os dados do usuário conforme requisitos da Meta (SHA-256)
 */
function normalizeAndHash($data, $key)
{
    if (empty($data))
        return null;
    $data = strtolower(trim($data));

    // Normalizações específicas
    if ($key === 'ph') {
        // Remover tudo que não for número
        $data = preg_replace('/[^0-9]/', '', $data);
        // Remover 0 à esquerda
        $data = ltrim($data, '0');
        // Adicionar código do país se faltar (assumindo BR +55 para números de 10-11 digitos)
        if (strlen($data) >= 10 && strlen($data) <= 11) {
            $data = '55' . $data;
        }
    }

    return hash('sha256', $data);
}

/**
 * Envia evento para Meta Conversions API
 */
function sendMetaEvent($pixelId, $token, $eventName, $eventData, $context = [])
{
    if (empty($pixelId) || empty($token))
        return;

    $apiVersion = 'v19.0';
    $url = "https://graph.facebook.com/{$apiVersion}/{$pixelId}/events?access_token={$token}";

    // Preparar User Data
    $userData = [
        'client_ip_address' => $context['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
        'client_user_agent' => $context['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
    ];

    if (!empty($eventData['customer'])) {
        $c = $eventData['customer'];
        if (!empty($c['email']))
            $userData['em'] = [normalizeAndHash($c['email'], 'em')];
        if (!empty($c['phone']))
            $userData['ph'] = [normalizeAndHash($c['phone'], 'ph')];
        if (!empty($c['name'])) {
            // Tentar separar nome e sobrenome
            $parts = explode(' ', trim($c['name']), 2);
            $userData['fn'] = [normalizeAndHash($parts[0], 'fn')];
            if (count($parts) > 1) {
                $userData['ln'] = [normalizeAndHash($parts[1], 'ln')];
            }
        }
    }

    // ID Externo se disponível (para match)
    if (!empty($context['fbp']))
        $userData['fbp'] = $context['fbp'];
    if (!empty($context['fbc']))
        $userData['fbc'] = $context['fbc'];

    // Custom Data
    $customData = [
        'currency' => 'BRL',
        'value' => isset($eventData['value']) ? $eventData['value'] / 100 : 0
    ];

    // Adicionar produtos se houver
    if (!empty($eventData['products'])) {
        $customData['content_type'] = 'product';
        $contents = [];
        foreach ($eventData['products'] as $prod) {
            $contents[] = [
                'id' => $prod['sku'] ?? $prod['id'] ?? 'unknown',
                'quantity' => $prod['qty'] ?? 1,
                'item_price' => $prod['price'] ?? 0
            ];
        }
        $customData['contents'] = $contents;
    }

    $payload = [
        'data' => [
            [
                'event_name' => $eventName,
                'event_time' => time(),
                'event_id' => $eventData['correlation_id'] ?? uniqid('evt_', true),
                'event_source_url' => $context['source_url'] ?? null,
                'action_source' => 'website',
                'user_data' => array_filter($userData),
                'custom_data' => $customData
            ]
        ]
        // 'test_event_code' => 'TEST12345' // Descomentar para testar no Event Manager
    ];

    // Envio cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response];
}
