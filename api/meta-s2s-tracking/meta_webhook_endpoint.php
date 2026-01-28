<?php
/**
 * Endpoint para buscar dados de correlação da Meta (SQLite) e enviá-los para n8n.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../connection.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['correlationId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CorrelationId obrigatório.']);
    exit;
}

$correlationId = $data['correlationId'];

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM tracking_logs WHERE correlation_id = ?");
    $stmt->execute([$correlationId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        $payload = json_decode($log['json_payload'], true) ?? [];

        // Merge main fields
        $outputData = array_merge($payload, [
            'correlation_id' => $log['correlation_id'],
            'fbc' => $log['fbc'],
            'fbp' => $log['fbp'],
            'client_user_agent' => $log['user_agent'],
            'event_source_url' => $log['event_url'],
            'pixel_id' => $log['pixel_id']
        ]);

        // Logic to extract fbclid
        if (!empty($log['fbc'])) {
            $parts = explode('.', $log['fbc']);
            $fbclid = end($parts);
            if ($fbclid) {
                $outputData['fbclid'] = $fbclid;
            }
        }

        // Send to N8N
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/meta-s2s-tracking-ebook';

        $ch = curl_init($n8n_webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($outputData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            echo json_encode(['success' => true, 'message' => 'Enviado para N8N.']);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Erro N8N.', 'code' => $http_code]);
        }

    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Correlation ID não encontrado no banco.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno BD.']);
}
?>