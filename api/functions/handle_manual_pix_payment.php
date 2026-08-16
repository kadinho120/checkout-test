<?php
/**
 * Processador para Pagamento Pix Direto / Manual com Chave Pix e WhatsApp
 */
function handle_manual_pix_payment()
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

    // Tratamento de Email
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

    // Conexão com o banco para buscar configurações de Chave Pix do Produto
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

    $pixKey = !empty($productConfig['pix_key']) ? trim($productConfig['pix_key']) : (getenv('DEFAULT_PIX_KEY') ?: '');
    $receiverName = !empty($productConfig['pix_receiver_name']) ? trim($productConfig['pix_receiver_name']) : (getenv('DEFAULT_PIX_RECEIVER_NAME') ?: 'BENEFICIARIO');
    $receiverCity = !empty($productConfig['pix_receiver_city']) ? trim($productConfig['pix_receiver_city']) : (getenv('DEFAULT_PIX_RECEIVER_CITY') ?: 'SAO PAULO');
    $whatsappNumber = !empty($productConfig['pix_whatsapp_number']) ? trim($productConfig['pix_whatsapp_number']) : (getenv('DEFAULT_WHATSAPP_SUPPORT') ?: '');
    $whatsappMsgTemplate = !empty($productConfig['pix_whatsapp_message']) ? trim($productConfig['pix_whatsapp_message']) : '';

    if (empty($pixKey)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Chave Pix não configurada nas configurações deste produto.']));
    }

    // Formatação de Preço
    require_once __DIR__ . '/format_price.php';
    $formattedPrice = 'R$ ' . format_price($totalValueInReais);

    // Geração do Código Pix Copia e Cola Oficial BACEN
    require_once __DIR__ . '/generate_pix_brcode.php';
    $brCode = generatePixBrcode($pixKey, $receiverName, $receiverCity, $totalValueInReais, $correlationID);
    $qrCodeImage = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($brCode);

    // Formatação da Mensagem do WhatsApp
    if (empty($whatsappMsgTemplate)) {
        $whatsappMsgTemplate = "Olá! Acabei de fazer o pagamento do pedido #{pedido_id} ({produto}) no valor de {valor}.\n\nSegue o comprovante em anexo:";
    }

    $whatsappReplacements = [
        '{nome}' => $customer_name,
        '{primeiro_nome}' => $firstName,
        '{produto}' => $product_description,
        '{valor}' => $formattedPrice,
        '{pedido_id}' => $correlationID,
        '{pix_chave}' => $pixKey
    ];

    $whatsappMessageFinal = str_replace(array_keys($whatsappReplacements), array_values($whatsappReplacements), $whatsappMsgTemplate);

    // Limpa o número de WhatsApp para link
    $cleanWhatsappNumber = preg_replace('/\D/', '', $whatsappNumber);
    if (!empty($cleanWhatsappNumber) && strlen($cleanWhatsappNumber) >= 10 && strlen($cleanWhatsappNumber) <= 11) {
        $cleanWhatsappNumber = '55' . $cleanWhatsappNumber;
    }

    $whatsappUrl = '';
    if (!empty($cleanWhatsappNumber)) {
        $whatsappUrl = 'https://wa.me/' . $cleanWhatsappNumber . '?text=' . rawurlencode($whatsappMessageFinal);
    }

    $pix_data = [
        'brCode' => $brCode,
        'qrCodeImage' => $qrCodeImage,
        'formattedPrice' => $formattedPrice,
        'is_manual' => true,
        'pix_key' => $pixKey,
        'receiver_name' => $receiverName,
        'receiver_city' => $receiverCity,
        'whatsapp_url' => $whatsappUrl,
        'whatsapp_number' => $cleanWhatsappNumber,
        'whatsapp_message' => $whatsappMessageFinal
    ];

    // --- SALVAMENTO NO BANCO SQLITE ---
    try {
        $externalID = $params['customer']['external_id'] ?? '';

        $stmt = $db->prepare("INSERT INTO orders (product_id, customer_name, customer_email, customer_phone, customer_cpf, total_amount, status, payment_method, gateway, transaction_id, external_id, cep, address, address_number, complement, neighborhood, city, state, json_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

        $json_data_store = json_encode([
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'gateway' => 'manual_pix',
            'pix_data' => $pix_data,
            'products' => $params['products'] ?? [],
            'tracking' => array_merge($params['tracking'] ?? [], ['client_ip' => $client_ip])
        ]);

        $stmt->execute([
            $mainProductId ?: ($productConfig['id'] ?? 0),
            $params['customer']['name'],
            $params['customer']['email'],
            $whatsapp_formatted,
            $params['customer']['document'] ?? '',
            $totalValueInReais,
            'pending',
            'pix',
            'manual_pix',
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

        // Disparo UTMIFY
        require_once __DIR__ . '/send_utmify_event.php';
        $full_webhook_payload = [
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'status' => 'pending',
            'value' => $totalValueInCents,
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
            'pix_data' => $pix_data
        ];
        sendUtmifyEvent($full_webhook_payload, 'pending');

    } catch (Exception $e) {
        error_log('Erro interno ao salvar pedido manual_pix SQLite: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'pixData' => $pix_data,
        'correlationId' => $correlationID,
        'is_manual' => true,
        'whatsapp_url' => $whatsappUrl
    ]);
}
