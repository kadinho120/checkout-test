<?php
/**
 * Envia evento de pedido para a UTMIFY
 */
function sendUtmifyEvent($orderData, $eventType)
{
    // Configuration from config.php
    $apiToken = UTMIFY_API_TOKEN;
    $endpoint = 'https://api.utmify.com.br/api-credentials/orders';

    // Map internal status to UTMIFY status
    $utmifyStatus = 'waiting_payment';
    if ($eventType === 'paid' || (isset($orderData['status']) && $orderData['status'] === 'paid')) {
        $utmifyStatus = 'paid';
    } elseif (isset($orderData['status']) && $orderData['status'] === 'failed') {
        $utmifyStatus = 'refused';
    }

    $now = gmdate('Y-m-d H:i:s');
    $approvedDate = ($utmifyStatus === 'paid') ? $now : null;

    // Tracking Params Extraction
    $tracking = $orderData['tracking'] ?? [];

    // Product Mapping
    $products = [];
    if (!empty($orderData['products'])) {
        foreach ($orderData['products'] as $p) {
            $products[] = [
                'id' => $p['sku'] ?? 'UNK-' . rand(1000, 9999),
                'name' => $p['name'] ?? 'Produto',
                'planId' => null,
                'planName' => null,
                'quantity' => (int) ($p['qty'] ?? 1),
                'priceInCents' => (int) (($p['price'] ?? 0) * 100)
            ];
        }
    } else {
        $products[] = [
            'id' => 'DEFAULT',
            'name' => 'Produto Padrão',
            'planId' => null,
            'planName' => null,
            'quantity' => 1,
            'priceInCents' => (int) ($orderData['value'] ?? 0)
        ];
    }

    $totalCents = (int) ($orderData['value'] ?? 0);
    $gatewayFee = (int) ($totalCents * 0.01) + 100;
    $userCommission = $totalCents - $gatewayFee;

    $payload = [
        'orderId' => $orderData['correlation_id'] ?? uniqid(),
        'platform' => 'CheckoutAlpha',
        'paymentMethod' => 'pix',
        'status' => $utmifyStatus,
        'createdAt' => $now,
        'approvedDate' => $approvedDate,
        'refundedAt' => null,
        'customer' => [
            'name' => $orderData['customer']['name'] ?? 'Cliente',
            'email' => $orderData['customer']['email'] ?? 'email@teste.com',
            'phone' => preg_replace('/[^0-9]/', '', $orderData['customer']['phone'] ?? ''),
            'document' => preg_replace('/[^0-9]/', '', $orderData['customer']['document'] ?? ''),
            'country' => 'BR',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ],
        'products' => $products,
        'trackingParameters' => [
            'src' => $tracking['src'] ?? null,
            'sck' => $tracking['sck'] ?? null,
            'utm_source' => $tracking['utm_source'] ?? null,
            'utm_campaign' => $tracking['utm_campaign'] ?? null,
            'utm_medium' => $tracking['utm_medium'] ?? null,
            'utm_content' => $tracking['utm_content'] ?? null,
            'utm_term' => $tracking['utm_term'] ?? null,
            'fbclid' => $tracking['fbclid'] ?? null
        ],
        'commission' => [
            'totalPriceInCents' => $totalCents,
            'gatewayFeeInCents' => $gatewayFee,
            'userCommissionInCents' => $userCommission
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-token: ' . $apiToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'response' => $response
    ];
}
