<?php
/**
 * Envia e-mail via Gmail API
 */
require_once __DIR__ . '/make_http_request.php';
require_once __DIR__ . '/get_gmail_access_token.php';

function sendGmailApiEmail($to, $subject, $body, $fromName = 'Suporte')
{
    $auth = getGmailAccessToken();
    if (!$auth['success']) {
        return $auth;
    }

    $accessToken = $auth['access_token'];

    // Construct MIME message
    $boundary = uniqid('np', true);
    $rawMessage = "To: $to\r\n";
    $rawMessage .= "Subject: =?utf-8?B?" . base64_encode($subject) . "?=\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
    $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $rawMessage .= chunk_split(base64_encode($body));

    // Base64url encode the entire message
    $safeRawMessage = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

    $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json'
    ];
    $data = ['raw' => $safeRawMessage];

    $response = make_http_request($url, 'POST', json_encode($data), $headers);

    if ($response['http_code'] === 200) {
        return ['success' => true, 'response' => json_decode($response['body'], true)];
    }

    return [
        'success' => false,
        'error' => 'Gmail API error: ' . ($response['body'] ?: $response['error']),
        'http_code' => $response['http_code']
    ];
}
