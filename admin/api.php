<?php
// admin/api.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

require_once __DIR__ . '/../api/connection.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT o.*, p.name as product_name FROM orders o LEFT JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map SQLite column names to what the JS expects
        $formattedOrders = array_map(function($order) {
            return [
                'id' => $order['id'],
                'correlationId' => $order['transaction_id'],
                'createdAt' => $order['created_at'],
                'customerName' => $order['customer_name'],
                'whatsapp' => $order['customer_phone'],
                'email' => $order['customer_email'],
                'productName' => $order['product_name'] ?: 'Produto #' . $order['product_id'],
                'value' => $order['total_amount'] * 100, // JS expects cents
                'status' => strtoupper($order['status']),
                'cep' => $order['cep'],
                'address' => $order['address'],
                'address_number' => $order['address_number'],
                'complement' => $order['complement'],
                'neighborhood' => $order['neighborhood'],
                'city' => $order['city'],
                'state' => $order['state']
            ];
        }, $orders);

        echo json_encode($formattedOrders);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
    }
}
