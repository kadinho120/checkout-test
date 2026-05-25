<?php
// api/v1/tracking.php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch tracking logs joined with orders to check if converted (Paginated)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Get total count
    $totalCount = $db->query("SELECT COUNT(*) FROM tracking_logs")->fetchColumn();
    $totalPages = ceil($totalCount / $limit);

    $query = "
        SELECT 
            t.id,
            t.correlation_id,
            t.fbc,
            t.fbp,
            t.user_agent,
            t.event_url,
            t.pixel_id,
            t.created_at as tracking_date,
            o.id as order_id,
            o.status as order_status,
            o.total_amount
        FROM tracking_logs t
        LEFT JOIN orders o ON t.correlation_id = o.transaction_id
        ORDER BY t.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process data for frontend
    $processed = array_map(function ($row) {
        // Parse URL for UTMs
        $urlParts = parse_url($row['event_url']);
        $queryParams = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        return [
            'id' => $row['id'],
            'correlation_id' => $row['correlation_id'],
            'date' => $row['tracking_date'],
            'identifiers' => [
                'fbc' => $row['fbc'] ?? '-',
                'fbp' => $row['fbp'] ?? '-',
                'pixel' => $row['pixel_id']
            ],
            'source' => [
                'domain' => $urlParts['host'] ?? 'Desconhecido',
                'utm_source' => $queryParams['utm_source'] ?? '-',
                'utm_campaign' => $queryParams['utm_campaign'] ?? '-'
            ],
            'conversion' => [
                'converted' => !empty($row['order_id']),
                'order_id' => $row['order_id'],
                'status' => $row['order_status'],
                'amount' => $row['total_amount']
            ]
        ];
    }, $data);

    echo json_encode([
        'data' => $processed,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'total_count' => (int)$totalCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>