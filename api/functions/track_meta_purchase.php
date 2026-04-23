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
        log_activity("DEBUG: Início trackMetaPurchase para Pedido #{$order_id}. Correlation: $correlation_id", 'meta_activity.log');

        // 1. Buscar dados de correlação do banco (Salvamento inteligente)
        $stmtTrack = $db->prepare("SELECT * FROM tracking_logs WHERE correlation_id = ?");
        $stmtTrack->execute([$correlation_id]);
        $trackData = $stmtTrack->fetch(PDO::FETCH_ASSOC);

        if ($trackData) {
            log_activity("DEBUG: Dados encontrados na tracking_logs para $correlation_id", 'meta_activity.log');
        } else {
            log_activity("DEBUG: Dados NÃO encontrados na tracking_logs para $correlation_id. Usando fallback do json_data.", 'meta_activity.log');
        }

        // 2. Extrair parâmetros (Preferência para tracking_logs, fallback para json_data do pedido)
        $fbc = $trackData['fbc'] ?? $orderJsonData['tracking']['fbc'] ?? null;
        $fbp = $trackData['fbp'] ?? $orderJsonData['tracking']['fbp'] ?? null;
        $userAgent = $trackData['user_agent'] ?? $orderJsonData['tracking']['user_agent'] ?? null;
        $sourceUrl = $trackData['event_url'] ?? $orderJsonData['tracking']['event_source_url'] ?? null;
        $pixelId = $trackData['pixel_id'] ?? $orderJsonData['tracking']['pixel_id'] ?? null;
        $clientIp = $trackData['client_ip'] ?? $orderJsonData['tracking']['client_ip'] ?? null;

        // 3. Buscar Credenciais (Pixel/Token) do Produto
        $capiToken = null;
        $mainProductSku = $orderJsonData['products'][0]['sku'] ?? null;
        
        log_activity("DEBUG: SKU do produto principal: $mainProductSku", 'meta_activity.log');

        if ($mainProductSku) {
            $stmtPx = $db->prepare("
                SELECT px.pixel_id, px.token, p.request_email, p.request_phone, p.request_name
                FROM products p 
                JOIN pixels px ON p.id = px.product_id 
                WHERE p.slug = ? AND px.type = 'facebook' AND px.active = 1 
                LIMIT 1
            ");
            $stmtPx->execute([$mainProductSku]);
            $pxData = $stmtPx->fetch(PDO::FETCH_ASSOC);

            if ($pxData) {
                log_activity("DEBUG: Credenciais encontradas na tabela pixels para o slug: $mainProductSku", 'meta_activity.log');
                if (!$pixelId)
                    $pixelId = $pxData['pixel_id'];
                $capiToken = $pxData['token'];
                $reqEmail = (int) ($pxData['request_email'] ?? 1);
                $reqPhone = (int) ($pxData['request_phone'] ?? 1);
                $reqName = (int) ($pxData['request_name'] ?? 1);
            } else {
                log_activity("WARNING: Nenhuma credencial ativa encontrada na tabela pixels para o slug: $mainProductSku", 'meta_activity.log');
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
            'client_ip' => $clientIp ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $userAgent,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'source_url' => $sourceUrl
        ];

        // 4.1 Validação de Dados Reais vs Gerados (Otimização Meta)
        $customerEmail = $order['customer_email'];
        $customerPhone = $order['customer_phone'];

        // Se o produto não pede e-mail OU se o e-mail contém o domínio fake, removemos para evitar poluir a Meta
        if ($reqEmail === 0 || strpos($customerEmail, '@naoinformado.com') !== false) {
            $customerEmail = null;
        }

        // Se o produto não pede telefone, removemos o telefone gerado do envio
        if ($reqPhone === 0) {
            $customerPhone = null;
        }

        $customerName = $order['customer_name'];

        // Se o nome for genérico (ex: Cliente #XXXX) ou se o produto não solicita nome, removemos apenas o NOME do envio para a Meta
        if ($reqName === 0 || strpos($customerName, 'Cliente #') === 0) {
            $customerName = null;
        }

        $eventData = [
            'correlation_id' => $correlation_id,
            'external_id' => $correlation_id, // Usado para Match de Alta Qualidade
            'value' => (int) ($order['total_amount'] * 100),
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
                'document' => $order['customer_cpf']
            ],
            'products' => $orderJsonData['products'] ?? [],
            'tracking' => $orderJsonData['tracking'] ?? [] // Inclui UTMs
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
