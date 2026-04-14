<?php
// api/process-pix-woovi.php

// 1. Carrega as configurações e chaves
require_once __DIR__ . '/config.php';

// 2. Carrega as funções isoladas
require_once __DIR__ . '/functions/make_http_request.php';
require_once __DIR__ . '/functions/generate_valid_cpf.php';
require_once __DIR__ . '/functions/generate_random_phone.php';
require_once __DIR__ . '/functions/send_utmify_event.php';
require_once __DIR__ . '/functions/handle_woovi_pix_payment.php';

header('Content-Type: application/json');

// 3. Executa a função principal
handle_woovi_pix_payment();