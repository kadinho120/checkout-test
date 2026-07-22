<?php
// api/v1/rotate-pix-keys.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../functions/rotate_pix_keys.php';

try {
    $force = isset($_GET['force']) && $_GET['force'] === '1';

    // Verificar se o intervalo mínimo de 30 minutos passou desde a última rotação (a menos que force=1)
    if (!$force) {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->query("
            SELECT pix_key, created_at,
                   (strftime('%s', 'now', '-03:00') - strftime('%s', created_at)) as seconds_since_last
            FROM pix_key_rotations 
            WHERE status = 'ACTIVE' AND is_default = 1
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $last_rotation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last_rotation) {
            $seconds_since = (int) ($last_rotation['seconds_since_last'] ?? 0);
            $min_seconds = 9 * 60; // Trava para garantir intervalo mínimo de ~10 minutos entre rotações

            if ($seconds_since < $min_seconds) {
                $remaining_seconds = $min_seconds - $seconds_since;
                echo json_encode([
                    'success' => true,
                    'executed' => false,
                    'message' => "A rotação ainda não é necessária. Faltam " . ceil($remaining_seconds / 60) . " minutos.",
                    'current_active_key' => $last_rotation['pix_key'],
                    'last_rotation_at' => $last_rotation['created_at']
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // Executar rotação
    $result = rotate_pix_keys();

    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(500);
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
