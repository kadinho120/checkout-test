<?php
// api/utmify-helper.php

function sendUtmifyEvent($orderData, $eventType)
{
    // Configuration
    $apiToken = 'LBN9pcFeRqpdKyu6CYtlleSiQtc2TbjlUm1k';
    $endpoint = 'https://api.utmify.com.br/api-credentials/orders';

    // Map internal status to UTMIFY status
    // internal: 'pending', 'paid', 'failed'
    // utmify: 'waiting_payment', 'paid', 'refused', 'refunded'
    $utmifyStatus = 'waiting_payment';
    if ($eventType === 'paid' || $orderData['status'] === 'paid') {
        $utmifyStatus = 'paid';
    } elseif ($orderData['status'] === 'failed') {
        $utmifyStatus = 'refused';
    }

    // Dates (UTC Required by their docs usually, but commonly ISO8601)
    // Using current time for created/approved based on event
    $now = gmdate('Y-m-d H:i:s');
    $approvedDate = ($utmifyStatus === 'paid') ? $now : null;

    // Tracking Params Extraction
    $tracking = $orderData['tracking'] ?? [];

    // Product Mapping
    $products = [];
    if (!empty($orderData['products'])) {
        foreach ($orderData['products'] as $p) {
            $products[] = [
                'id' => $p['sku'] ?? 'UNK-' . rand(1000, 9999), // Fallback ID
                'name' => $p['name'] ?? 'Produto',
                'planId' => null,
                'planName' => null,
                'quantity' => (int) ($p['qty'] ?? 1),
                'priceInCents' => (int) (($p['price'] ?? 0) * 100)
            ];
        }
    } else {
        // Fallback if no products found (should not happen)
        $products[] = [
            'id' => 'DEFAULT',
            'name' => 'Produto Padrão',
            'planId' => null,
            'planName' => null,
            'quantity' => 1,
            'priceInCents' => (int) ($orderData['value'] ?? 0)
        ];
    }

    // Commission Calculation
    $totalCents = (int) ($orderData['value'] ?? 0); // already in cents usually or pass in cents
    // If value passed is float BRL (e.g. 10.00), convert. If int cents, keep.
    // In our process-pix logic, $params['value'] is cents.

    // Mock Gateway Fee (e.g. 1% + 1.00)
    $gatewayFee = (int) ($totalCents * 0.01) + 100;
    $userCommission = $totalCents - $gatewayFee;

    $payload = [
        'orderId' => $orderData['correlation_id'],
        'platform' => 'CheckoutAlpha',
        'paymentMethod' => 'pix', // We mostly handle pix here
        'status' => $utmifyStatus,
        'createdAt' => $now, // ideally should be original created date for updates, but for new events "now" is fine
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
            'utm_content' => $tracking['utm_content'] ?? null,
            'utm_term' => $tracking['utm_term'] ?? null,
            'fbclid' => $tracking['fbclid'] ?? null // Added explicitly per request
        ],
        'commission' => [
            'totalPriceInCents' => $totalCents,
            'gatewayFeeInCents' => $gatewayFee,
            'userCommissionInCents' => $userCommission
        ]
    ];

    // Send Request
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-token: ' . $apiToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Don't block too long

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Logging/Debugging
    error_log("UTMIFY Tracking ($utmifyStatus) [" . $httpCode . "]: " . ($err ? $err : $response));

    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'response' => $response
    ];
}
?>