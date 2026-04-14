<?php
/**
 * API para upload de imagens
 * Local: api/v1/upload.php
 */

session_start();

// Verifica autenticação
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Verifica se há arquivo
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/../../imagens/';

// Garante que o diretório existe
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Valida extensões
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Extensão não permitida. Use JPG, PNG, WEBP ou GIF.']);
    exit;
}

// Limita tamanho (ex: 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB.']);
    exit;
}

// Gera nome único
$newName = uniqid('prod_', true) . '.' . $ext;
$targetPath = $uploadDir . $newName;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Retorna o caminho relativo à raiz do projeto
    echo json_encode([
        'success' => true,
        'url' => 'imagens/' . $newName,
        'full_url' => '../imagens/' . $newName // Para uso imediato no admin se necessário
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha ao mover arquivo salvo. Verifique permissões da pasta imagens/.']);
}
