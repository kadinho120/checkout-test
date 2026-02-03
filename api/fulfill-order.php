<?php
// api/fulfill-order.php
// Endpoint para buscar um pedido, atualizar status para PAID e encaminhar para webhook.

header('Content-Type: application/json');
require_once __DIR__ . '/connection.php';

// Recebe o input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['correlationId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'correlationId ausente na requisição.']);
    exit;
}

$correlationId = $data['correlationId'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Busca o pedido pelo transaction_id (correlation_id)
    $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ?");
    $stmt->execute([$correlationId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {

        // 2. Atualiza o status se não estiver pago
        if ($order['status'] !== 'paid' && $order['status'] !== 'completed') {
            $updateStmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = datetime('now') WHERE id = ?");
            $updateStmt->execute([$order['id']]);
            $order['status'] = 'paid'; // Atualiza array local para envio
        }

        // 3. Prepara o payload para o N8N
        // O campo json_data no banco contém tracking, produtos, etc.
        $storedData = json_decode($order['json_data'], true) ?? [];

        $payloadForN8N = [
            'id' => $order['id'],
            'correlation_id' => $order['transaction_id'],
            'status' => 'paid',
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf']
            ],
            'amount' => (float) $order['total_amount'],
            'value_formatted' => (float) $order['total_amount'], // Ex: 9 ou 13.5
            'updated_at' => date('Y-m-d H:i:s'),
            'products' => $storedData['products'] ?? [],
            'tracking' => $storedData['tracking'] ?? [],
            'fbclid' => $storedData['tracking']['fbclid'] ?? null, // <--- STANDALONE FBCLID
            'pixel_id' => $storedData['tracking']['pixel_id'] ?? null,
            'pix_data' => $storedData['pix_data'] ?? []
        ];

        // 4. Envia para o N8N (Webhook de Pagamento Confirmado)
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-pago-abacatepay-envio';

        $ch = curl_init($n8n_webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadForN8N));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($payloadForN8N))
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // --- UTMIFY PAID HOOK ---
        require_once __DIR__ . '/utmify-helper.php';

        $utmifyOrderData = [
            'correlation_id' => $order['transaction_id'],
            'value' => (int) ($order['total_amount'] * 100), // Convert back to cents for helper
            'status' => 'paid',
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf']
            ],
            'products' => $storedData['products'] ?? [],
            'tracking' => $storedData['tracking'] ?? []
        ];

        sendUtmifyEvent($utmifyOrderData, 'paid');

        // --- META CAPI PURCHASE HOOK ---
        require_once __DIR__ . '/meta-capi-helper.php';

        // 1. Recover Pixel Token
        // Logic: Try to find token from DB for the main product in the order
        $capiToken = null;
        $capiPixelId = $storedData['tracking']['pixel_id'] ?? null;

        // If we don't have pixel_id or need token, fetch from DB
        try {
            // Retrieve first product SKU/ID from stored json
            $mainProductSku = $storedData['products'][0]['sku'] ?? null;
            if ($mainProductSku) {
                // Re-use DB connection from above
                $stmtPx = $db->prepare("
                    SELECT px.pixel_id, px.token 
                    FROM products p 
                    JOIN pixels px ON p.id = px.product_id 
                    WHERE p.slug = ? AND px.type = 'facebook' AND px.active = 1 
                    LIMIT 1
                ");
                $stmtPx->execute([$mainProductSku]);
                $pxData = $stmtPx->fetch(PDO::FETCH_ASSOC);
                if ($pxData) {
                    if (!$capiPixelId)
                        $capiPixelId = $pxData['pixel_id'];
                    $capiToken = $pxData['token'];
                }
            }
        } catch (Exception $e) { /* Ignore */
        }

        // 2. Send Purchase Event
        if ($capiPixelId && $capiToken) {
            $capiContext = [
                'client_ip' => $storedData['tracking']['client_ip'] ?? null,
                'user_agent' => $storedData['tracking']['user_agent'] ?? null,
                'fbp' => $storedData['tracking']['fbp'] ?? null,
                'fbc' => $storedData['tracking']['fbc'] ?? null,
                'source_url' => $storedData['tracking']['source_url'] ?? null
            ];

            sendMetaEvent(
                $capiPixelId,
                $capiToken,
                'Purchase',
                [ // Using formatted amount for value
                    'correlation_id' => $order['transaction_id'],
                    'value' => (int) ($order['total_amount'] * 100),
                    'customer' => [
                        'name' => $order['customer_name'],
                        'email' => $order['customer_email'],
                        'phone' => $order['customer_phone'],
                        'document' => $order['customer_cpf']
                    ],
                    'products' => $storedData['products'] ?? []
                ],
                $capiContext
            );
        }
        // -------------------------------

        // --- EVOLUTION API DELIVERABLE HOOK ---
        require_once __DIR__ . '/evolution-helper.php';

        try {
            // Prepare Customer Data for Helper
            $customerDataForHelper = [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'], // Already in $customerPhone variable too
                'document' => $order['customer_cpf']
            ];

            // Use the robust helper function that handles Bumps and Main Products correctly
            processOrderDeliverables($storedData['products'] ?? [], $customerDataForHelper, $db);

        } catch (Exception $e) { /* Silent fail for Deliverable */
            error_log("Deliverable Error: " . $e->getMessage());
        }
        // --------------------------------------

        // ------------------------

        if ($http_code >= 200 && $http_code < 300) {
            echo json_encode([
                'success' => true,
                'message' => 'Pedido atualizado e enviado ao n8n.',
                'data_sent' => $payloadForN8N
            ]);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Atualizado DB, mas erro no N8N.', 'n8n_code' => $http_code]);
        }

    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>