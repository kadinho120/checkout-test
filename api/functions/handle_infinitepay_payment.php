<?php
/**
 * api/functions/handle_infinitepay_payment.php
 * Processa a criação de links de pagamento na InfinitePay (Checkout Integrado).
 * Suporta PIX e Cartão de Crédito.
 */

function handle_infinitepay_payment($target_method = 'all')
{
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../connection.php';
    require_once __DIR__ . '/get_client_ip.php';
    require_once __DIR__ . '/log_activity.php';
    require_once __DIR__ . '/format_price.php';
    require_once __DIR__ . '/generate_random_phone.php';
    require_once __DIR__ . '/trigger_custom_webhooks.php';
    require_once __DIR__ . '/send_utmify_event.php';

    $json_params = file_get_contents('php://input');
    $params = json_decode($json_params, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($params['value'], $params['correlation_id'], $params['customer'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Dados inválidos. Valor, correlation_id e customer são obrigatórios.']));
    }

    if (!defined('INFINITEPAY_API_KEY') || empty(INFINITEPAY_API_KEY)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Chave de API da InfinitePay não configurada.']));
    }

    $client_ip = get_client_ip();
    $correlationID = trim($params['correlation_id']);
    $value_in_cents = (int) round($params['value'] ?? 0);

    if ($value_in_cents <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Valor do pedido inválido.']));
    }

    // 1. Tratamento de Nome
    $customer_name = trim($params['customer']['name'] ?? '');
    if (empty($customer_name)) {
        $customer_name = 'Cliente #' . strtoupper(substr($correlationID, -4));
    }

    // 2. Tratamento de E-mail
    $customer_email = trim($params['customer']['email'] ?? '');
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $customer_email = 'cliente_' . substr($correlationID, -8) . '@cliente.com';
    }

    // 3. Tratamento de Telefone (formato E.164 exigido pela InfinitePay: +5511999887766)
    $raw_phone = preg_replace('/[^0-9]/', '', $params['customer']['phone'] ?? '');
    if (empty($raw_phone)) {
        $raw_phone = preg_replace('/[^0-9]/', '', generate_random_phone());
    }
    if (str_starts_with($raw_phone, '55') && strlen($raw_phone) >= 12) {
        $phone_e164 = '+' . $raw_phone;
    } else {
        $clean_without_zero = ltrim($raw_phone, '0');
        $phone_e164 = '+55' . $clean_without_zero;
    }

    // 4. Montagem dos Itens do Pedido (valores em centavos)
    $items = [];
    if (isset($params['products']) && is_array($params['products']) && !empty($params['products'])) {
        foreach ($params['products'] as $idx => $p) {
            $p_price_cents = (int) round(($p['price'] ?? 0) * 100);
            if ($p_price_cents <= 0) {
                $p_price_cents = $value_in_cents;
            }
            $qty = max(1, (int) ($p['qty'] ?? 1));
            $items[] = [
                'quantity' => $qty,
                'price' => $p_price_cents,
                'description' => !empty($p['name']) ? mb_substr(trim($p['name']), 0, 100) : ('Item ' . ($idx + 1))
            ];
        }
    }

    if (empty($items)) {
        $items[] = [
            'quantity' => 1,
            'price' => $value_in_cents,
            'description' => 'Acesso ao Produto'
        ];
    } else {
        // Ajusta arredondamento na soma dos itens para coincidir exatamente com o valor total
        $sum_cents = 0;
        foreach ($items as $it) {
            $sum_cents += $it['price'] * $it['quantity'];
        }
        if ($sum_cents !== $value_in_cents) {
            $items[0]['price'] += ($value_in_cents - $sum_cents);
        }
    }

    // 5. Determina as Formas de Pagamento Permitidas
    $payment_method_saved = 'all';
    $methods_enabled = ['credit_card', 'pix'];

    if ($target_method === 'pix') {
        $methods_enabled = ['pix'];
        $payment_method_saved = 'pix';
    } elseif ($target_method === 'credit_card' || $target_method === 'card') {
        $methods_enabled = ['credit_card'];
        $payment_method_saved = 'credit_card';
    } elseif (isset($params['payment_method'])) {
        $pm = strtolower(trim($params['payment_method']));
        if ($pm === 'pix') {
            $methods_enabled = ['pix'];
            $payment_method_saved = 'pix';
        } elseif ($pm === 'credit_card' || $pm === 'card') {
            $methods_enabled = ['credit_card'];
            $payment_method_saved = 'credit_card';
        }
    }

    // 6. Base URL para Redirect e Webhook
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = "{$protocol}://{$host}";

    $product_slug = $params['products'][0]['sku'] ?? '';
    $redirect_url = $params['redirect_url'] ?? "{$base_url}/checkout.php?status=success&order_nsu={$correlationID}" . (!empty($product_slug) ? "&product={$product_slug}" : "");
    $webhook_url = (defined('INFINITEPAY_WEBHOOK_URL') && !empty(INFINITEPAY_WEBHOOK_URL))
        ? INFINITEPAY_WEBHOOK_URL
        : "{$base_url}/api/webhook-infinitepay.php";

    // 7. Montagem do Payload para a InfinitePay
    $infinite_payload = [
        'order_nsu' => $correlationID,
        'items' => $items,
        'customer' => [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone_number' => $phone_e164
        ],
        'redirect_url' => $redirect_url,
        'webhook_url' => $webhook_url,
        'options' => [
            'single_payment' => true,
            'payment_methods_enabled' => $methods_enabled
        ]
    ];

    // Endereço de Entrega (se preenchido)
    $address_postcode = preg_replace('/[^0-9]/', '', $params['customer']['cep'] ?? '');
    $address_street = trim($params['customer']['address'] ?? '');
    if (!empty($address_postcode) && !empty($address_street)) {
        $infinite_payload['address'] = [
            'cep' => $address_postcode,
            'street' => $address_street,
            'neighborhood' => trim($params['customer']['neighborhood'] ?? 'Centro'),
            'number' => trim($params['customer']['address_number'] ?? 'S/N'),
            'complement' => trim($params['customer']['complement'] ?? '')
        ];
    }

    // 8. Envio da Requisição para API InfinitePay
    $api_url = 'https://api.checkout.infinitepay.io/links';
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($infinite_payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Api-Key: ' . INFINITEPAY_API_KEY
    ]);

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        log_activity("InfinitePay Curl Error: {$curl_err}", 'infinitepay_errors.log', __DIR__ . '/..');
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Erro de conexão com a InfinitePay: ' . $curl_err]));
    }

    $res_data = json_decode($response_body, true);

    if ($http_code < 200 || $http_code >= 300 || empty($res_data['url'])) {
        $err_msg = $res_data['message'] ?? ($res_data['error'] ?? 'Não foi possível gerar o link de pagamento na InfinitePay.');
        log_activity("InfinitePay Error: HTTP {$http_code} | Msg: {$err_msg} | Body: {$response_body}", 'infinitepay_errors.log', __DIR__ . '/..');
        http_response_code($http_code >= 400 ? $http_code : 400);
        die(json_encode(['success' => false, 'message' => 'InfinitePay: ' . $err_msg]));
    }

    $checkout_url = $res_data['url'];
    $slug = basename(parse_url($checkout_url, PHP_URL_PATH));

    // 9. Gravação do Pedido no Banco de Dados SQLite
    $order_id = null;
    $externalID = !empty($params['customer']['external_id']) ? (string) $params['customer']['external_id'] : '';

    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("INSERT INTO orders (
            product_id, customer_name, customer_email, customer_phone, customer_cpf, 
            total_amount, status, payment_method, gateway, transaction_id, external_id, 
            cep, address, address_number, complement, neighborhood, city, state, 
            json_data, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '-03:00'))");

        $json_data_store = json_encode([
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'gateway' => 'infinitepay',
            'payment_method' => $payment_method_saved,
            'checkout_url' => $checkout_url,
            'slug' => $slug,
            'products' => $params['products'] ?? [],
            'tracking' => array_merge($params['tracking'] ?? [], ['client_ip' => $client_ip]),
            'infinitepay_response' => $res_data
        ]);

        $mainProductId = 0;
        if (isset($params['products'][0]['id'])) {
            $mainProductId = (int) $params['products'][0]['id'];
        }

        $stmt->execute([
            $mainProductId,
            $customer_name,
            $customer_email,
            $phone_e164,
            $params['customer']['document'] ?? '',
            $value_in_cents / 100,
            'pending',
            $payment_method_saved,
            'infinitepay',
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

        // Disparo de Webhook Customizado para pedido criado
        trigger_custom_webhooks('order.created', $order_id);

        // UTMIFY - Evento Pendente
        $full_webhook_payload = [
            'id' => $order_id,
            'correlation_id' => $correlationID,
            'external_id' => $externalID,
            'gateway' => 'infinitepay',
            'status' => 'pending',
            'value' => $value_in_cents,
            'value_formatted' => (float) ($value_in_cents / 100),
            'created_at' => date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $customer_name,
                'email' => $customer_email,
                'phone' => $phone_e164,
                'document' => $params['customer']['document'] ?? '',
                'external_id' => $externalID
            ],
            'products' => $params['products'] ?? [],
            'tracking' => $params['tracking'] ?? []
        ];

        sendUtmifyEvent($full_webhook_payload, 'pending');

    } catch (Exception $e) {
        log_activity("Erro ao gravar pedido InfinitePay no SQLite: " . $e->getMessage(), 'infinitepay_errors.log', __DIR__ . '/..');
    }

    log_activity("InfinitePay Link Gerado: {$checkout_url} | Pedido #{$order_id} | NSU: {$correlationID} | Método: {$payment_method_saved}", 'infinitepay.log', __DIR__ . '/..');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkout_url,
        'url' => $checkout_url,
        'redirect_url' => $checkout_url,
        'correlationId' => $correlationID,
        'order_id' => $order_id,
        'slug' => $slug,
        'payment_method' => $payment_method_saved
    ]);
    exit;
}
