<?php
// api/fulfill-order.php
// Endpoint para buscar um pedido, atualizar status para PAID e encaminhar para webhook.

header('Content-Type: application/json');
require_once __DIR__ . '/connection.php';

// Recebe o input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['correlationId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'correlationId ausente na requisição.']);
    exit;
}

$correlationId = $data['correlationId'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Busca o pedido pelo transaction_id (correlation_id)
    $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ?");
    $stmt->execute([$correlationId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {

        // 2. Atualiza o status se não estiver pago
        if ($order['status'] !== 'paid' && $order['status'] !== 'completed') {
            $updateStmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = datetime('now') WHERE id = ?");
            $updateStmt->execute([$order['id']]);
            $order['status'] = 'paid'; // Atualiza array local para envio
        }

        $pixData = $storedData['pix_data'] ?? [];

        // --- UTMIFY PAID HOOK ---
        require_once __DIR__ . '/utmify-helper.php';

        $utmifyOrderData = [
            'correlation_id' => $order['transaction_id'],
            'value' => (int) ($order['total_amount'] * 100), // Convert back to cents for helper
            'status' => 'paid',
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf']
            ],
            'products' => $storedData['products'] ?? [],
            'tracking' => $storedData['tracking'] ?? []
        ];

        sendUtmifyEvent($utmifyOrderData, 'paid');

        // --- META CAPI PURCHASE HOOK (Centralized) ---
        require_once __DIR__ . '/functions/track_meta_purchase.php';
        trackMetaPurchase($order['id'], $db);
        // ----------------------------------------------

        // --- EVOLUTION API DELIVERABLE HOOK ---
        require_once __DIR__ . '/evolution-helper.php';

        try {
            // Prepare Customer Data for Helper
            $customerDataForHelper = [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'], // Already in $customerPhone variable too
                'document' => $order['customer_cpf']
            ];

            // Use the robust helper function that handles Bumps and Main Products correctly
            processOrderDeliverables($storedData['products'] ?? [], $customerDataForHelper, $db);

        } catch (Exception $e) { /* Silent fail for Deliverable */
            error_log("Deliverable Error: " . $e->getMessage());
        }
        // --------------------------------------

        // ------------------------

        if ($http_code >= 200 && $http_code < 300) {
            echo json_encode([
                'success' => true,
                'message' => 'Pedido atualizado e enviado ao n8n.',
                'data_sent' => $payloadForN8N
            ]);
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Atualizado DB, mas erro no N8N.', 'n8n_code' => $http_code]);
        }

    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>