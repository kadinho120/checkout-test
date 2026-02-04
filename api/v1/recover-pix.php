<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../connection.php';
require_once '../evolution-helper.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->order_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Order ID required"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch order
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$data->order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    $storedData = json_decode($order['json_data'], true) ?? [];
    $products = $storedData['products'] ?? [];
    $pixData = $storedData['pix_data'] ?? [];

    if (empty($products)) {
        echo json_encode(["success" => false, "message" => "No products found in this order"]);
        exit;
    }

    // Find Main Product to get Evolution Config
    // Assume first product is main, or filter out BUMPS (BUMP-*)
    $mainProductSku = null;
    $mainProductName = 'Produto';

    foreach ($products as $p) {
        if (strpos($p['sku'], 'BUMP-') !== 0) {
            $mainProductSku = $p['sku'];
            $mainProductName = $p['name'];
            break;
        }
    }

    if (!$mainProductSku) {
        // Fallback: use first item
        $mainProductSku = $products[0]['sku'];
        $mainProductName = $products[0]['name'];
    }

    // Fetch Config for Main Product
    $stmt = $db->prepare("SELECT evolution_instance, evolution_token, evolution_url FROM products WHERE slug = ?");
    $stmt->execute([$mainProductSku]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['evolution_url'])) {
        echo json_encode(["success" => false, "message" => "Evolution API not configured for this product ({$mainProductSku})"]);
        exit;
    }

    // Prepare Customer Data
    $customerData = [
        'name' => $order['customer_name'],
        'email' => $order['customer_email'],
        'phone' => $order['customer_phone'],
        'product_name' => $mainProductName
    ];

    // Standard Recovery Message
    // "Ola, Fulano! Vimos que você ainda não finalizou o pagamento do seu pedido do {nome_do_produto}. As vagas estão acabando e não podemos segurar a sua por muito tempo. Segue o pix copia e cola pra efetuar o pagamento:\n\n{pix_copia_cola}"

    $rawMessage = "Ola, {primeiro_nome}! Vimos que você ainda não finalizou o pagamento do seu pedido do {nome_do_produto}. As vagas estão acabando e não podemos segurar a sua por muito tempo. Segue o pix copia e cola pra efetuar o pagamento:\n\n{pix_copia_cola}";

    $pixCode = $pixData['brCode'] ?? '';

    $finalMessage = replaceShortcodes($rawMessage, $customerData, $pixCode);

    // Send Message
    // Send Message based on Type
    $type = $data->type ?? 'wpp'; // Default to wpp if not specified
    $results = [];

    if ($type === 'wpp' || $type === 'all') {
        $res = sendEvolutionMessage(
            $config['evolution_instance'],
            $config['evolution_token'],
            $config['evolution_url'],
            $customerData['phone'],
            'text',
            $finalMessage
        );
        $results['wpp'] = $res;
    }

    if ($type === 'email' || $type === 'all') {
        $emailSubject = "Finalize seu pedido de {nome_do_produto}";
        $emailSubject = replaceShortcodes($emailSubject, $customerData, '');

        $emailBody = "<p>Olá, {primeiro_nome}!</p>
        <p>Vimos que você ainda não finalizou o pagamento do seu pedido do <strong>{nome_do_produto}</strong>.</p>
        <p>As vagas estão acabando e não podemos segurar a sua por muito tempo.</p>
        <p>Segue o código Pix Copia e Cola para efetuar o pagamento:</p>
        <div style='background: #f4f4f4; padding: 15px; border-radius: 5px; word-break: break-all; margin: 20px 0; font-family: monospace;'>{pix_copia_cola}</div>
        <p>Caso já tenha efetuado o pagamento, desconsidere esta mensagem.</p>";

        $emailBody = replaceShortcodes($emailBody, $customerData, $pixCode);

        $resEmail = sendOrderEmail($customerData['email'], $emailSubject, $emailBody);
        $results['email'] = $resEmail;
    }

    echo json_encode([
        "success" => true,
        "message" => "Recovery processed",
        "details" => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
?>