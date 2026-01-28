<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['slug'])) {
    http_response_code(400);
    echo json_encode(["message" => "Slug required."]);
    exit;
}

$slug = $_GET['slug'];

$stmt = $db->prepare("SELECT * FROM products WHERE slug = ? AND active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if ($product) {
    // Fetch Active Bumps
    $bumpStmt = $db->prepare("SELECT id, title, description, price, image_url FROM order_bumps WHERE product_id = ? AND active = 1");
    $bumpStmt->execute([$product['id']]);
    $product['bumps'] = $bumpStmt->fetchAll();

    // Fetch Active Pixels
    $pixelStmt = $db->prepare("SELECT type, pixel_id, token FROM pixels WHERE product_id = ? AND active = 1");
    $pixelStmt->execute([$product['id']]);
    $product['pixels'] = $pixelStmt->fetchAll();

    // Remove sensitive info if any (none currently, but good practice)
    echo json_encode($product);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Product not found."]);
}
