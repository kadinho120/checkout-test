<?php
/**
 * api/functions/get_appmax_token.php
 * Obtém e faz cache do token Bearer OAuth2 da Appmax.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/log_activity.php';

function get_appmax_token($force_refresh = false)
{
    $client_id = defined('APPMAX_CLIENT_ID') && !empty(APPMAX_CLIENT_ID) 
        ? APPMAX_CLIENT_ID 
        : (getenv('APPMAX_CLIENT_ID') ?: '');

    $client_secret = defined('APPMAX_CLIENT_SECRET') && !empty(APPMAX_CLIENT_SECRET) 
        ? APPMAX_CLIENT_SECRET 
        : (getenv('APPMAX_CLIENT_SECRET') ?: '');

    if (empty($client_id) || empty($client_secret)) {
        log_activity("Appmax Auth Error: Client ID ou Client Secret ausentes.", 'appmax_errors.log', __DIR__ . '/..');
        return null;
    }

    $cache_dir = __DIR__ . '/../logs';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    $cache_file = $cache_dir . '/appmax_token_' . md5($client_id) . '.json';

    // Se não for forçado, checa o cache
    if (!$force_refresh && file_exists($cache_file)) {
        $cache_content = @file_get_contents($cache_file);
        if ($cache_content) {
            $token_data = json_decode($cache_content, true);
            if (isset($token_data['access_token'], $token_data['expires_at'])) {
                // Se ainda faltar mais de 120s para expirar, utiliza o token em cache
                if ($token_data['expires_at'] > (time() + 120)) {
                    return $token_data['access_token'];
                }
            }
        }
    }

    // Solicita novo token via OAuth2
    $auth_url = 'https://auth.appmax.com.br/oauth2/token';
    $post_data = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ]);

    $ch = curl_init($auth_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppmaxIntegration/1.0'
    ]);

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        log_activity("Appmax Token Curl Error: {$curl_error}", 'appmax_errors.log', __DIR__ . '/..');
        return null;
    }

    $res_json = json_decode($response_body, true);

    if ($http_code >= 200 && $http_code < 300 && !empty($res_json['access_token'])) {
        $expires_in = (int) ($res_json['expires_in'] ?? 3600);
        $save_data = [
            'access_token' => $res_json['access_token'],
            'expires_at' => time() + $expires_in,
            'scope' => $res_json['scope'] ?? ''
        ];
        @file_put_contents($cache_file, json_encode($save_data));
        return $res_json['access_token'];
    }

    log_activity("Appmax Token Failed [HTTP {$http_code}]: {$response_body}", 'appmax_errors.log', __DIR__ . '/..');
    return null;
}
