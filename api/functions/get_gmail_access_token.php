<?php
/**
 * Obtém um novo Access Token do Google usando o Refresh Token
 */
require_once __DIR__ . '/make_http_request.php';

function getGmailAccessToken()
{
    $clientId = GMAIL_CLIENT_ID;
    $clientSecret = GMAIL_CLIENT_SECRET;
    $refreshToken = GMAIL_REFRESH_TOKEN;

    if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
        return ['success' => false, 'error' => 'Gmail credentials missing in environment'];
    }

    $url = 'https://accounts.google.com/o/oauth2/token';
    $data = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token'
    ];

    $response = make_http_request($url, 'POST', http_build_query($data), [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'User-Agent' => 'PHP-Gmail-API-Client'
    ]);

    if ($response['http_code'] !== 200) {
        return [
            'success' => false,
            'error' => 'Failed to refresh token: ' . ($response['body'] ?: $response['error']),
            'http_code' => $response['http_code']
        ];
    }

    $result = json_decode($response['body'], true);
    if (isset($result['access_token'])) {
        return ['success' => true, 'access_token' => $result['access_token']];
    }

    return ['success' => false, 'error' => 'Access token not found in response'];
}
