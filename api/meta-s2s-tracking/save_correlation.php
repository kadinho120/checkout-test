<?php
// /meta-s2s-tracking/save_correlation.php

header('Content-Type: application/json');
require_once __DIR__ . '/../connection.php';

function log_meta_activity($message)
{
    $logFile = __DIR__ . '/meta_activity.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['correlation_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$correlationId = $data['correlation_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Ensure table exists (Quick fix for migration if schema didn't run yet)
    $db->exec("CREATE TABLE IF NOT EXISTS tracking_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        correlation_id TEXT NOT NULL UNIQUE,
        fbc TEXT,
        fbp TEXT,
        user_agent TEXT,
        event_url TEXT,
        pixel_id TEXT,
        json_payload TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $stmt = $db->prepare("INSERT OR REPLACE INTO tracking_logs (correlation_id, fbc, fbp, user_agent, event_url, pixel_id, json_payload) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Extra payload data
    $payloadData = [
        'value' => $data['value'] ?? 0,
        'currency' => $data['currency'] ?? 'BRL',
        'product_description' => $data['product_description'] ?? ''
    ];

    $stmt->execute([
        $correlationId,
        $data['fbc'] ?? '',
        $data['fbp'] ?? '',
        $data['client_user_agent'] ?? '',
        $data['event_source_url'] ?? '',
        $data['pixel_id'] ?? '',
        json_encode($payloadData)
    ]);

    log_meta_activity("SAVE: Dados salvos para ID: " . $correlationId);
    echo json_encode(['success' => true, 'message' => 'Dados de rastreamento salvos.']);

} catch (Exception $e) {
    log_meta_activity("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar dados.']);
}
?>