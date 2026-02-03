<?php
// api/v1/test-capi.php

header('Content-Type: application/json');
require_once __DIR__ . '/../meta-capi-helper.php';

// Auth Check (Optional but recommended, relying on session or just public for admin tool context)
// For simplicity in this tool context, we assume it's hit from admin.

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$pixelId = $input['pixel_id'] ?? '';
$token = $input['token'] ?? '';
$eventName = $input['event_name'] ?? 'InitiateCheckout';

if (empty($pixelId) || empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pixel ID and Token are required']);
    exit;
}

// Dummy Data for Test
$dummyData = [
    'correlation_id' => 'test_' . time(),
    'value' => 10000, // 100.00
    'customer' => [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '5511999999999',
    ],
    'products' => [
        [
            'sku' => 'TEST-001',
            'qty' => 1,
            'price' => 10000 // 100.00
        ]
    ]
];

$context = [
    'client_ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'source_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/test-capi'
];

try {
    $result = sendMetaEvent($pixelId, $token, $eventName, $dummyData, $context);

    // Parse response
    $metaResp = json_decode($result['response'], true);

    echo json_encode([
        'success' => $result['code'] >= 200 && $result['code'] < 300,
        'http_code' => $result['code'],
        'meta_response' => $metaResp,
        'sent_payload' => 'Hidden (Processed in Helper)'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
