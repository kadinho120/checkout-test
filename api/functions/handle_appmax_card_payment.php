<?php
/**
 * api/functions/handle_appmax_card_payment.php
 * Processa o pagamento com Cartão de Crédito via Appmax API V1.
 * Realiza tokenização segura e chamada de unified-order.
 */

function handle_appmax_card_payment()
{
    $json_params = file_get_contents('php://input');
    $params = json_decode($json_params, true);

    require_once __DIR__ . '/get_client_ip.php';
    require_once __DIR__ . '/log_activity.php';
    require_once __DIR__ . '/appmax_request.php';
    require_once __DIR__ . '/format_price.php';
    require_once __DIR__ . '/../connection.php';

    $client_ip = get_client_ip();

    if (json_last_error() !== JSON_ERROR_NONE || !isset($params['value'], $params['correlation_id'], $params['customer'], $params['card'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Dados inválidos. Valor, correlation_id, dados do cliente e cartão são obrigatórios.']));
    }

    $correlationID = $params['correlation_id'];

    // Tratamento de CPF (Obrigatório Appmax)
    $raw_document = preg_replace('/[^0-9]/', '', $params['customer']['document'] ?? '');
    if (strlen($raw_document) !== 11 && strlen($raw_document) !== 14) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'CPF obrigatório e válido para pagamento com cartão de crédito.']));
    }

    // Tratamento de Nome
    $customer_name = trim($params['customer']['name'] ?? '');
    if (empty($customer_name)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Nome completo é obrigatório.']));
    }

    // Tratamento de Email
    $customer_email = trim($params['customer']['email'] ?? '');
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'E-mail válido é obrigatório.']));
    }

    // Tratamento de Telefone
    $raw_phone = $params['customer']['phone'] ?? '';
    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);
    if (substr($clean_phone, 0, 2) === '55' && strlen($clean_phone) > 10) {
        $clean_phone = substr($clean_phone, 2);
    }
    $area_code = strlen($clean_phone) >= 10 ? substr($clean_phone, 0, 2) : '11';
    $phone_number = strlen($clean_phone) >= 10 ? substr($clean_phone, 2) : '999999999';
    $whatsapp_formatted = '55' . $area_code . $phone_number;

    // Dados do Cartão
    $card = $params['card'];
    $clean_card_number = preg_replace('/[^0-9]/', '', $card['number'] ?? '');
    $clean_cvv = preg_replace('/[^0-9]/', '', $card['cvv'] ?? '');
    $holder_name = trim($card['holder_name'] ?? '');
    if (empty($holder_name)) {
        $holder_name = $customer_name;
    }

    // Validade do Cartão (exp_month e exp_year)
    $exp_month = str_pad((string) (int) ($card['exp_month'] ?? 0), 2, '0', STR_PAD_LEFT);
    $raw_year = preg_replace('/[^0-9]/', '', (string) ($card['exp_year'] ?? ''));
    if (strlen($raw_year) === 4) {
        $exp_year = substr($raw_year, -2);
    } else {
        $exp_year = str_pad($raw_year, 2, '0', STR_PAD_LEFT);
    }

    $installments = max(1, min(12, (int) ($card['installments'] ?? 1)));

    if (strlen($clean_card_number) < 13 || strlen($clean_card_number) > 19) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Número de cartão inválido.']));
    }

    if (strlen($clean_cvv) < 3 || strlen($clean_cvv) > 4) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Código de segurança (CVV) inválido.']));
    }

    if ((int) $exp_month < 1 || (int) $exp_month > 12) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Mês de validade inválido.']));
    }

    // 1. TOKENIZAÇÃO DO CARTÃO NA APPMAX
    $tok_payload = [
        'payment_data' => [
            'credit_card' => [
                'number' => $clean_card_number,
                'cvv' => $clean_cvv,
                'expiration_month' => (string) (int) $exp_month,
                'expiration_year' => $exp_year,
                'holder_name' => $holder_name
            ]
        ]
    ];

    $tok_res = appmax_request('https://api.appmax.com.br/v1/payments/tokenize', 'POST', $tok_payload);

    if ($tok_res['http_code'] < 200 || $tok_res['http_code'] >= 300 || empty($tok_res['data']['data']['token'])) {
        $tok_err = $tok_res['data']['message'] ?? ($tok_res['data']['error'] ?? 'Não foi possível validar os dados do cartão.');
        log_activity("Appmax Tokenize Error: {$tok_err} | HTTP: {$tok_res['http_code']}", 'appmax_errors.log', __DIR__ . '/..');
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Erro nos dados do cartão: ' . $tok_err]));
    }

    $card_token = $tok_res['data']['data']['token'];

    // 2. MONTAGEM DOS ITENS E VALOR
    $value_in_cents = (int) round($params['value'] ?? 0);
    $items = [];
    if (isset($params['products']) && is_array($params['products']) && !empty($params['products'])) {
        foreach ($params['products'] as $idx => $p) {
            $p_price_cents = (int) round(($p['price'] ?? 0) * 100);
            if ($p_price_cents <= 0) $p_price_cents = $value_in_cents;
            $items[] = [
                'amount' => $p_price_cents,
                'description' => !empty($p['name']) ? $p['name'] : ('Item ' . ($idx + 1)),
                'quantity' => (int) ($p['qty'] ?? 1),
                'code' => !empty($p['sku']) ? $p['sku'] : ('SKU-' . ($idx + 1))
            ];
        }
    }

    if (empty($items)) {
        $items[] = [
            'amount' => $value_in_cents,
            'description' => 'Acesso ao Produto',
            'quantity' => 1,
            'code' => 'PROD-' . substr($correlationID, -6)
        ];
    } else {
        $sum_cents = 0;
        foreach ($items as $it) {
            $sum_cents += $it['amount'] * $it['quantity'];
        }
        if ($sum_cents !== $value_in_cents) {
            $items[0]['amount'] += ($value_in_cents - $sum_cents);
        }
    }

    $address_postcode = preg_replace('/[^0-9]/', '', $params['customer']['cep'] ?? '');
    $address_street = !empty($params['customer']['address']) ? $params['customer']['address'] : '';
    $address_number = !empty($params['customer']['address_number']) ? $params['customer']['address_number'] : '';
    $address_complement = $params['customer']['complement'] ?? '';
    $address_neighborhood = !empty($params['customer']['neighborhood']) ? $params['customer']['neighborhood'] : '';
    $address_city = !empty($params['customer']['city']) ? $params['customer']['city'] : '';
    $address_state = !empty($params['customer']['state']) ? strtoupper($params['customer']['state']) : '';

    // 3. UNIFIED-ORDER COM O TOKEN DE CARTÃO
    $unified_payload = [
        'customer' => [
            'name' => $customer_name,
            'email' => $customer_email,
            'phones' => [
                'mobile_phone' => [
                    'area_code' => $area_code,
                    'number' => $phone_number
                ]
            ]
        ],
        'ip' => !empty($client_ip) ? $client_ip : '127.0.0.1',
        'items' => $items,
        'payments' => [
            [
                'payment_method' => 'credit_card',
                'credit_card' => [
                    'token' => $card_token,
                    'holder_name' => $holder_name,
                    'holder_document' => $raw_document,
                    'installments' => $installments
                ]
            ]
        ],
        'client_key' => $correlationID,
        'external_key' => $correlationID
    ];

    if (!empty($address_street) && !empty($address_postcode)) {
        $unified_payload['shipping'] = [
            'amount' => 0,
            'address' => [
                'line_1' => trim($address_number . ', ' . $address_street . ', ' . $address_neighborhood, ', '),
                'line_2' => $address_complement,
                'zip_code' => $address_postcode,
                'city' => $address_city,
                'state' => $address_state
            ]
        ];
    }

    $cc_res = appmax_request('https://api.appmax.com.br/v1/orders/unified-order', 'POST', $unified_payload);

    $appmax_order_id = null;
    $resData = $cc_res['data']['data'] ?? ($cc_res['data'] ?? []);
    if (!empty($resData)) {
        $appmax_order_id = $resData['order_id'] ?? ($resData['order']['id'] ?? ($resData['id'] ?? ($cc_res['data']['order_id'] ?? null)));
    }

    $is_approved = false;
    if ($cc_res['http_code'] >= 200 && $cc_res['http_code'] < 300) {
        $order_status = strtolower($resData['order']['status'] ?? ($resData['status'] ?? ''));
        // Status aceitos como aprovados
        if (empty($order_status) || in_array($order_status, ['autorizado', 'aprovado', 'pago', 'paid', 'approved', 'authorized', 'pendente'])) {
            $is_approved = true;
        }
    }

    // Banco de Dados SQLite
    $database = new Database();
    $db = $database->getConnection();

    $externalID = !empty($appmax_order_id) ? (string)$appmax_order_id : (!empty($params['customer']['external_id']) ? (string)$params['customer']['external_id'] : '');
    $final_db_status = $is_approved ? 'paid' : 'failed';

    $stmt = $db->prepare("INSERT INTO orders (
        product_id, customer_name, customer_email, customer_phone, customer_cpf, 
        total_amount, status, payment_method, gateway, transaction_id, external_id, 
        cep, address, address_number, complement, neighborhood, city, state, 
        json_data, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

    $json_data_store = json_encode([
        'correlation_id' => $correlationID,
        'external_id' => $externalID,
        'gateway' => 'appmax',
        'payment_method' => 'credit_card',
        'installments' => $installments,
        'appmax_order_id' => $appmax_order_id,
        'products' => $params['products'] ?? [],
        'tracking' => array_merge($params['tracking'] ?? [], ['client_ip' => $client_ip]),
        'appmax_response' => $cc_res['data'] ?? null
    ]);

    $mainProductId = 0;
    if (isset($params['products'][0]['id'])) {
        $mainProductId = (int) $params['products'][0]['id'];
    }

    $stmt->execute([
        $mainProductId,
        $customer_name,
        $customer_email,
        $whatsapp_formatted,
        $raw_document,
        $value_in_cents / 100,
        $final_db_status,
        'credit_card',
        'appmax',
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

    if ($is_approved) {
        // Disparos de pós-venda
        require_once __DIR__ . '/trigger_custom_webhooks.php';
        trigger_custom_webhooks('order.paid', $order_id);

        require_once __DIR__ . '/process_order_deliverables.php';
        require_once __DIR__ . '/send_utmify_event.php';
        require_once __DIR__ . '/track_meta_purchase.php';

        $customerDataForHelper = [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $whatsapp_formatted,
            'document' => $raw_document
        ];

        $full_webhook_payload = [
            'id' => $order_id,
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'gateway' => 'appmax',
            'status' => 'paid',
            'value' => $value_in_cents,
            'value_formatted' => (float) ($value_in_cents / 100),
            'created_at' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $customer_name,
                'email' => $customer_email,
                'phone' => $whatsapp_formatted,
                'document' => $raw_document,
                'external_id' => $externalID
            ],
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? []
        ];

        // UTMIFY
        sendUtmifyEvent($full_webhook_payload, 'paid');

        // Entregáveis automáticos (e-mail e WhatsApp)
        processOrderDeliverables($params['products'] ?? [], $customerDataForHelper, $db);

        // Meta Conversions API (CAPI)
        trackMetaPurchase($order_id, $db);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'status' => 'paid',
            'correlationId' => $correlationID,
            'order_id' => $appmax_order_id,
            'total_amount' => $value_in_cents / 100
        ]);
        exit;
    } else {
        // Erro retornado pela Appmax
        $err_msg = $cc_res['data']['data']['message'] 
            ?? ($cc_res['data']['message'] 
            ?? ($cc_res['data']['error'] 
            ?? 'Transação não autorizada pelo emissor do cartão. Revise os dados e tente novamente.'));

        log_activity("Appmax Cartão Recusado: {$err_msg} | Pedido ID {$order_id}", 'appmax_errors.log', __DIR__ . '/..');

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $err_msg,
            'correlationId' => $correlationID
        ]);
        exit;
    }
}
