<?php
session_start();

// Segurança
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

$dbFile = __DIR__ . '/db/ad_spend.json';

// Garante que o arquivo existe
if (!file_exists($dbFile)) {
    // Tenta criar o diretório se não existir
    if (!is_dir(__DIR__ . '/db')) {
        mkdir(__DIR__ . '/db', 0755, true);
    }
    file_put_contents($dbFile, '[]');
}

// Handle POST (Salvar novo gasto)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['date']) || !isset($input['amount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $currentData = json_decode(file_get_contents($dbFile), true) ?? [];
    
    // Cria novo registro
    $newEntry = [
        'id' => uniqid(),
        'date' => $input['date'], // YYYY-MM-DD
        'amount' => floatval($input['amount']),
        'product' => $input['product'] ?? 'global', // 'global' ou nome do produto
        'platform' => $input['platform'] ?? 'meta',
        'createdAt' => date('Y-m-d H:i:s')
    ];

    // Se já existir um gasto para esse produto nessa data, podemos somar ou adicionar nova entrada
    // Neste modelo, vamos adicionar como nova entrada para manter histórico
    $currentData[] = $newEntry;

    if (file_put_contents($dbFile, json_encode($currentData, JSON_PRETTY_PRINT))) {
        echo json_encode(['success' => true, 'entry' => $newEntry]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no arquivo']);
    }
    exit;
}

// Handle GET (Listar gastos)
// Se vier com parâmetro ?action=delete&id=XXX, deleta
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = json_decode(file_get_contents($dbFile), true) ?? [];
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $idToDelete = $_GET['id'];
        $newData = array_filter($data, function($item) use ($idToDelete) {
            return $item['id'] !== $idToDelete;
        });
        // Re-indexar array
        $newData = array_values($newData);
        file_put_contents($dbFile, json_encode($newData, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode($data);
    exit;
}
?>
