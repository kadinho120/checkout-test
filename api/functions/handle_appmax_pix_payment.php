<?php
/**
 * api/functions/handle_appmax_pix_payment.php
 * Processa o pagamento PIX da Appmax utilizando a nova API V1 (unified-order).
 */

function handle_appmax_pix_payment()
{
    // Obtém os dados JSON enviados no corpo da requisição
    $json_params = file_get_contents('php://input');
    $params = json_decode($json_params, true);

    require_once __DIR__ . '/get_client_ip.php';
    require_once __DIR__ . '/log_activity.php';
    require_once __DIR__ . '/appmax_request.php';
    require_once __DIR__ . '/generate_valid_cpf.php';
    require_once __DIR__ . '/generate_random_phone.php';
    require_once __DIR__ . '/format_price.php';

    $client_ip = get_client_ip();

    // Validação básica
    if (json_last_error() !== JSON_ERROR_NONE || !isset($params['value'], $params['correlation_id'], $params['customer'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Dados inválidos. Valor, correlation_id e customer são obrigatórios.']));
    }

    $correlationID = $params['correlation_id'];

    // Tratamento de Documento / CPF (Obrigatório Appmax)
    $raw_document = preg_replace('/[^0-9]/', '', $params['customer']['document'] ?? '');
    if (!is_valid_cpf($raw_document) && strlen($raw_document) !== 14) {
        $raw_document = generate_valid_cpf();
        $params['customer']['document'] = $raw_document;
    }

    // Tratamento de Nome
    $customer_name = trim($params['customer']['name'] ?? '');
    if (empty($customer_name)) {
        $customer_name = 'Cliente #' . strtoupper(substr($correlationID, -4));
        $params['customer']['name'] = $customer_name;
    }

    // Tratamento de Telefone
    $raw_phone = $params['customer']['phone'] ?? '';
    $clean_phone = preg_replace('/[^0-9]/', '', $raw_phone);

    if (empty($clean_phone)) {
        $clean_phone = preg_replace('/[^0-9]/', '', generate_random_phone());
    }

    if (substr($clean_phone, 0, 2) === '55' && strlen($clean_phone) > 10) {
        $clean_phone = substr($clean_phone, 2);
    }

    $area_code = strlen($clean_phone) >= 10 ? substr($clean_phone, 0, 2) : '11';
    $phone_number = strlen($clean_phone) >= 10 ? substr($clean_phone, 2) : '999999999';
    $whatsapp_formatted = '55' . $area_code . $phone_number;

    // Tratamento de Email
    $customer_email = trim($params['customer']['email'] ?? '');
    if (empty($customer_email)) {
        $customer_email = 'cliente_' . $correlationID . '@naoinformado.com';
        $params['customer']['email'] = $customer_email;
    }

    // --- LÓGICA DE PRODUTOS & VALOR ---
    $value_in_cents = (int) round($params['value'] ?? 0);
    $product_name = 'Acesso ao Produto';
    $product_sku = 'PROD-' . substr($correlationID, -6);

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
            'description' => $product_name,
            'quantity' => 1,
            'code' => $product_sku
        ];
    } else {
        // Ajusta arredondamento na soma dos itens para coincidir exatamente com o total
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

    // ========================================================
    // REQUISIÇÃO À NOVA API DA APPMAX (Unified Order)
    // ========================================================

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
                'payment_method' => 'pix',
                'pix' => [
                    'document_number' => $raw_document
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

    $appmax_res = appmax_request('https://api.appmax.com.br/v1/orders/unified-order', 'POST', $unified_payload);

    $br_code = null;
    $qr_code_image = null;
    $appmax_order_id = null;
    $appmax_error = null;

    if ($appmax_res['http_code'] >= 200 && $appmax_res['http_code'] < 300 && !empty($appmax_res['data'])) {
        $resData = $appmax_res['data']['data'] ?? $appmax_res['data'];
        $appmax_order_id = $resData['order_id'] ?? ($resData['order']['id'] ?? ($resData['id'] ?? ($appmax_res['data']['order_id'] ?? ($appmax_res['data']['data']['order_id'] ?? null))));

        // 1. Tenta extrair diretamente de $resData['payment'] (formato padrão Appmax V1 unified-order)
        if (isset($resData['payment']) && is_array($resData['payment'])) {
            $payment = $resData['payment'];
            $br_code = $payment['pix_emv'] ?? ($payment['emv_code'] ?? ($payment['brCode'] ?? ($payment['pix']['pix_emv'] ?? ($payment['pix']['emv_code'] ?? ($payment['pix']['brCode'] ?? null)))));
            $qr_code_image = $payment['pix_qrcode'] ?? ($payment['qr_code'] ?? ($payment['qrcode_image'] ?? ($payment['pix']['pix_qrcode'] ?? ($payment['pix']['qr_code'] ?? null))));
        }

        // 2. Se não encontrou, tenta em $resData['payments'][0]
        if (empty($br_code) && isset($resData['payments'][0]) && is_array($resData['payments'][0])) {
            $payment0 = $resData['payments'][0];
            $br_code = $payment0['pix_emv'] ?? ($payment0['emv_code'] ?? ($payment0['brCode'] ?? ($payment0['pix']['pix_emv'] ?? ($payment0['pix']['emv_code'] ?? null))));
            $qr_code_image = $payment0['pix_qrcode'] ?? ($payment0['qr_code'] ?? ($payment0['pix']['pix_qrcode'] ?? ($payment0['pix']['qr_code'] ?? null)));
        }

        // 3. Se não encontrou, tenta em $resData['pix']
        if (empty($br_code) && isset($resData['pix']) && is_array($resData['pix'])) {
            $br_code = $resData['pix']['pix_emv'] ?? ($resData['pix']['emv_code'] ?? ($resData['pix']['brCode'] ?? null));
            $qr_code_image = $resData['pix']['pix_qrcode'] ?? ($resData['pix']['qr_code'] ?? null);
        }

        // 4. Se não encontrou, tenta no próprio $resData
        if (empty($br_code)) {
            $br_code = $resData['pix_emv'] ?? ($resData['emv_code'] ?? ($resData['brCode'] ?? null));
            $qr_code_image = $resData['pix_qrcode'] ?? ($resData['qr_code'] ?? null);
        }

        // 5. Varredura recursiva por padrão EMV PIX do Banco Central (inicia com 000201)
        if (empty($br_code)) {
            $find_emv = function ($item) use (&$find_emv) {
                if (is_string($item) && str_starts_with($item, '000201')) {
                    return $item;
                }
                if (is_array($item)) {
                    foreach ($item as $val) {
                        $found = $find_emv($val);
                        if ($found) return $found;
                    }
                }
                return null;
            };
            $br_code = $find_emv($resData);
        }

        // 6. Varredura recursiva para QR Code Image
        if (empty($qr_code_image)) {
            $find_qr = function ($item) use (&$find_qr) {
                if (is_string($item) && (str_starts_with($item, 'data:image') || str_starts_with($item, 'http://') || str_starts_with($item, 'https://')) && (stripos($item, 'qrcode') !== false || stripos($item, 'pix') !== false)) {
                    return $item;
                }
                if (is_array($item)) {
                    foreach ($item as $k => $val) {
                        if (is_string($val) && (stripos((string)$k, 'qrcode') !== false || stripos((string)$k, 'qr_code') !== false)) {
                            return $val;
                        }
                        $found = $find_qr($val);
                        if ($found) return $found;
                    }
                }
                return null;
            };
            $qr_code_image = $find_qr($resData);
        }
    } else {
        $appmax_error = $appmax_res['data']['message'] ?? ($appmax_res['data']['error'] ?? ($appmax_res['data']['data']['message'] ?? $appmax_res['error']));
        log_activity('Appmax V1 PIX error: ' . json_encode($appmax_res), 'appmax_errors.log', __DIR__ . '/..');
    }

    // Se a V1 não retornou o Pix, erro claro
    if (empty($br_code)) {
        http_response_code(500);
        $err_msg = !empty($appmax_error) ? $appmax_error : 'Falha ao gerar o Pix na Appmax. Verifique o CPF informado e tente novamente.';
        log_activity("Appmax PIX Generation Failed: {$err_msg}", 'appmax_errors.log', __DIR__ . '/..');
        die(json_encode(['success' => false, 'message' => 'Gateway Appmax: ' . $err_msg]));
    }

    // Normalização da imagem do QR Code
    if (!empty($qr_code_image)) {
        if (!str_starts_with($qr_code_image, 'http') && !str_starts_with($qr_code_image, 'data:')) {
            $qr_code_image = 'data:image/png;base64,' . $qr_code_image;
        }
    } else {
        // Fallback de renderização caso a API envie só o código EMV
        $qr_code_image = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($br_code);
    }

    $pix_data = [
        'brCode' => $br_code,
        'qrCodeImage' => $qr_code_image,
        'formattedPrice' => 'R$ ' . format_price(($params['value'] ?? 0) / 100)
    ];

    // --- SALVAMENTO NO BANCO SQLITE ---
    require_once __DIR__ . '/../connection.php';
    try {
        $database = new Database();
        $db = $database->getConnection();

        $externalID = !empty($appmax_order_id) ? (string)$appmax_order_id : (!empty($params['customer']['external_id']) ? (string)$params['customer']['external_id'] : '');

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
            'appmax_order_id' => $appmax_order_id,
            'pix_data' => $pix_data,
            'products' => $params['products'] ?? [],
            'tracking' => array_merge($params['tracking'] ?? [], ['client_ip' => $client_ip])
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
            $params['value'] / 100,
            'pending',
            'pix',
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

        // Disparo de Webhooks Customizados
        require_once __DIR__ . '/trigger_custom_webhooks.php';
        trigger_custom_webhooks('order.created', $order_id);

        $full_webhook_payload = [
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'gateway' => 'appmax',
            'status' => 'pending',
            'value' => $params['value'],
            'value_formatted' => (float) ($params['value'] / 100),
            'created_at' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $customer_name,
                'email' => $customer_email,
                'phone' => $whatsapp_formatted,
                'document' => $raw_document,
                'external_id' => $externalID
            ],
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? [],
            'fbclid' => $params['tracking']['fbclid'] ?? null,
            'pixel_id' => $params['tracking']['pixel_id'] ?? null,
            'pix_data' => $pix_data
        ];

        // UTMIFY
        require_once __DIR__ . '/send_utmify_event.php';
        sendUtmifyEvent($full_webhook_payload, 'pending');

    } catch (Exception $e) {
        log_activity('Erro interno ao salvar pedido Appmax SQLite: ' . $e->getMessage(), 'appmax_errors.log', __DIR__ . '/..');
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'pixData' => $pix_data, 'correlationId' => $correlationID]);
    exit;
}

