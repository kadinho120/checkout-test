<?php
/**
 * api/functions/handle_appmax_pix_payment.php
 * Função principal para processar o pagamento PIX da Appmax.
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

    // Busca a chave/token da API definida no config.php
    $api_token = defined('APPMAX_API_TOKEN') && !empty(APPMAX_API_TOKEN) ? APPMAX_API_TOKEN : (getenv('APPMAX_API_TOKEN') ?: '');
    if (empty($api_token)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Erro de configuração: APPMAX_API_TOKEN não definida.']));
    }

    $correlationID = $params['correlation_id'];

    // Tratamento de Nome
    $customer_name = trim($params['customer']['name'] ?? '');
    if (empty($customer_name)) {
        $customer_name = 'Cliente #' . strtoupper(substr($correlationID, -4));
        $params['customer']['name'] = $customer_name;
    }

    $name_parts = preg_split('/\s+/', $customer_name);
    $first_name = $name_parts[0] ?? 'Cliente';
    $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : 'Appmax';

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

    // Tratamento de Documento / CPF
    $raw_document = preg_replace('/[^0-9]/', '', $params['customer']['document'] ?? '');
    if (strlen($raw_document) !== 11 && strlen($raw_document) !== 14) {
        $raw_document = preg_replace('/[^0-9]/', '', generate_valid_cpf());
    }

    // --- LÓGICA DE PRODUTOS & VALOR ---
    $value_in_cents = (int) round($params['value'] ?? 0);
    $product_name = 'Acesso ao Produto';
    $product_sku = 'PROD-' . substr($correlationID, -6);

    if (isset($params['products']) && is_array($params['products']) && !empty($params['products'])) {
        $first_prod = $params['products'][0];
        if (!empty($first_prod['name'])) {
            $product_name = $first_prod['name'];
        }
        if (!empty($first_prod['id'])) {
            $product_sku = 'SKU-' . $first_prod['id'];
        }
    }

    $address_postcode = preg_replace('/[^0-9]/', '', $params['customer']['cep'] ?? '01310100');
    $address_street = !empty($params['customer']['address']) ? $params['customer']['address'] : 'Av. Paulista';
    $address_number = !empty($params['customer']['address_number']) ? $params['customer']['address_number'] : '1000';
    $address_complement = $params['customer']['complement'] ?? '';
    $address_neighborhood = !empty($params['customer']['neighborhood']) ? $params['customer']['neighborhood'] : 'Bela Vista';
    $address_city = !empty($params['customer']['city']) ? $params['customer']['city'] : 'São Paulo';
    $address_state = !empty($params['customer']['state']) ? strtoupper($params['customer']['state']) : 'SP';

    // ========================================================
    // REQUISIÇÃO À API DA APPMAX
    // Tentativa 1: API V1 REST / Unified Order ou Payments Pix
    // Tentativa 2: API V3 Classic (admin.appmax.com.br/api/v3)
    // ========================================================

    $br_code = null;
    $qr_code_image = null;
    $appmax_order_id = null;
    $appmax_error = null;

    // 1. Tentar Endpoint V1 Unified Order
    $v1_unified_payload = [
        'customer' => [
            'name' => $customer_name,
            'email' => $customer_email,
            'document_number' => $raw_document,
            'phones' => [
                'mobile_phone' => [
                    'area_code' => $area_code,
                    'number' => $phone_number
                ]
            ],
            'address' => [
                'postcode' => $address_postcode,
                'street' => $address_street,
                'number' => $address_number,
                'complement' => $address_complement,
                'district' => $address_neighborhood,
                'city' => $address_city,
                'state' => $address_state
            ],
            'ip' => !empty($client_ip) ? $client_ip : '127.0.0.1'
        ],
        'items' => [
            [
                'sku' => $product_sku,
                'description' => $product_name,
                'amount' => $value_in_cents,
                'quantity' => 1
            ]
        ],
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

    $v1_response = appmax_request(
        'https://api.appmax.com.br/v1/orders/unified-order',
        'POST',
        $v1_unified_payload,
        ['Authorization: Bearer ' . $api_token]
    );

    if ($v1_response['http_code'] >= 200 && $v1_response['http_code'] < 300 && !empty($v1_response['data'])) {
        $resData = $v1_response['data']['data'] ?? $v1_response['data'];
        
        $appmax_order_id = $resData['order']['id'] ?? ($resData['id'] ?? null);
        
        $pixObj = $resData['pix'] ?? ($resData['payment']['pix'] ?? ($resData['payments'][0]['pix'] ?? null));
        if ($pixObj) {
            $br_code = $pixObj['emv_code'] ?? ($pixObj['pix_emv'] ?? ($pixObj['brCode'] ?? ($pixObj['payload'] ?? null)));
            $qr_code_image = $pixObj['qr_code'] ?? ($pixObj['pix_qrcode'] ?? ($pixObj['qrCodeImage'] ?? null));
        }
    } else {
        $appmax_error = $v1_response['data']['message'] ?? ($v1_response['data']['error'] ?? $v1_response['error']);
        log_activity('Appmax V1 unified order error: ' . json_encode($v1_response), 'appmax_errors.log', __DIR__ . '/..');
    }

    // 2. Se a V1 não retornou o Pix, tentar o fluxo V3 Classic (com "access-token")
    if (empty($br_code)) {
        // Passo A: Criar/Atualizar Cliente V3
        $v3_cust_payload = [
            'access-token' => $api_token,
            'firstname' => $first_name,
            'lastname' => $last_name,
            'email' => $customer_email,
            'telephone' => $area_code . $phone_number,
            'postcode' => $address_postcode,
            'address' => $address_street,
            'number' => $address_number,
            'complement' => $address_complement,
            'district' => $address_neighborhood,
            'city' => $address_city,
            'state' => $address_state,
            'ip' => !empty($client_ip) ? $client_ip : '127.0.0.1'
        ];

        $v3_cust_res = appmax_request('https://admin.appmax.com.br/api/v3/customer', 'POST', $v3_cust_payload);

        $v3_cust_id = $v3_cust_res['data']['data']['id'] ?? ($v3_cust_res['data']['id'] ?? null);

        if ($v3_cust_id) {
            // Passo B: Criar Pedido V3
            $v3_order_payload = [
                'access-token' => $api_token,
                'customer_id' => $v3_cust_id,
                'products' => [
                    [
                        'sku' => $product_sku,
                        'name' => $product_name,
                        'qty' => 1,
                        'price' => (float) ($value_in_cents / 100)
                    ]
                ]
            ];

            $v3_order_res = appmax_request('https://admin.appmax.com.br/api/v3/order', 'POST', $v3_order_payload);
            $v3_order_id = $v3_order_res['data']['data']['id'] ?? ($v3_order_res['data']['id'] ?? null);

            if ($v3_order_id) {
                $appmax_order_id = $v3_order_id;
                // Passo C: Gerar Pagamento Pix V3
                $v3_pix_payload = [
                    'access-token' => $api_token,
                    'order_id' => $v3_order_id,
                    'customer_id' => $v3_cust_id,
                    'payment' => [
                        'pix' => [
                            'document_number' => $raw_document
                        ]
                    ]
                ];

                $v3_pix_res = appmax_request('https://admin.appmax.com.br/api/v3/payment/pix', 'POST', $v3_pix_payload);

                $pixDataV3 = $v3_pix_res['data']['data'] ?? ($v3_pix_res['data'] ?? []);
                $br_code = $pixDataV3['pix_emv'] ?? ($pixDataV3['emv_code'] ?? ($pixDataV3['brCode'] ?? null));
                $qr_code_image = $pixDataV3['pix_qrcode'] ?? ($pixDataV3['qr_code'] ?? null);
            } else {
                log_activity('Appmax V3 order error: ' . json_encode($v3_order_res), 'appmax_errors.log', __DIR__ . '/..');
            }
        } else {
            log_activity('Appmax V3 customer error: ' . json_encode($v3_cust_res), 'appmax_errors.log', __DIR__ . '/..');
        }
    }

    // Se após as tentativas ainda não houver código Pix, retornar erro detalhado
    if (empty($br_code)) {
        http_response_code(500);
        $err_msg = !empty($appmax_error) ? $appmax_error : 'Falha ao gerar o Pix na Appmax. Verifique o token e as credenciais.';
        log_activity("Appmax PIX Generation Failed: {$err_msg}", 'appmax_errors.log', __DIR__ . '/..');
        die(json_encode(['success' => false, 'message' => 'Gateway Appmax: ' . $err_msg]));
    }

    // Normalização da imagem do QR Code
    if (!empty($qr_code_image)) {
        if (!str_starts_with($qr_code_image, 'http') && !str_starts_with($qr_code_image, 'data:')) {
            $qr_code_image = 'data:image/png;base64,' . $qr_code_image;
        }
    } else {
        // Fallback para renderização do QR Code a partir do código EMV (Google Chart API / QuickChart)
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

        $externalID = $params['customer']['external_id'] ?? ($appmax_order_id ? (string)$appmax_order_id : '');

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

        // Webhook N8N (Legado/Fixo)
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-gerado-abacatepay';

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
        require_once __DIR__ . '/send_utmify_event.php';
        sendUtmifyEvent($full_webhook_payload, 'pending');

    } catch (Exception $e) {
        error_log('Erro interno ao salvar pedido Appmax SQLite: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'pixData' => $pix_data, 'correlationId' => $correlationID]);
}
