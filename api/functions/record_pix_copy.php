<?php
/**
 * Registra o evento de cópia do código Pix pelo cliente no checkout.
 * Função isolada para evitar efeitos colaterais em outras partes do sistema.
 *
 * @param string|null $correlationId
 * @param int|null $orderId
 * @return array
 */
function record_pix_copy($correlationId = null, $orderId = null)
{
    if (empty($correlationId) && empty($orderId)) {
        return [
            'success' => false,
            'message' => 'correlation_id ou order_id é obrigatório.'
        ];
    }

    try {
        require_once __DIR__ . '/../connection.php';
        $database = new Database();
        $db = $database->getConnection();

        // 1. Localiza o pedido
        if (!empty($orderId)) {
            $stmt = $db->prepare("SELECT id, json_data, pix_copied FROM orders WHERE id = ?");
            $stmt->execute([(int)$orderId]);
        } else {
            $stmt = $db->prepare("SELECT id, json_data, pix_copied FROM orders WHERE transaction_id = ?");
            $stmt->execute([trim($correlationId)]);
        }

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Pedido não encontrado.'
            ];
        }

        // 2. Atualiza os dados JSON mantendo histórico completo
        $jsonData = json_decode($order['json_data'] ?? '{}', true) ?: [];
        $jsonData['pix_copied'] = 1;
        $now = date('Y-m-d H:i:s');
        if (empty($jsonData['pix_copied_at'])) {
            $jsonData['pix_copied_at'] = $now;
        }

        // 3. Atualiza o banco com a marcação de cópia do Pix
        $updateStmt = $db->prepare("UPDATE orders SET pix_copied = 1, pix_copied_at = COALESCE(pix_copied_at, datetime('now', '-03:00')), updated_at = datetime('now', '-03:00'), json_data = ? WHERE id = ?");
        $updateStmt->execute([
            json_encode($jsonData),
            $order['id']
        ]);

        return [
            'success' => true,
            'order_id' => (int)$order['id'],
            'message' => 'Cópia de Pix registrada com sucesso.'
        ];
    } catch (Exception $e) {
        error_log('Erro ao registrar cópia de Pix: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro interno ao registrar cópia de Pix: ' . $e->getMessage()
        ];
    }
}
