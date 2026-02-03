<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../connection.php';
require_once '../evolution-helper.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->order_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Order ID required"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch order
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$data->order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    $storedData = json_decode($order['json_data'], true) ?? [];
    $products = $storedData['products'] ?? [];

    if (empty($products)) {
        echo json_encode(["success" => false, "message" => "No products found in this order"]);
        exit;
    }

    // Prepare Customer Data for Shortcodes
    $customerData = [
        'name' => $order['customer_name'],
        'email' => $order['customer_email'],
        'phone' => $order['customer_phone'],
        'document' => $order['customer_cpf']
    ];

    // Process Deliverables
    $result = processOrderDeliverables($products, $customerData, $db);

    echo json_encode([
        "success" => true,
        "message" => "Process completed",
        "details" => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
?>