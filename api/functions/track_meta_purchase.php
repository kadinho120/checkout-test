<?php
/**
 * Função centralizada para disparar evento de Purchase no Meta Ads (CAPI)
 * Caminho: api/functions/track_meta_purchase.php
 */

require_once __DIR__ . '/send_meta_event.php';
require_once __DIR__ . '/normalize_and_hash.php';
require_once __DIR__ . '/log_activity.php';

function trackMetaPurchase($order_id, $db = null)
{
    if (!$db) {
        require_once __DIR__ . '/../connection.php';
        $database = new Database();
        $db = $database->getConnection();
    }

    try {
        // 1. Busca detalhes do pedido
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'message' => 'Pedido não encontrado'];
        }

        $orderJsonData = json_decode($order['json_data'] ?? '{}', true);
        $correlation_id = $order['transaction_id'];

        // 2. Busca dados de rastreamento (fbc, fbp, user_agent, etc)
        // Tentamos buscar da tabela tracking_logs primeiro
        $stmtTrack = $db->prepare("SELECT * FROM tracking_logs WHERE correlation_id = ?");
        $stmtTrack->execute([$correlation_id]);
        $trackData = $stmtTrack->fetch(PDO::FETCH_ASSOC);

        // Se não houver log específico, tenta pegar do JSON do pedido
        $fbc = $trackData['fbc'] ?? $orderJsonData['tracking']['fbc'] ?? null;
        $fbp = $trackData['fbp'] ?? $orderJsonData['tracking']['fbp'] ?? null;
        $userAgent = $trackData['user_agent'] ?? $orderJsonData['tracking']['user_agent'] ?? null;
        $sourceUrl = $trackData['event_url'] ?? $orderJsonData['tracking']['source_url'] ?? null;
        $pixelId = $trackData['pixel_id'] ?? $orderJsonData['tracking']['pixel_id'] ?? null;

        // 3. Identifica o Pixel/Token do Facebook
        $capiToken = null;
        $mainProductSku = $orderJsonData['products'][0]['sku'] ?? null;

        if ($mainProductSku) {
            $stmtPx = $db->prepare("
                SELECT px.pixel_id, px.token 
                FROM products p 
                JOIN pixels px ON p.id = px.product_id 
                WHERE p.slug = ? AND px.type = 'facebook' AND px.active = 1 
                LIMIT 1
            ");
            $stmtPx->execute([$mainProductSku]);
            $pxData = $stmtPx->fetch(PDO::FETCH_ASSOC);
            if ($pxData) {
                if (!$pixelId) $pixelId = $pxData['pixel_id'];
                $capiToken = $pxData['token'];
            }
        }

        if (!$pixelId || !$capiToken) {
            $errorMsg = "Pixel ID ou Token não encontrados para o produto: $mainProductSku";
            $db->prepare("UPDATE orders SET meta_purchase_status = 2, meta_purchase_log = ? WHERE id = ?")
               ->execute([$errorMsg, $order_id]);
            return ['success' => false, 'message' => $errorMsg];
        }

        // 4. Prepara o contexto de envio
        $context = [
            'client_ip' => $orderJsonData['tracking']['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $userAgent,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'source_url' => $sourceUrl
        ];

        $eventData = [
            'correlation_id' => $correlation_id,
            'value' => (int)($order['total_amount'] * 100),
            'customer' => [
                'name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'document' => $order['customer_cpf']
            ],
            'products' => $orderJsonData['products'] ?? []
        ];

        // 5. Envia o evento via Meta CAPI
        $result = sendMetaEvent($pixelId, $capiToken, 'Purchase', $eventData, $context);

        // 6. Atualiza o banco com o resultado
        $status = ($result['code'] >= 200 && $result['code'] < 300) ? 1 : 2;
        $log = "HTTP {$result['code']}: " . $result['response'];

        $updateStmt = $db->prepare("UPDATE orders SET meta_purchase_status = ?, meta_purchase_log = ? WHERE id = ?");
        $updateStmt->execute([$status, $log, $order_id]);

        log_activity("META_TRACK: Pedido #{$order_id} enviou purchase via CAPI. Status: $status", 'meta_activity.log', __DIR__ . '/..');

        return [
            'success' => ($status === 1),
            'code' => $result['code'],
            'response' => $result['response']
        ];

    } catch (Exception $e) {
        $error = "ERRO INTERNO: " . $e->getMessage();
        $db->prepare("UPDATE orders SET meta_purchase_status = 2, meta_purchase_log = ? WHERE id = ?")
           ->execute([$error, $order_id]);
        return ['success' => false, 'message' => $error];
    }
}
