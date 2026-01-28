<?php
// Endpoint para buscar um pedido, atualizar status para PAID e encaminhar para webhook.

header('Content-Type: application/json');

// Recebe o input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['correlationId'])) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'correlationId ausente na requisição.']);
    exit;
}

$correlationId = $data['correlationId'];

// --- ALTERAÇÃO 1: Caminho apontando para o VOLUME PERSISTENTE ---
// Sai da pasta 'api' (..) e entra em 'app/database'
$detailed_orders_path = __DIR__ . '/database/detailed_orders.json';

// Verifica se o arquivo existe (cria se não existir para evitar erros)
if (!file_exists($detailed_orders_path)) {
    file_put_contents($detailed_orders_path, '[]');
}

// Abre o arquivo com trava de segurança (Lock) para evitar conflito de vendas simultâneas
$fp = fopen($detailed_orders_path, 'c+'); 
$found_order = null;

if (flock($fp, LOCK_EX)) { // Trava exclusiva
    
    // Lê o conteúdo atual
    $filesize = filesize($detailed_orders_path);
    $content = $filesize > 0 ? fread($fp, $filesize) : '[]';
    $detailed_orders = json_decode($content, true);
    
    // --- ALTERAÇÃO 2: Busca e Atualiza o status na lista principal ---
    // Usamos o &$order (passagem por referência) ou o índice $key para alterar o original
    foreach ($detailed_orders as $key => $order) {
        if (isset($order['correlationId']) && $order['correlationId'] === $correlationId) {
            // Atualiza no ARRAY PRINCIPAL
            $detailed_orders[$key]['status'] = 'PAID'; 
            
            // Salva uma cópia para enviar ao webhook
            $found_order = $detailed_orders[$key]; 
            break;
        }
    }

    // --- ALTERAÇÃO 3: Grava de volta no disco se achou o pedido ---
    if ($found_order) {
        ftruncate($fp, 0); // Limpa o arquivo
        rewind($fp);       // Volta pro início
        fwrite($fp, json_encode($detailed_orders, JSON_PRETTY_PRINT)); // Escreve tudo de novo
        fflush($fp);       // Força a gravação
    }

    flock($fp, LOCK_UN); // Destrava
}
fclose($fp);

// Se encontrou e atualizou, segue para o envio ao n8n
if ($found_order) {
    $n8n_webhook_url = 'https://n8n-n8n.tutv5u.easypanel.host/webhook/pix-pago-abacatepay-envio';

    $ch = curl_init($n8n_webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($found_order));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($found_order))
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode([
            'success' => true, 
            'message' => 'Pedido atualizado para PAID e enviado ao n8n.',
            'order' => $found_order
        ]);
    } else {
        http_response_code(502); 
        echo json_encode(['success' => false, 'message' => 'Pedido atualizado no JSON, mas erro ao enviar para n8n.', 'n8n_response_code' => $http_code]);
    }

} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pedido não encontrado no banco de dados.']);
}
?>
