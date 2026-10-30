<?php
/**
 * api/webhook-infinitepay.php
 * Receptor de Webhook da InfinitePay para confirmação de pagamento.
 * Atualiza o status do pedido para 'paid', dispara entregáveis, UTMify, Meta CAPI e Webhooks customizados.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/functions/log_activity.php';

header('Content-Type: application/json');

$payload_json = file_get_contents('php://input');

if (empty($payload_json)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload vazio.']);
    exit;
}

$payload = json_decode($payload_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON inválido.']);
    exit;
}

log_activity("InfinitePay Webhook Recebido: " . $payload_json, 'infinitepay_webhook.log', __DIR__);

// Identificadores enviados pela InfinitePay
$order_nsu = $payload['order_nsu'] ?? ($payload['orderNsu'] ?? null);
$transaction_nsu = $payload['transaction_nsu'] ?? ($payload['transactionNsu'] ?? null);
$invoice_slug = $payload['invoice_slug'] ?? ($payload['invoiceSlug'] ?? ($payload['slug'] ?? null));
$capture_method = $payload['capture_method'] ?? ($payload['captureMethod'] ?? 'pix');
$receipt_url = $payload['receipt_url'] ?? ($payload['receiptUrl'] ?? null);
$paid_amount = $payload['paid_amount'] ?? ($payload['amount'] ?? 0);

if (!$order_nsu) {
    // Pode ser um ping de teste de URL feito pela InfinitePay
    log_activity("InfinitePay Webhook: Ping de teste sem order_nsu recebido com sucesso.", 'infinitepay_webhook.log', __DIR__);
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Webhook InfinitePay ativo. Aguardando eventos de pedidos.']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Localiza o pedido pelo order_nsu (transaction_id ou external_id)
    $stmt = $db->prepare("SELECT * FROM orders WHERE transaction_id = ? OR external_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$order_nsu, $order_nsu]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        log_activity("InfinitePay Webhook: Pedido não encontrado para order_nsu '{$order_nsu}'", 'infinitepay_webhook.log', __DIR__);
        // Retorna 200 para evitar retentativas infinitas caso o NSU seja de outro ambiente/teste
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Pedido não encontrado, evento ignorado.']);
        exit;
    }

    if ($order['status'] !== 'paid') {
        // Normaliza método de captura
        $normalized_method = (strtolower($capture_method) === 'credit_card' || strtolower($capture_method) === 'card') ? 'credit_card' : 'pix';

        // Atualiza status e método no banco
        $updateStmt = $db->prepare("UPDATE orders SET status = 'paid', payment_method = ?, updated_at = datetime('now', '-03:00') WHERE id = ?");
        $updateStmt->execute([$normalized_method, $order['id']]);
        $order['status'] = 'paid';
        $order['payment_method'] = $normalized_method;

        // Atualiza json_data com os dados do comprovante e webhook
        $orderJsonData = json_decode($order['json_data'] ?? '{}', true);
        if (!is_array($orderJsonData)) $orderJsonData = [];

        $orderJsonData['infinitepay_webhook'] = $payload;
        $orderJsonData['receipt_url'] = $receipt_url;
        $orderJsonData['transaction_nsu'] = $transaction_nsu;
        $orderJsonData['invoice_slug'] = $invoice_slug;
        $orderJsonData['paid_amount'] = $paid_amount;

        $updJson = $db->prepare("UPDATE orders SET json_data = ? WHERE id = ?");
        $updJson->execute([json_encode($orderJsonData), $order['id']]);

        log_activity("InfinitePay Webhook: Pedido #{$order['id']} aprovado com sucesso via {$normalized_method}!", 'infinitepay_webhook.log', __DIR__);

        $productsList = $orderJsonData['products'] ?? [];
        $trackingData = $orderJsonData['tracking'] ?? [];
        $externalID = $order['external_id'] ?? '';

        // 1. Webhook Customizado
        require_once __DIR__ . '/functions/trigger_custom_webhooks.php';
        trigger_custom_webhooks('order.paid', $order['id']);

        // 2. Entregáveis do Produto (Email e WhatsApp via Evolution API / Twilio / DisparoPro)
        require_once __DIR__ . '/functions/process_order_deliverables.php';
        require_once __DIR__ . '/functions/replace_shortcodes.php';
        require_once __DIR__ . '/functions/send_evolution_message.php';
        require_once __DIR__ . '/functions/send_order_email.php';
        require_once __DIR__ . '/functions/send_utmify_event.php';
        require_once __DIR__ . '/functions/track_meta_purchase.php';

        $customerDataForHelper = [
            'name' => $order['customer_name'] ?? '',
            'email' => $order['customer_email'] ?? '',
            'phone' => $order['customer_phone'] ?? '',
            'document' => $order['customer_cpf'] ?? ''
        ];

        processOrderDeliverables($productsList, $customerDataForHelper, $db);

        // 3. UTMIFY - Evento Pago
        $full_webhook_payload = [
            'id' => $order['id'],
            'correlation_id' => $order_nsu,
            'external_id' => $externalID,
            'gateway' => 'infinitepay',
            'status' => 'paid',
            'value' => (int) round(($order['total_amount'] ?? 0) * 100),
            'value_formatted' => (float) ($order['total_amount'] ?? 0),
            'created_at' => $order['created_at'] ?? date('Y-m-d H:i:s'),
            'customer' => [
                'name' => $order['customer_name'] ?? '',
                'email' => $order['customer_email'] ?? '',
                'phone' => $order['customer_phone'] ?? '',
                'document' => $order['customer_cpf'] ?? '',
                'external_id' => $externalID
            ],
            'products' => $productsList,
            'tracking' => $trackingData
        ];

        sendUtmifyEvent($full_webhook_payload, 'paid');

        // 4. Meta Conversions API (CAPI)
        trackMetaPurchase($order['id'], $db);

    } else {
        log_activity("InfinitePay Webhook: Pedido #{$order['id']} já estava com status 'paid'.", 'infinitepay_webhook.log', __DIR__);
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Pagamento processado com sucesso']);

} catch (Exception $e) {
    log_activity("InfinitePay Webhook Exception: " . $e->getMessage(), 'infinitepay_webhook.log', __DIR__);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno ao processar webhook: ' . $e->getMessage()]);
}
