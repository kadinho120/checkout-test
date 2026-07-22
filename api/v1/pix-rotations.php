<?php
// api/v1/pix-rotations.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
// Opcional: verificar sessão do admin se necessário
if (!($_SESSION['logged_in'] ?? false)) {
    // Permite leitura se necessário ou valida sessão
}

require_once __DIR__ . '/../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    // Total de registros
    $stmt_count = $db->query("SELECT COUNT(*) as total FROM pix_key_rotations");
    $total_count = (int) $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = $total_count > 0 ? ceil($total_count / $limit) : 1;

    // Chave ativa atual
    $stmt_active = $db->query("
        SELECT pix_key, type, created_at 
        FROM pix_key_rotations 
        WHERE status = 'ACTIVE' AND is_default = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $current_active = $stmt_active->fetch(PDO::FETCH_ASSOC);

    // Estatísticas gerais
    $stmt_stats = $db->query("
        SELECT 
            COUNT(*) as total_rotations,
            SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'DELETED' THEN 1 ELSE 0 END) as deleted_count,
            MAX(created_at) as last_rotation_at
        FROM pix_key_rotations
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Registros paginados
    $stmt_data = $db->prepare("
        SELECT id, pix_key, type, is_default, status, created_at, deleted_at
        FROM pix_key_rotations
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt_data->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_data->execute();
    $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'page' => $page,
        'limit' => $limit,
        'total_count' => $total_count,
        'total_pages' => $total_pages,
        'current_active_key' => $current_active ?: null,
        'stats' => [
            'total_rotations' => (int)($stats['total_rotations'] ?? 0),
            'active_count' => (int)($stats['active_count'] ?? 0),
            'deleted_count' => (int)($stats['deleted_count'] ?? 0),
            'last_rotation_at' => $stats['last_rotation_at'] ?? null
        ],
        'data' => $rows
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
