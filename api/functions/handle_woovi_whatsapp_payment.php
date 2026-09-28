<?php
/**
 * Função para processar o pagamento PIX da Woovi com entrega manual via WhatsApp
 * Combina a API da Woovi (QR Code dinâmico, polling, Meta CAPI) com instruções manuais via WhatsApp
 */
function handle_woovi_whatsapp_payment()
{
    // Obtém os dados JSON enviados no corpo da requisição
    $json_params = file_get_contents('php://input');
    $params = json_decode($json_params, true);

    require_once __DIR__ . '/get_client_ip.php';
    $client_ip = get_client_ip();

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
    $totalValueInCents = (int) round($params['value'] ?? 0);
    $totalValueInReais = $totalValueInCents / 100;

    // Tratamento de Nome
    $customer_name = trim($params['customer']['name'] ?? '');
    if (empty($customer_name)) {
        $customer_name = 'Cliente #' . strtoupper(substr($correlationID, -4));
        $params['customer']['name'] = $customer_name;
    }
    $firstName = explode(' ', $customer_name)[0];

    // Tratamento de Telefone
    $raw_phone = $params['customer']['phone'] ?? '';
    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

    if (empty($clean_phone)) {
        require_once __DIR__ . '/generate_random_phone.php';
        $whatsapp_formatted = generate_random_phone();
    } else {
        if (substr($clean_phone, 0, 2) !== '55') {
            $whatsapp_formatted = '55' . $clean_phone;
        } else {
            $whatsapp_formatted = $clean_phone;
        }
    }

    // Tratamento de Email (Opcional)
    $customer_email = trim($params['customer']['email'] ?? '');
    if (empty($customer_email)) {
        $customer_email = 'cliente_' . $correlationID . '@naoinformado.com';
        $params['customer']['email'] = $customer_email;
    }

    // --- LÓGICA DE PRODUTOS ---
    $product_description = 'Acesso ao Produto';
    $mainProductSlug = '';
    $mainProductId = 0;

    if (isset($params['products']) && is_array($params['products']) && count($params['products']) > 0) {
        $product_names = array_column(array_filter($params['products'], fn($p) => !empty($p['name'])), 'name');
        if (!empty($product_names)) {
            $product_description = implode(' + ', $product_names);
        }
        $mainProductSlug = $params['products'][0]['sku'] ?? '';
        $mainProductId = (int) ($params['products'][0]['id'] ?? 0);
    }

    // Payload para a Woovi
    require_once __DIR__ . '/generate_valid_cpf.php';
    require_once __DIR__ . '/make_http_request.php';
    require_once __DIR__ . '/log_activity.php';

    $payload = [
        'correlationID' => $correlationID,
        'value' => $totalValueInCents,
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

    // --- CARREGA CONFIGURAÇÕES DO WHATSAPP DO PRODUTO ---
    require_once __DIR__ . '/../connection.php';
    $database = new Database();
    $db = $database->getConnection();

    $productConfig = null;
    if ($mainProductId > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$mainProductId]);
        $productConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!empty($mainProductSlug)) {
        $stmt = $db->prepare("SELECT * FROM products WHERE slug = ?");
        $stmt->execute([$mainProductSlug]);
        $productConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $whatsappNumber = !empty($productConfig['pix_whatsapp_number']) ? trim($productConfig['pix_whatsapp_number']) : (getenv('DEFAULT_WHATSAPP_SUPPORT') ?: '');
    $whatsappMsgTemplate = !empty($productConfig['pix_whatsapp_message']) ? trim($productConfig['pix_whatsapp_message']) : '';
    $instructionType = !empty($productConfig['pix_instruction_type']) ? trim($productConfig['pix_instruction_type']) : 'name';
    $customButtonText = !empty($productConfig['pix_whatsapp_button_text']) ? trim($productConfig['pix_whatsapp_button_text']) : '';

    require_once __DIR__ . '/format_price.php';
    $formattedPrice = 'R$ ' . format_price($totalValueInReais);

    // Formatação da Mensagem do WhatsApp
    if (empty($whatsappMsgTemplate)) {
        if ($instructionType === 'comprovante') {
            $whatsappMsgTemplate = "Olá! Acabei de fazer o pagamento do pedido #{pedido_id} ({produto}) no valor de {valor}.\n\nSegue o comprovante em anexo:";
        } else {
            $whatsappMsgTemplate = "Olá! Acabei de fazer o pagamento do pedido #{pedido_id} ({produto}) no valor de {valor}.\n\nMeu nome completo é: {nome}";
        }
    }

    $shortCustomerCode = '#' . strtoupper(substr($correlationID, -4));

    $whatsappReplacements = [
        '{nome}' => $customer_name,
        '{primeiro_nome}' => $firstName,
        '{produto}' => $product_description,
        '{valor}' => $formattedPrice,
        '{pedido_id}' => $correlationID,
        '{codigo_cliente}' => $shortCustomerCode,
        '{codigo}' => $shortCustomerCode,
        '{pix_chave}' => ''
    ];

    $whatsappMessageFinal = str_replace(array_keys($whatsappReplacements), array_values($whatsappReplacements), $whatsappMsgTemplate);

    // Limpa o número de WhatsApp para montar o link wa.me
    $cleanWhatsappNumber = preg_replace('/\D/', '', $whatsappNumber);
    if (!empty($cleanWhatsappNumber) && strlen($cleanWhatsappNumber) >= 10 && strlen($cleanWhatsappNumber) <= 11) {
        $cleanWhatsappNumber = '55' . $cleanWhatsappNumber;
    }

    $whatsappUrl = '';
    if (!empty($cleanWhatsappNumber)) {
        $whatsappUrl = 'https://wa.me/' . $cleanWhatsappNumber . '?text=' . rawurlencode($whatsappMessageFinal);
    }

    $pix_data = [
        'brCode' => $data['charge']['brCode'],
        'qrCodeImage' => $data['charge']['qrCodeImage'],
        'formattedPrice' => $formattedPrice,
        'whatsapp_delivery' => true,
        'whatsapp_url' => $whatsappUrl,
        'whatsapp_number' => $cleanWhatsappNumber,
        'whatsapp_message' => $whatsappMessageFinal,
        'pix_instruction_type' => $instructionType,
        'whatsapp_button_text' => $customButtonText
    ];

    // --- SALVAMENTO NO BANCO SQLITE ---
    try {
        $externalID = $params['customer']['external_id'] ?? '';

        $stmt = $db->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, customer_cpf, total_amount, status, payment_method, gateway, transaction_id, external_id, cep, address, address_number, complement, neighborhood, city, state, json_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

        $json_data_store = json_encode([
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'pix_data' => $pix_data,
            'products' => $params['products'] ?? [],
            'tracking' => array_merge($params['tracking'] ?? [], ['client_ip' => $client_ip])
        ]);

        $stmt->execute([
            $mainProductId,
            $params['customer']['name'],
            $params['customer']['email'],
            $whatsapp_formatted,
            $params['customer']['document'] ?? '',
            $totalValueInReais,
            'pending',
            'pix',
            'woovi_whatsapp',
            $correlationID,
            $externalID,
            $params['customer']['cep'] ?? '',
            $params['customer']['address'] ?? '',
            $params['customer']['address_number'] ?? '',
            $params['customer']['complement'] ?? '',
            $params['customer']['neighborhood'] ?? '',
            $params['customer']['city'] ?? '',
            $params['customer']['state'] ?? '',
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
            'value_formatted' => $totalValueInReais,
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
        error_log('Erro interno ao salvar pedido SQLite (woovi_whatsapp): ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'pixData' => $pix_data, 'correlationId' => $correlationID]);
}
