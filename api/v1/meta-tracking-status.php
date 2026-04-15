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

    // Busca apenas pedidos pagos (que deveriam ter disparado CAPI)
    $stmt = $db->query("
        SELECT id, customer_name, customer_email, total_amount, meta_purchase_status, meta_purchase_log, updated_at, created_at 
        FROM orders 
        WHERE status = 'paid' 
        ORDER BY id DESC 
        LIMIT 100
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
