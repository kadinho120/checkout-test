<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

// Basic query to fetch orders
// In a real app, we would add pagination and search here.
try {
    $query = "SELECT * FROM orders ORDER BY created_at DESC";
    $stmt = $db->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format if necessary (e.g. status labels are already in DB? Yes, pending/paid)
    echo json_encode($orders);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error fetching orders", "error" => $e->getMessage()]);
}
?>