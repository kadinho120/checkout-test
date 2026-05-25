<?php
// api/v1/heartbeat.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit;
}

include_once '../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Purge expired sessions (inactive for more than 15 seconds)
    $db->exec("DELETE FROM checkout_sessions WHERE last_ping < datetime('now', '-15 seconds')");

    // 2. Read request body
    $data = json_decode(file_get_contents("php://input"));

    if (empty($data->session_id) || empty($data->status)) {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete heartbeat data."]);
        exit;
    }

    $sessionId = $data->session_id;
    $status = $data->status;
    $productSlug = $data->product_slug ?? null;

    // 3. Upsert session ping
    $stmt = $db->prepare("
        INSERT INTO checkout_sessions (session_id, status, product_slug, last_ping)
        VALUES (:session_id, :status, :product_slug, CURRENT_TIMESTAMP)
        ON CONFLICT(session_id) DO UPDATE SET
            status = excluded.status,
            product_slug = excluded.product_slug,
            last_ping = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        ':session_id' => $sessionId,
        ':status' => $status,
        ':product_slug' => $productSlug
    ]);

    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
