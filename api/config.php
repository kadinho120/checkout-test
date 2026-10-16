<?php
// api/config.php

// Evita acesso direto se não definido ABSPATH (segurança básica)
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// ====================================================
// CREDENCIAIS SEGURAS (Lidas do Ambiente/Environment)
// ====================================================

// A função getenv() busca o valor configurado no Easypanel.
// O operador '?:' define uma string vazia '' caso a variável não exista, evitando erros.

define('APPMAX_API_TOKEN', getenv('APPMAX_API_TOKEN') ?: 'CC4136CD-AE5AEDD0-FB9612A1-3D66212A');
define('APPMAX_CLIENT_ID', getenv('APPMAX_CLIENT_ID') ?: '3848dbbfa69b41cfb8bb3ba7de3eb2db');
define('APPMAX_CLIENT_SECRET', getenv('APPMAX_CLIENT_SECRET') ?: '98b800f0b4bd455ba39be5abb93a974e');
define('APPMAX_EXTERNAL_ID', getenv('APPMAX_EXTERNAL_ID') ?: 'a688709f-01c4-4ff5-a2ae-d5555d23e956');

define('WOOVI_APP_ID', getenv('WOOVI_APP_ID') ?: '');

define('ABACATEPAY_API_KEY', getenv('ABACATEPAY_API_KEY') ?: '');

define('N8N_WEBHOOK_SECRET', getenv('N8N_WEBHOOK_SECRET') ?: '');

define('META_CONVERSIONS_API_TOKEN', getenv('META_CONVERSIONS_API_TOKEN') ?: '');

define('SMM_API_KEY', getenv('SMM_API_KEY') ?: '');

define('TRIBOPAY_API_TOKEN', getenv('TRIBOPAY_API_TOKEN') ?: '');

define('TRIBOPAY_POSTBACK_URL', getenv('TRIBOPAY_POSTBACK_URL') ?: '');

define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: '');

define('UTMIFY_API_TOKEN', getenv('UTMIFY_API_TOKEN') ?: '');

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 465);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

define('GMAIL_CLIENT_ID', getenv('GMAIL_CLIENT_ID') ?: '');
define('GMAIL_CLIENT_SECRET', getenv('GMAIL_CLIENT_SECRET') ?: '');
define('GMAIL_REFRESH_TOKEN', getenv('GMAIL_REFRESH_TOKEN') ?: '');




// ====================================================
// CONFIGURAÇÕES GERAIS DO PHP
// ====================================================

// Define o fuso horário (importante para PIX e logs)
date_default_timezone_set('America/Sao_Paulo');

// Oculta erros na tela (segurança em produção), mas mantém o log interno
@ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configurações de sessão
ini_set('session.gc_maxlifetime', 172800);
ini_set('session.cookie_lifetime', 172800);
