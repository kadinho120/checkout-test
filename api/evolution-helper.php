<?php
// api/evolution-helper.php

/**
 * Replace shortcodes in message
 */
function replaceShortcodes($text, $customer, $pixCode = '')
{
    $firstName = explode(' ', trim($customer['name']))[0];

    $replacements = [
        '{primeiro_nome}' => $firstName,
        '{nome_completo}' => $customer['name'],
        '{email}' => $customer['email'],
        '{telefone}' => $customer['phone'],
        '{pix_copia_cola}' => $pixCode
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $text);
}

/**
 * Send Message via Evolution API
 * Returns array ['success' => bool, 'response' => array]
 */
function sendEvolutionMessage($instance, $token, $baseUrl, $phone, $type, $message, $fileUrl = null)
{
    if (empty($instance) || empty($token) || empty($baseUrl) || empty($phone)) {
        return ['success' => false, 'error' => 'Missing configuration'];
    }

    // Sanitize phone (remove non-digits)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Ensure 55 (DDI) if missing (assuming BR)
    if (strlen($phone) >= 10 && strlen($phone) <= 11) {
        $phone = '55' . $phone;
    }

    // Remove trailing slash from URL
    $baseUrl = rtrim($baseUrl, '/');

    // Headers
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $token
    ];

    $response = null;
    $endpoint = '';
    $payload = [];

    if ($type === 'text') {
        $endpoint = "/message/sendText/{$instance}";
        $payload = [
            "number" => $phone,
            "text" => $message
        ];
    } elseif ($type === 'pdf' || $type === 'image') {
        $endpoint = "/message/sendMedia/{$instance}";

        $mime = 'application/pdf'; // default
        $mediaType = 'document';

        if ($fileUrl) {
            $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                $mediaType = 'image';
            }
        }

        $payload = [
            "number" => $phone,
            "mediatype" => $mediaType,
            "mimetype" => $mime,
            "caption" => $message,
            "media" => $fileUrl,
            "fileName" => basename($fileUrl)
        ];
    } else {
        return ['success' => false, 'error' => 'Invalid deliverable type'];
    }

    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $jsonResp = json_decode($rawResponse, true);
    $success = ($httpCode >= 200 && $httpCode < 300);

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $jsonResp
    ];
}
