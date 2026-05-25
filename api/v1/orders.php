<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

// Paginated query to fetch orders
try {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Get total count
    $totalCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalPages = ceil($totalCount / $limit);

    $query = "SELECT * FROM orders ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $orders,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'total_count' => (int)$totalCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error fetching orders", "error" => $e->getMessage()]);
}
?>