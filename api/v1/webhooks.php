<?php
/**
 * API para gestão de Webhooks (CRUD)
 * Local: api/v1/webhooks.php
 */

session_start();

// Verifica autenticação
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Não autorizado']));
}

require_once __DIR__ . '/../connection.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $db->query("SELECT * FROM webhooks ORDER BY id DESC");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($results);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'POST':
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['url'])) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Dados inválidos. URL é obrigatória.']));
        }

        // Normaliza eventos (converte array para string se necessário)
        $events = $data['events'] ?? '';
        if (is_array($events)) {
            $events = implode(',', $events);
        }

        $active = isset($data['active']) ? (int)$data['active'] : 1;

        try {
            if (isset($data['id']) && !empty($data['id'])) {
                // UPDATE
                $stmt = $db->prepare("UPDATE webhooks SET url = ?, events = ?, active = ? WHERE id = ?");
                $stmt->execute([$data['url'], $events, $active, $data['id']]);
                echo json_encode(['success' => true, 'message' => 'Webhook atualizado com sucesso.']);
            } else {
                // INSERT
                $stmt = $db->prepare("INSERT INTO webhooks (url, events, active) VALUES (?, ?, ?)");
                $stmt->execute([$data['url'], $events, $active]);
                echo json_encode(['success' => true, 'message' => 'Webhook cadastrado com sucesso.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'ID não informado.']));
        }

        try {
            $stmt = $db->prepare("DELETE FROM webhooks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Webhook removido com sucesso.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao remover: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
        break;
}
