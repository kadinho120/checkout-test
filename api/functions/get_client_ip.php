<?php
/**
 * Captura o IP real do cliente, lidando com proxies/Cloudflare
 */
function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Cloudflare
        $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Pode conter uma lista de IPs separados por vírgula
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ipaddress = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    return $ipaddress;
}
