<?php
// api/process-pix-woovi.php

// 1. Carrega as configurações e chaves (substitui o wp-config)
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

/**
 * Função auxiliar para fazer requisições HTTP sem depender do WordPress
 */
function make_http_request($url, $method = 'GET', $data = null, $headers = [])
{
    $ch = curl_init($url);

    // Configurações básicas do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Configura o corpo da requisição se houver dados
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
    }

    // Configura os cabeçalhos
    $formatted_headers = [];
    foreach ($headers as $key => $value) {
        if (is_numeric($key)) {
            $formatted_headers[] = $value;
        } else {
            $formatted_headers[] = "$key: $value";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted_headers);

    // Executa a requisição
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    return [
        'body' => $response,
        'http_code' => $http_code,
        'error' => $curl_error
    ];
}

/**
 * Função para gerar um CPF aleatório e válido matematicamente
 */
function generate_valid_cpf()
{
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);

    // Primeiro Dígito Verificador
    $d1 = $n9 * 2 + $n8 * 3 + $n7 * 4 + $n6 * 5 + $n5 * 6 + $n4 * 7 + $n3 * 8 + $n2 * 9 + $n1 * 10;
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) {
        $d1 = 0;
    }

    // Segundo Dígito Verificador
    $d2 = $d1 * 2 + $n9 * 3 + $n8 * 4 + $n7 * 5 + $n6 * 6 + $n5 * 7 + $n4 * 8 + $n3 * 9 + $n2 * 10 + $n1 * 11;
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) {
        $d2 = 0;
    }

    return "{$n1}{$n2}{$n3}{$n4}{$n5}{$n6}{$n7}{$n8}{$n9}{$d1}{$d2}";
}

/**
 * Função para gerar um número de telefone aleatório (formato 55 + DDD + 9 + 8 dígitos)
 */
function generate_random_phone()
{
    // Lista de DDDs comuns para evitar bloqueios por DDD inválido
    $ddds = [11, 21, 31, 41, 51, 61, 71, 81, 91, 19, 13, 12];
    $ddd = $ddds[array_rand($ddds)];

    // Gera 8 dígitos aleatórios
    $number = mt_rand(10000000, 99999999);

    return "55{$ddd}9{$number}";
}

function handle_woovi_pix_payment()
{
    // Obtém os dados JSON enviados no corpo da requisição
    $json_params = file_get_contents('php://input');
    $params = json_decode($json_params, true);

    // Validação básica
    if (json_last_error() !== JSON_ERROR_NONE || !isset($params['value'], $params['correlation_id'], $params['customer'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Dados inválidos. Valor, correlation_id e customer são obrigatórios.']));
    }

    // Busca a chave da API definida no config.php
    if (!defined('WOOVI_APP_ID') || empty(WOOVI_APP_ID)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Erro de configuração: WOOVI_APP_ID não definida.']));
    }

    $api_key = WOOVI_APP_ID;
    $correlationID = $params['correlation_id'];

    // Formatação de telefone (Envia o número real do cliente)
    $clean_phone = preg_replace('/[^0-9]/', '', $params['customer']['phone']);

    // Verifica se o número tem conteúdo, senão usa um fallback ou gera erro
    if (empty($clean_phone)) {
        // Se por algum motivo chegar vazio, aí sim usamos o gerador para não quebrar a API
        $whatsapp_formatted = generate_random_phone();
    } else {
        // Se o número não começar com 55 (DDI Brasil), adiciona
        if (substr($clean_phone, 0, 2) !== '55') {
            $whatsapp_formatted = '55' . $clean_phone;
        } else {
            $whatsapp_formatted = $clean_phone;
        }
    }

    // --- LÓGICA DE PRODUTOS (Para uso interno) ---
    $product_description = 'Acesso ao Protocolo Alpha-7';
    if (isset($params['products']) && is_array($params['products'])) {
        $product_names = array_column(array_filter($params['products'], fn($p) => !empty($p['name'])), 'name');
        if (!empty($product_names)) {
            $product_description = implode(' + ', $product_names);
        }
    }

    // Payload para a Woovi
    $payload = [
        'correlationID' => $correlationID,
        'value' => $params['value'], // Centavos
        'type' => 'DYNAMIC',
        // 'comment'    => $product_description, // Removido para não aparecer no Pix
        'customer' => [
            'name' => $params['customer']['name'],
            'email' => $params['customer']['email'],
            'phone' => $whatsapp_formatted,
            'taxID' => generate_valid_cpf() // <--- GERA UM CPF ALEATÓRIO E VÁLIDO A CADA PEDIDO
        ]
    ];

    // --- CHAMADA À API DA WOOVI ---
    $api_url = 'https://api.woovi.com/api/v1/charge';

    $response = make_http_request($api_url, 'POST', $payload, [
        'Authorization' => $api_key,
        'Content-Type' => 'application/json'
    ]);

    // Verifica erros de conexão
    if ($response['error']) {
        http_response_code(500);
        error_log('Woovi API Curl Error: ' . $response['error']);
        die(json_encode(['success' => false, 'message' => 'Erro de conexão com o gateway: ' . $response['error']]));
    }

    $data = json_decode($response['body'], true);

    // Verifica erros da API ou formato inválido
    if ($response['http_code'] >= 400 || isset($data['error']) || !isset($data['charge']['brCode'])) {
        http_response_code($response['http_code']);
        $msg_erro = $data['error'] ?? 'Resposta inesperada da Woovi.';
        error_log('Woovi Gateway Error: ' . $msg_erro . ' Body: ' . $response['body']);
        die(json_encode(['success' => false, 'message' => 'Gateway Woovi: ' . $msg_erro]));
    }

    // Prepara dados de retorno
    $pix_data = [
        'brCode' => $data['charge']['brCode'],
        'qrCodeImage' => $data['charge']['qrCodeImage'],
        'formattedPrice' => 'R$ ' . number_format($params['value'] / 100, 2, ',', '.')
    ];

    // --- SALVAMENTO E WEBHOOK (VERSÃO BLINDADA - SQLite) ---
    require_once __DIR__ . '/connection.php';
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, customer_cpf, total_amount, status, payment_method, transaction_id, json_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

        $json_data_store = json_encode([
            'correlation_id' => $correlationID,
            'pix_data' => $pix_data,
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? []
        ]);

        // Mock Product ID 0 or extract from first item if available. 
        // Logic assumes one main product or bundle.
        $mainProductId = 0;
        // We could look up by SKU if we had a mapping, for now 0 or first product.

        $stmt->execute([
            $mainProductId,
            $params['customer']['name'],
            $params['customer']['email'],
            $whatsapp_formatted,
            $params['customer']['document'] ?? '', // if available
            $params['value'] / 100, // Amount in BRL
            'pending',
            'pix',
            $correlationID, // Using correlation ID as transaction ID for tracking
            $json_data_store
        ]);

        // 3. Webhook N8N (Enriched Payload)
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-gerado-abacatepay';

        // Constroi o payload COMPLETO para o N8N
        $full_webhook_payload = [
            'correlation_id' => $correlationID,
            'status' => 'pending',
            'value' => $params['value'], // Centavos
            'value_formatted' => (float) ($params['value'] / 100), // Ex: 9 ou 13.5 (Float JSON padrão)
            'created_at' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $params['customer']['name'],
                'email' => $params['customer']['email'],
                'phone' => $whatsapp_formatted, // Telefone tratado
                'document' => $params['customer']['document'] ?? '' // Se houver
            ],
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? [], // UTMs, FBC, FBP, User Agent
            'fbclid' => $params['tracking']['fbclid'] ?? null, // <--- STANDALONE FBCLID
            'pixel_id' => $params['tracking']['pixel_id'] ?? null, // <--- PIXEL ID
            'pix_data' => $pix_data // QRCode, BRCode, Preço Formatado
        ];

        $ch = curl_init($n8n_webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($full_webhook_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

        $hook_response = curl_exec($ch);
        curl_close($ch);

        // --- UTMIFY TRACKING HOOK ---
        require_once __DIR__ . '/utmify-helper.php';

        // Prepare data for helper
        // Ideally we pass the exact structure expected
        $utmifyOrderData = [
            'correlation_id' => $correlationID,
            'value' => $params['value'],
            'status' => 'pending',
            'customer' => $params['customer'], // ensure phone/email keys match
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? []
        ];

        sendUtmifyEvent($utmifyOrderData, 'pending');
        // ----------------------------

    } catch (Exception $e) {
        error_log('Erro interno ao salvar pedido SQLite: ' . $e->getMessage());
    }

    // Retorna sucesso
    http_response_code(200);
    echo json_encode(['success' => true, 'pixData' => $pix_data, 'correlationId' => $correlationID]);
}

handle_woovi_pix_payment();
?>