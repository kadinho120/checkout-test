<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    // Parâmetros de filtro
    $date = isset($_GET['date']) ? trim($_GET['date']) : '';
    $startTime = isset($_GET['start_time']) ? trim($_GET['start_time']) : '';
    $endTime = isset($_GET['end_time']) ? trim($_GET['end_time']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchDate = isset($_GET['searchDate']) ? trim($_GET['searchDate']) : '';
    $startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
    $endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

    $whereParts = [];
    $params = [];

    // 1. Filtro por Data
    if (!empty($searchDate)) {
        if ($searchDate === 'custom' && !empty($startDate) && !empty($endDate)) {
            $whereParts[] = "created_at BETWEEN :start_custom AND :end_custom";
            $params[':start_custom'] = $startDate . " 00:00:00";
            $params[':end_custom'] = $endDate . " 23:59:59";
        } elseif ($searchDate === 'today') {
            $now = new DateTime();
            $whereParts[] = "date(created_at) = :today_date";
            $params[':today_date'] = $now->format('Y-m-d');
        } elseif ($searchDate === 'yesterday') {
            $whereParts[] = "date(created_at) = date('now', '-03:00', '-1 day')";
        } elseif ($searchDate === 'last7') {
            $whereParts[] = "created_at >= date('now', '-03:00', '-7 days')";
        } elseif ($searchDate === 'last14') {
            $whereParts[] = "created_at >= date('now', '-03:00', '-14 days')";
        } elseif ($searchDate === 'last30') {
            $whereParts[] = "created_at >= date('now', '-03:00', '-30 days')";
        } elseif ($searchDate === 'this_month') {
            $whereParts[] = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', '-03:00')";
        } elseif ($searchDate === 'this_year') {
            $whereParts[] = "strftime('%Y', created_at) = strftime('%Y', 'now', '-03:00')";
        }
        // 'all' -> no date filter
    } elseif (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $st = !empty($startTime) ? (strlen($startTime) === 5 ? $startTime . ':00' : $startTime) : '00:00:00';
        $et = !empty($endTime) ? (strlen($endTime) === 5 ? $endTime . ':59' : $endTime) : '23:59:59';

        $startDateTime = $date . ' ' . $st;
        $endDateTime = $date . ' ' . $et;

        $whereParts[] = "created_at >= :start_datetime AND created_at <= :end_datetime";
        $params[':start_datetime'] = $startDateTime;
        $params[':end_datetime'] = $endDateTime;
    } elseif (!empty($startTime) || !empty($endTime)) {
        // Apenas filtro por horário (sem data específica)
        if (!empty($startTime) && !empty($endTime)) {
            $st = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
            $et = strlen($endTime) === 5 ? $endTime . ':59' : $endTime;

            $whereParts[] = "time(strftime('%H:%M:%S', created_at)) >= :start_time AND time(strftime('%H:%M:%S', created_at)) <= :end_time";
            $params[':start_time'] = $st;
            $params[':end_time'] = $et;
        } elseif (!empty($startTime)) {
            $st = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
            $whereParts[] = "time(strftime('%H:%M:%S', created_at)) >= :start_time";
            $params[':start_time'] = $st;
        } elseif (!empty($endTime)) {
            $et = strlen($endTime) === 5 ? $endTime . ':59' : $endTime;
            $whereParts[] = "time(strftime('%H:%M:%S', created_at)) <= :end_time";
            $params[':end_time'] = $et;
        }
    }

    // 2. Filtro por Status
    if (!empty($status) && $status !== 'all') {
        if ($status === 'paid') {
            $whereParts[] = "status IN ('paid', 'completed')";
        } elseif ($status === 'pending') {
            $whereParts[] = "status = 'pending'";
        } elseif ($status === 'cancelled') {
            $whereParts[] = "status IN ('cancelled', 'expired', 'failed', 'refunded')";
        } elseif ($status === 'manual_pix_copied') {
            $whereParts[] = "gateway IN ('manual_pix', 'pix_manual', 'direct_pix') AND pix_copied = 1";
        } elseif ($status === 'manual_pix_not_copied') {
            $whereParts[] = "gateway IN ('manual_pix', 'pix_manual', 'direct_pix') AND (pix_copied = 0 OR pix_copied IS NULL)";
        } elseif ($status === 'manual_pix') {
            $whereParts[] = "gateway IN ('manual_pix', 'pix_manual', 'direct_pix')";
        } else {
            $whereParts[] = "status = :status";
            $params[':status'] = $status;
        }
    }

    // 3. Filtro de Busca Textual
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $whereParts[] = "(customer_name LIKE :search_name OR customer_email LIKE :search_email OR customer_phone LIKE :search_phone OR id = :search_id OR transaction_id LIKE :search_tx OR external_id LIKE :search_ext)";
        $params[':search_name'] = $searchTerm;
        $params[':search_email'] = $searchTerm;
        $params[':search_phone'] = $searchTerm;
        $params[':search_id'] = is_numeric($search) ? (int)$search : 0;
        $params[':search_tx'] = $searchTerm;
        $params[':search_ext'] = $searchTerm;
    }

    // Cláusula WHERE
    $whereSql = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    // Contagem total para paginação
    $countQuery = "SELECT COUNT(*) FROM orders $whereSql";
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = $limit > 0 ? (int)ceil($totalCount / $limit) : 1;
    if ($totalPages < 1) {
        $totalPages = 1;
    }

    // Busca de pedidos paginada
    $query = "SELECT * FROM orders $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$order) {
        $data = json_decode($order['json_data'] ?? '{}', true);
        $productName = 'Produto desconhecido';
        if (isset($data['products']) && is_array($data['products']) && count($data['products']) > 0) {
            $names = array_column($data['products'], 'name');
            $productName = $names[0] . (count($names) > 1 ? ' + ' . (count($names) - 1) . ' item(s)' : '');
        } elseif (!empty($order['product_name'])) {
            $productName = $order['product_name'];
        }
        $order['product_name'] = $productName;
        $order['pix_copied'] = (int)($order['pix_copied'] ?? 0);
    }
    unset($order);

    echo json_encode([
        'data' => $orders,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'total_count' => $totalCount,
        'filters' => [
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'search' => $search,
            'searchDate' => $searchDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error fetching orders", "error" => $e->getMessage()]);
}
?>