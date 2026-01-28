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

        // 3. Prepara o payload para o N8N
        // O campo json_data no banco contém tracking, produtos, etc.
        $storedData = json_decode($order['json_data'], true) ?? [];

        $payloadForN8N = [
            'id' => $order['id'],
            'correlation_id' => $order['transaction_id'],
            'status' => 'paid',
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf']
            ],
            'amount' => (float) $order['total_amount'],
            'updated_at' => date('Y-m-d H:i:s'),
            'products' => $storedData['products'] ?? [],
            'tracking' => $storedData['tracking'] ?? [],
            'pix_data' => $storedData['pix_data'] ?? []
        ];

        // 4. Envia para o N8N (Webhook de Pagamento Confirmado)
        $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-pago-abacatepay-envio';

        $ch = curl_init($n8n_webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadForN8N));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($payloadForN8N))
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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