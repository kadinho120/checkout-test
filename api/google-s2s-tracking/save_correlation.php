<?php
// /google-s2s-tracking/save_correlation.php

/**
 * Salva a correlação da TENTATIVA de conversão do Google Ads.
 * Este script é chamado pelo frontend (index.html) antes do pagamento.
 */

header('Content-Type: application/json');

// Função de log simples para o contexto Google
function log_google_activity($message) {
    $logFile = __DIR__ . '/google_activity.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

log_google_activity("SAVE GOOGLE: Requisição recebida.");

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['correlation_id'])) {
    log_google_activity("SAVE GOOGLE: Erro - Payload JSON inválido ou correlation_id ausente.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido ou correlation_id é obrigatório.']);
    exit;
}

$correlationId = $data['correlation_id'];
$filePath = __DIR__ . '/google_correlation_data.json';

try {
    $file_handle = fopen($filePath, 'c+');
    if (!$file_handle) throw new Exception('Não foi possível abrir o arquivo de correlação do Google.');
    if (!flock($file_handle, LOCK_EX)) throw new Exception('Não foi possível obter o lock do arquivo.');

    $fileContent = stream_get_contents($file_handle);
    $currentData = !empty($fileContent) ? json_decode($fileContent, true) : [];
    if (!is_array($currentData)) {
        log_google_activity("SAVE GOOGLE: Alerta - Arquivo google_correlation_data.json corrompido, iniciando do zero.");
        $currentData = [];
    }
    
    // Salva os dados relevantes para o Google Ads (S2S)
    $currentData[$correlationId] = [
        'gclid' => $data['gclid'] ?? '', // Google Click ID
        'wbraid' => $data['wbraid'] ?? '', // iOS14+ Web
        'gbraid' => $data['gbraid'] ?? '', // iOS14+ App
        'conversion_id' => $data['conversion_id'] ?? '',
        'conversion_label' => $data['conversion_label'] ?? '',
        'value' => $data['value'] ?? 0,
        'currency' => $data['currency'] ?? 'BRL',
        'user_agent' => $data['client_user_agent'] ?? 'N/A',
        'event_source_url' => $data['event_source_url'] ?? '',
        'timestamp' => time(),
        'confirmed' => false
    ];

    rewind($file_handle);
    ftruncate($file_handle, 0);
    fwrite($file_handle, json_encode($currentData, JSON_PRETTY_PRINT));
    
    flock($file_handle, LOCK_UN);
    fclose($file_handle);

    log_google_activity("SAVE GOOGLE: Dados salvos para correlation_id: " . $correlationId);
    echo json_encode(['success' => true, 'message' => 'Dados de correlação do Google salvos.']);

} catch (Exception $e) {
    if (isset($file_handle) && is_resource($file_handle)) {
        flock($file_handle, LOCK_UN);
        fclose($file_handle);
    }
    log_google_activity("SAVE GOOGLE: Erro Crítico - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>