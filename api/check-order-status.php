<?php
/**
 * api/check-order-status.php
 * Endpoint para verificar o status de um pedido (pending ou paid).
 * Recebe um correlationId via POST JSON ou GET e retorna o status.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/connection.php';

$correlationId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $correlationId = $data['correlationId'] ?? ($data['correlation_id'] ?? null);
} else {
    $correlationId = $_GET['correlationId'] ?? ($_GET['correlation_id'] ?? null);
}

if (empty($correlationId)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'correlationId ausente.']));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, status, total_amount, payment_method, gateway, json_data, customer_name, customer_email, customer_phone, customer_cpf, created_at, external_id FROM orders WHERE transaction_id = ? OR external_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$correlationId, $correlationId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Se ainda estiver pendente e o gateway for InfinitePay, consulta API da InfinitePay para confirmação imediata
        if ($order['status'] !== 'paid' && ($order['gateway'] ?? '') === 'infinitepay') {
            $transaction_nsu = $_GET['transaction_nsu'] ?? ($_POST['transaction_nsu'] ?? null);
            $jsonData = json_decode($order['json_data'] ?? '{}', true);
            if (!$transaction_nsu && !empty($jsonData['transaction_nsu'])) {
                $transaction_nsu = $jsonData['transaction_nsu'];
            }
            $slug = $jsonData['slug'] ?? ($_GET['slug'] ?? null);

            if (!empty($slug) && !empty($transaction_nsu)) {
                require_once __DIR__ . '/config.php';
                $handle = defined('INFINITEPAY_HANDLE') && !empty(INFINITEPAY_HANDLE) ? INFINITEPAY_HANDLE : 'abn-digital';
                $checkPayload = [
                    'handle' => $handle,
                    'order_nsu' => $correlationId,
                    'transaction_nsu' => $transaction_nsu,
                    'slug' => $slug
                ];

                $ch = curl_init('https://api.checkout.infinitepay.io/payment_check');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($checkPayload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Api-Key: ' . (defined('INFINITEPAY_API_KEY') ? INFINITEPAY_API_KEY : '')
                ]);
                $checkRes = curl_exec($ch);
                curl_close($ch);

                $checkData = json_decode($checkRes, true);
                if (!empty($checkData['paid'])) {
                    $updateStmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = datetime('now', '-03:00') WHERE id = ?");
                    $updateStmt->execute([$order['id']]);
                    $order['status'] = 'paid';

                    require_once __DIR__ . '/functions/trigger_custom_webhooks.php';
                    trigger_custom_webhooks('order.paid', $order['id']);

                    require_once __DIR__ . '/functions/process_order_deliverables.php';
                    require_once __DIR__ . '/functions/send_utmify_event.php';
                    require_once __DIR__ . '/functions/track_meta_purchase.php';

                    $customerDataForHelper = [
                        'name' => $order['customer_name'] ?? '',
                        'email' => $order['customer_email'] ?? '',
                        'phone' => $order['customer_phone'] ?? '',
                        'document' => $order['customer_cpf'] ?? ''
                    ];
                    processOrderDeliverables($jsonData['products'] ?? [], $customerDataForHelper, $db);
                    trackMetaPurchase($order['id'], $db);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'status' => $order['status'],
            'order_id' => $order['id'],
            'gateway' => $order['gateway'] ?? 'woovi'
        ]);
        exit;
    }

    // Fallback para arquivo legada se existir
    $orders_file_path = __DIR__ . '/database/detailed_orders.json';
    if (file_exists($orders_file_path)) {
        $orders = json_decode(file_get_contents($orders_file_path), true);
        if (is_array($orders)) {
            foreach ($orders as $o) {
                if (isset($o['correlationId']) && $o['correlationId'] === $correlationId) {
                    echo json_encode(['success' => true, 'status' => $o['status']]);
                    exit;
                }
            }
        }
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido com o correlationId fornecido não foi encontrado.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao consultar status: ' . $e->getMessage()]);
}
