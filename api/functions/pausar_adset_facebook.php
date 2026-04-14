<?php
/**
 * Pausa um Adset no Facebook
 */
function pausarAdsetFacebook($adsetId) {
    if (!defined('META_ACCESS_TOKEN')) {
        return ['success' => false, 'error' => 'META_ACCESS_TOKEN not defined'];
    }
    
    $url = "https://graph.facebook.com/v18.0/$adsetId";
    $payload = ['status' => 'PAUSED', 'access_token' => META_ACCESS_TOKEN];
    
    // Chamada cURL omitida por brevidade, mas deve ser implementada usando make_http_request
    // $response = make_http_request($url, 'POST', $payload);
    // return $response;
}
