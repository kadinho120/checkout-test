<?php
// /taboola-s2s-tracking/save_correlation.php

/**
 * Salva a correlação da TENTATIVA de conversão do Taboola.
 * Este script é chamado pelo frontend (index.html) antes do pagamento.
 */

header('Content-Type: application/json');

// Função de log específica para Taboola
function log_taboola_activity($message) {
    $logFile = __DIR__ . '/taboola_activity.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

log_taboola_activity("SAVE TABOOLA: Requisição recebida.");

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data['correlation_id'])) {
    log_taboola_activity("SAVE TABOOLA: Erro - Payload JSON inválido ou correlation_id ausente.");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido ou correlation_id é obrigatório.']);
    exit;
}

$correlationId = $data['correlation_id'];
$filePath = __DIR__ . '/taboola_correlation_data.json';

try {
    $file_handle = fopen($filePath, 'c+');
    if (!$file_handle) throw new Exception('Não foi possível abrir o arquivo de correlação do Taboola.');
    if (!flock($file_handle, LOCK_EX)) throw new Exception('Não foi possível obter o lock do arquivo.');

    $fileContent = stream_get_contents($file_handle);
    $currentData = !empty($fileContent) ? json_decode($fileContent, true) : [];
    if (!is_array($currentData)) {
        log_taboola_activity("SAVE TABOOLA: Alerta - Arquivo taboola_correlation_data.json corrompido ou vazio, iniciando array.");
        $currentData = [];
    }
    
    // Salva os dados relevantes para o Taboola
    // O frontend envia 'click_id' (que é o tblci) e 'pixel_id'
    $currentData[$correlationId] = [
        'click_id' => $data['click_id'] ?? '', // O parâmetro tblci
        'pixel_id' => $data['pixel_id'] ?? '',
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

    log_taboola_activity("SAVE TABOOLA: Dados salvos para correlation_id: " . $correlationId);
    echo json_encode(['success' => true, 'message' => 'Dados de correlação do Taboola salvos.']);

} catch (Exception $e) {
    if (isset($file_handle) && is_resource($file_handle)) {
        flock($file_handle, LOCK_UN);
        fclose($file_handle);
    }
    log_taboola_activity("SAVE TABOOLA: Erro Crítico - " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit();
?>