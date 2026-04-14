<?php
/**
 * Função para disparar webhooks personalizados cadastrados no painel
 * Local: api/functions/trigger_custom_webhooks.php
 */

function trigger_custom_webhooks($event, $order_id)
{
    require_once __DIR__ . '/../connection.php';
    require_once __DIR__ . '/log_activity.php';

    try {
        $database = new Database();
        $db = $database->getConnection();

        // 1. Busca webhooks ativos
        $stmt = $db->prepare("SELECT url, events FROM webhooks WHERE active = 1");
        $stmt->execute();
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($webhooks)) {
            return;
        }

        // 2. Busca detalhes do pedido
        $orderStmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $orderStmt->execute([$order_id]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            log_activity("WEBHOOK_WARNING: Pedido ID {$order_id} não encontrado para disparo de webhook.", 'custom_webhooks.log', __DIR__ . '/..');
            return;
        }

        // Decodifica JSON data do pedido
        $orderJsonData = json_decode($order['json_data'] ?? '{}', true);
        
        // Payload padronizado para o webhook
        $payload = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'id' => $order['id'],
            'status' => $order['status'],
            'total_amount' => (float)$order['total_amount'],
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf'],
                'external_id' => $order['external_id']
            ],
            'products' => $orderJsonData['products'] ?? [],
            'tracking' => $orderJsonData['tracking'] ?? [],
            'transaction_id' => $order['transaction_id'],
            'created_at' => $order['created_at']
        ];

        $json_payload = json_encode($payload);

        foreach ($webhooks as $wh) {
            $subscribedEvents = array_map('trim', explode(',', $wh['events']));
            
            if (in_array($event, $subscribedEvents)) {
                $url = $wh['url'];
                
                // Envio simples via cURL
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Checkout-Event: ' . $event
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    log_activity("WEBHOOK_FAIL: Erro ao enviar para {$url}. Erro: {$curlError}", 'custom_webhooks.log', __DIR__ . '/..');
                } else {
                    log_activity("WEBHOOK_SENT: Evento {$event} enviado para {$url}. Status: {$httpCode}", 'custom_webhooks.log', __DIR__ . '/..');
                }
            }
        }

    } catch (Exception $e) {
        log_activity("WEBHOOK_CRITICAL_ERROR: " . $e->getMessage(), 'custom_webhooks.log', __DIR__ . '/..');
    }
}
