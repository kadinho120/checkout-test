<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get Single Product
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $product = $stmt->fetch();

            if ($product) {
                // Fetch Bumps
                $bumpStmt = $db->prepare("SELECT * FROM order_bumps WHERE product_id = ?");
                $bumpStmt->execute([$_GET['id']]);
                $product['bumps'] = $bumpStmt->fetchAll();

                // Fetch Pixels
                $pixelStmt = $db->prepare("SELECT * FROM pixels WHERE product_id = ?");
                $pixelStmt->execute([$_GET['id']]);
                $product['pixels'] = $pixelStmt->fetchAll();

                echo json_encode($product);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Product not found."]);
            }
        } else {
            // Get All Products
            $stmt = $db->query("SELECT * FROM products ORDER BY id DESC");
            $products = $stmt->fetchAll();
            echo json_encode($products);
        }
        break;

    case 'POST':
        // Create or Update
        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->name) || !isset($data->price) || !isset($data->slug)) {
            http_response_code(400);
            echo json_encode(["message" => "Incomplete data."]);
            exit;
        }

        try {
            $db->beginTransaction();

            if (isset($data->id) && !empty($data->id)) {
                // Update
                // Update
                $stmt = $db->prepare("UPDATE products SET name=?, slug=?, description=?, price=?, image_url=?, active=?, theme=?, request_email=?, request_phone=? WHERE id=?");
                $stmt->execute([
                    $data->name,
                    $data->slug,
                    $data->description ?? '',
                    $data->price,
                    $data->image_url ?? '',
                    $data->active ?? 1,
                    $data->theme ?? 'dark',
                    $data->request_email ?? 1,
                    $data->request_phone ?? 1,
                    $data->id
                ]);
                $productId = $data->id;
            } else {
                // Insert
                // Insert
                $stmt = $db->prepare("INSERT INTO products (name, slug, description, price, image_url, active, theme, request_email, request_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data->name,
                    $data->slug,
                    $data->description ?? '',
                    $data->price,
                    $data->image_url ?? '',
                    $data->active ?? 1,
                    $data->theme ?? 'dark',
                    $data->request_email ?? 1,
                    $data->request_phone ?? 1
                ]);
                $productId = $db->lastInsertId();
            }

            // Handle Bumps (Full replace for simplicity)
            if (isset($data->bumps) && is_array($data->bumps)) {
                $db->prepare("DELETE FROM order_bumps WHERE product_id = ?")->execute([$productId]);
                $bumpStmt = $db->prepare("INSERT INTO order_bumps (product_id, title, description, price, image_url, active) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($data->bumps as $bump) {
                    $bumpStmt->execute([
                        $productId,
                        $bump->title,
                        $bump->description ?? '',
                        $bump->price,
                        $bump->image_url ?? '',
                        $bump->active ?? 1
                    ]);
                }
            }

            // Handle Pixels (Full replace)
            if (isset($data->pixels) && is_array($data->pixels)) {
                $db->prepare("DELETE FROM pixels WHERE product_id = ?")->execute([$productId]);
                $pixelStmt = $db->prepare("INSERT INTO pixels (product_id, type, pixel_id, token, active) VALUES (?, ?, ?, ?, ?)");
                foreach ($data->pixels as $pixel) {
                    $pixelStmt->execute([
                        $productId,
                        $pixel->type,
                        $pixel->pixel_id,
                        $pixel->token ?? '',
                        $pixel->active ?? 1
                    ]);
                }
            }

            $db->commit();
            echo json_encode(["message" => "Product saved.", "id" => $productId]);

        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(503);
            echo json_encode(["message" => "Unable to save product.", "error" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "ID required."]);
            exit;
        }
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt->execute([$_GET['id']])) {
            echo json_encode(["message" => "Product deleted."]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "Unable to delete product."]);
        }
        break;
}
