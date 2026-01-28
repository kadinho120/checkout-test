<?php
/**
 * Verifica o status de um pedido PIX no Banco de Dados SQLite.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/connection.php';

if (!isset($_GET['correlationId'])) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'Correlation ID não fornecido.']);
    exit;
}

$correlationId = $_GET['correlationId'];
$status = 'NOT_FOUND';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT status FROM orders WHERE transaction_id = ? LIMIT 1");
    $stmt->execute([$correlationId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Map DB status to expected Frontend status
        // DB: pending, paid
        // Frontend expects: COMPLETED, PAID
        $dbStatus = strtolower($result['status']);
        if ($dbStatus === 'paid' || $dbStatus === 'completed') {
            $status = 'PAID';
        } else {
            $status = 'PENDING';
        }
    }

} catch (Exception $e) {
    $status = 'ERROR';
}

echo json_encode(['status' => $status]);
?>