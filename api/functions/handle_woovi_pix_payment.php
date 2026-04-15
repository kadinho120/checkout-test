<?php
/**
 * Função principal para processar o pagamento PIX da Woovi
 */
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

    // Tratamento de Telefone
    $raw_phone = $params['customer']['phone'] ?? '';
    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

    // Verifica se o número tem conteúdo, senão usa um fallback ou gera erro
    if (empty($clean_phone)) {
        $whatsapp_formatted = generate_random_phone();
    } else {
        if (substr($clean_phone, 0, 2) !== '55') {
            $whatsapp_formatted = '55' . $clean_phone;
        } else {
            $whatsapp_formatted = $clean_phone;
        }
    }

    // Tratamento de Email (Opcional)
    $customer_email = $params['customer']['email'] ?? '';
    if (empty($customer_email)) {
        $customer_email = 'cliente_' . $correlationID . '@naoinformado.com';
        $params['customer']['email'] = $customer_email;
    }

    // --- LÓGICA DE PRODUTOS ---
    $product_description = 'Acesso ao Produto';
    if (isset($params['products']) && is_array($params['products'])) {
        $product_names = array_column(array_filter($params['products'], fn($p) => !empty($p['name'])), 'name');
        if (!empty($product_names)) {
            $product_description = implode(' + ', $product_names);
        }
    }

    // Payload para a Woovi
    $payload = [
        'correlationID' => $correlationID,
        'value' => (int) round($params['value'] ?? 0),
        'type' => 'DYNAMIC',
        'customer' => [
            'name' => $params['customer']['name'],
            'email' => $customer_email,
            'phone' => $whatsapp_formatted,
            'taxID' => generate_valid_cpf()
        ]
    ];

    $api_url = 'https://api.woovi.com/api/v1/charge';

    $response = make_http_request($api_url, 'POST', $payload, [
        'Authorization' => $api_key,
        'Content-Type' => 'application/json'
    ]);

    if ($response['error']) {
        http_response_code(500);
        log_activity('Woovi API Curl Error: ' . $response['error'], 'woovi_errors.log', __DIR__ . '/..');
        die(json_encode(['success' => false, 'message' => 'Erro de conexão com o gateway: ' . $response['error']]));
    }

    $data = json_decode($response['body'], true);

    if ($response['http_code'] >= 400 || isset($data['error']) || !isset($data['charge']['brCode'])) {
        http_response_code($response['http_code']);
        $msg_erro = $data['error'] ?? 'Resposta inesperada da Woovi.';
        log_activity('Woovi Gateway Error: ' . $msg_erro . ' | Body: ' . $response['body'], 'woovi_errors.log', __DIR__ . '/..');
        die(json_encode(['success' => false, 'message' => 'Gateway Woovi: ' . $msg_erro]));
    }

    $pix_data = [
        'brCode' => $data['charge']['brCode'],
        'qrCodeImage' => $data['charge']['qrCodeImage'],
        'formattedPrice' => 'R$ ' . number_format(($params['value'] ?? 0) / 100, 2, ',', '.')
    ];

    // --- SALVAMENTO E WEBHOOK ---
    require_once __DIR__ . '/../connection.php';
    try {
        $database = new Database();
        $db = $database->getConnection();

        $externalID = $params['customer']['external_id'] ?? '';

        $stmt = $db->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, customer_cpf, total_amount, status, payment_method, transaction_id, external_id, json_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

        $json_data_store = json_encode([
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'pix_data' => $pix_data,
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? []
        ]);

        $mainProductId = 0;

        $stmt->execute([
            $mainProductId,
            $params['customer']['name'],
            $params['customer']['email'],
            $whatsapp_formatted,
            $params['customer']['document'] ?? '',
            $params['value'] / 100,
            'pending',
            'pix',
            $correlationID,
            $externalID,
            $json_data_store
        ]);

        $order_id = $db->lastInsertId();

        // Disparo de Webhooks Customizados
        require_once __DIR__ . '/trigger_custom_webhooks.php';
        trigger_custom_webhooks('order.created', $order_id);

        // Webhook N8N (Legado/Fixo)
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-gerado-abacatepay';

        $full_webhook_payload = [
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'status' => 'pending',
            'value' => $params['value'],
            'value_formatted' => (float) ($params['value'] / 100),
            'created_at' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $params['customer']['name'],
                'email' => $customer_email,
                'phone' => $whatsapp_formatted,
                'document' => $params['customer']['document'] ?? '',
                'external_id' => $externalID
            ],
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? [],
            'fbclid' => $params['tracking']['fbclid'] ?? null,
            'pixel_id' => $params['tracking']['pixel_id'] ?? null,
            'pix_data' => $pix_data
        ];

        $ch = curl_init($n8n_webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($full_webhook_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_exec($ch);
        curl_close($ch);

        // UTMIFY
        sendUtmifyEvent($full_webhook_payload, 'pending');

    } catch (Exception $e) {
        error_log('Erro interno ao salvar pedido SQLite: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'pixData' => $pix_data, 'correlationId' => $correlationID]);
}
