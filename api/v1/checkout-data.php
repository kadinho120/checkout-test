<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$slug = $_GET['slug'] ?? '';
$bumpParam = $_GET['bump'] ?? ($_GET['bump_id'] ?? '');

$isBumpCheckout = false;
$product = null;

if (!empty($bumpParam)) {
    $bumpId = (int)$bumpParam;
    if ($bumpId > 0) {
        $bumpStmt = $db->prepare("SELECT * FROM order_bumps WHERE id = ?");
        $bumpStmt->execute([$bumpId]);
        $bump = $bumpStmt->fetch();
        if ($bump) {
            $parentStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $parentStmt->execute([$bump['product_id']]);
            $parentProduct = $parentStmt->fetch();
            if ($parentProduct) {
                $isBumpCheckout = true;
                $product = $parentProduct;
                $product['name'] = $bump['title'];
                $product['price'] = $bump['price'];
                if (!empty($bump['description'])) {
                    $product['description'] = $bump['description'];
                }
                if (!empty($bump['image_url'])) {
                    $product['image_url'] = $bump['image_url'];
                }
                $product['compare_at_price'] = null;
                $product['downsell_enabled'] = 0;
                $product['bumps'] = [];
            }
        }
    }
}

if (!$isBumpCheckout) {
    if (empty($slug)) {
        http_response_code(400);
        echo json_encode(["message" => "Slug or bump required."]);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM products WHERE slug = ? AND active = 1");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
}

if ($product) {
    // Fetch Active Bumps (apenas se não for checkout exclusivo de bump)
    if (!$isBumpCheckout) {
        $bumpStmt = $db->prepare("SELECT id, title, description, price, image_url FROM order_bumps WHERE product_id = ? AND active = 1");
        $bumpStmt->execute([$product['id']]);
        $product['bumps'] = $bumpStmt->fetchAll();
    }

    // Fetch Active Pixels
    $pixelStmt = $db->prepare("SELECT type, pixel_id, token FROM pixels WHERE product_id = ? AND active = 1");
    $pixelStmt->execute([$isBumpCheckout ? $bump['product_id'] : $product['id']]);
    $product['pixels'] = $pixelStmt->fetchAll();

    // Remove sensitive info if any (none currently, but good practice)
    echo json_encode($product);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Product not found."]);
}
