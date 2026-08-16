<?php
// api/v1/mark-order-paid.php
// Endpoint administrativo para marcar um pedido como PAGO manualmente e disparar os entregáveis.

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../api/connection.php';
require_once __DIR__ . '/../../api/evolution-helper.php';
require_once __DIR__ . '/../../api/utmify-helper.php';
require_once __DIR__ . '/../../api/functions/trigger_custom_webhooks.php';
require_once __DIR__ . '/../../api/functions/track_meta_purchase.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$orderId = $data['order_id'] ?? $data['id'] ?? null;
$correlationId = $data['correlation_id'] ?? $data['correlationId'] ?? null;

if (empty($orderId) && empty($correlationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do pedido ou correlation_id não informado.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Busca o pedido por ID ou correlation_id
    if (!empty($orderId)) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([(int)$orderId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ?");
        $stmt->execute([$correlationId]);
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado no banco de dados.']);
        exit;
    }

    // 2. Atualiza o status do pedido para 'paid'
    $updateStmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = datetime('now', '-03:00') WHERE id = ?");
    $updateStmt->execute([$order['id']]);
    $order['status'] = 'paid';

    $storedData = json_decode($order['json_data'] ?? '{}', true) ?? [];

    // 3. Dispara Webhooks Customizados (order.paid)
    try {
        trigger_custom_webhooks('order.paid', $order['id']);
    } catch (Exception $e) {
        error_log("Webhook Error: " . $e->getMessage());
    }

    // 4. Dispara UTMIFY Event (paid)
    try {
        $utmifyOrderData = [
            'correlation_id' => $order['transaction_id'],
            'value' => (int) ($order['total_amount'] * 100),
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
    } catch (Exception $e) {
        error_log("UTMify Error: " . $e->getMessage());
    }

    // 5. Dispara Meta Conversions API (CAPI Purchase)
    try {
        trackMetaPurchase($order['id'], $db);
    } catch (Exception $e) {
        error_log("Meta CAPI Error: " . $e->getMessage());
    }

    // 6. Dispara os Entregáveis do Pedido (WhatsApp Evolution/Twilio, SMS, E-mail)
    $deliverablesResult = [];
    try {
        $customerDataForHelper = [
            'name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone'],
            'document' => $order['customer_cpf']
        ];

        $deliverablesResult = processOrderDeliverables($storedData['products'] ?? [], $customerDataForHelper, $db);
    } catch (Exception $e) {
        error_log("Deliverables Error: " . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Pedido #{$order['id']} marcado como PAGO com sucesso!",
        'order_id' => $order['id'],
        'deliverables' => $deliverablesResult
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao marcar pedido como pago: ' . $e->getMessage()]);
}
