<?php
session_start();

// Segurança: Apenas usuários logados
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

$jsonPath = __DIR__ . '/../api/database/detailed_orders.json';

if (!file_exists($jsonPath)) {
    file_put_contents($jsonPath, '[]');
}

// LÓGICA DE EXCLUSÃO (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idToDelete = $_GET['id'];
    $data = json_decode(file_get_contents($jsonPath), true) ?? [];
    
    // Filtra removendo o item com o correlationId correspondente
    $newData = array_filter($data, function($item) use ($idToDelete) {
        // Verifica se correlationId existe, senão usa lógica de índice (fallback)
        return isset($item['correlationId']) ? $item['correlationId'] !== $idToDelete : true;
    });
    
    // Reorganiza os índices do array
    $newData = array_values($newData);
    
    if (file_put_contents($jsonPath, json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo']);
    }
    exit;
}

// LÓGICA PADRÃO (LISTAR)
$content = file_get_contents($jsonPath);
echo empty($content) ? '[]' : $content;
?>
