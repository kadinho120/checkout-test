<?php
// api/process-card.php
// Endpoint para processamento de pagamentos com Cartão de Crédito Appmax

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/functions/handle_appmax_card_payment.php';

header('Content-Type: application/json');

handle_appmax_card_payment();
