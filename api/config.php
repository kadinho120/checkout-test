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

define('APPMAX_API_TOKEN', getenv('APPMAX_API_TOKEN') ?: '');

define('WOOVI_APP_ID', getenv('WOOVI_APP_ID') ?: '');

define('ABACATEPAY_API_KEY', getenv('ABACATEPAY_API_KEY') ?: '');

define('N8N_WEBHOOK_SECRET', getenv('N8N_WEBHOOK_SECRET') ?: '');

define('META_CONVERSIONS_API_TOKEN', getenv('META_CONVERSIONS_API_TOKEN') ?: '');

define('SMM_API_KEY', getenv('SMM_API_KEY') ?: '');

define('TRIBOPAY_API_TOKEN', getenv('TRIBOPAY_API_TOKEN') ?: '');

define('TRIBOPAY_POSTBACK_URL', getenv('TRIBOPAY_POSTBACK_URL') ?: '');

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
