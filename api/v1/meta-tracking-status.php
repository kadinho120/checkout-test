<?php
/**
 * API para buscar status de rastreamento do Meta Ads (CAPI)
 * Local: api/v1/meta-tracking-status.php
 */

session_start();

// Verifica autenticação
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['error' => 'Não autorizado']));
}

require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Busca apenas pedidos pagos (que deveriam ter disparado CAPI) - Paginado
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Get total count
    $totalCount = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn();
    $totalPages = ceil($totalCount / $limit);

    // Get stats totals (independent of pagination)
    $successCount = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND meta_purchase_status = 1")->fetchColumn();
    $errorCount = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND meta_purchase_status = 2")->fetchColumn();
    $pendingCount = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND meta_purchase_status = 0")->fetchColumn();

    $stmt = $db->prepare("
        SELECT id, customer_name, customer_email, total_amount, meta_purchase_status, meta_purchase_log, updated_at, created_at 
        FROM orders 
        WHERE status = 'paid' 
        ORDER BY id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $results,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'total_count' => (int)$totalCount,
        'stats' => [
            'success' => (int)$successCount,
            'error' => (int)$errorCount,
            'pending' => (int)$pendingCount
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
