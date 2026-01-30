<?php
// api/v1/dashboard-stats.php

header('Content-Type: application/json');
require_once __DIR__ . '/../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Helper to get date range
    $searchDate = $_GET['searchDate'] ?? 'today';
    $customStart = $_GET['startDate'] ?? null;
    $customEnd = $_GET['endDate'] ?? null;

    // Fix Timezone for PHP logic
    date_default_timezone_set('America/Sao_Paulo');

    $whereClause = "WHERE 1=1";
    $params = [];

    if ($searchDate === 'custom' && $customStart && $customEnd) {
        $whereClause .= " AND created_at BETWEEN :start AND :end";
        $params[':start'] = $customStart . " 00:00:00";
        $params[':end'] = $customEnd . " 23:59:59";
    } else {
        // Presets
        $now = new DateTime();
        $today = $now->format('Y-m-d');

        switch ($searchDate) {
            case 'today':
                // Compare explicitly with today's date in PHP (which is now Sao Paulo)
                $whereClause .= " AND date(created_at) = :today";
                $params[':today'] = $today;
                break;
            case 'yesterday':
                $whereClause .= " AND date(created_at) = date('now', '-03:00', '-1 day')";
                break;
            case 'last7':
                $whereClause .= " AND created_at >= date('now', '-03:00', '-7 days')";
                break;
            case 'last14':
                $whereClause .= " AND created_at >= date('now', '-03:00', '-14 days')";
                break;
            case 'last30':
                $whereClause .= " AND created_at >= date('now', '-03:00', '-30 days')";
                break;
            case 'this_month':
                $whereClause .= " AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', '-03:00')";
                break;
            case 'this_year':
                $whereClause .= " AND strftime('%Y', created_at) = strftime('%Y', 'now', '-03:00')";
                break;
            default:
                // 'all' or fallback -> No filter
                break;
        }
    }

    // 1. Total Revenue (Paid)
    $stmt = $db->prepare("SELECT SUM(total_amount) as total FROM orders $whereClause AND status IN ('paid', 'completed', 'PAID', 'COMPLETED')");
    $stmt->execute($params);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Total Orders (All)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders $whereClause");
    $stmt->execute($params);
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Paid Orders
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders $whereClause AND status IN ('paid', 'completed', 'PAID', 'COMPLETED')");
    $stmt->execute($params);
    $paidOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3.1 Pending Orders (New)
    $stmt = $db->prepare("SELECT COUNT(*) as count, SUM(total_amount) as total FROM orders $whereClause AND status = 'pending'");
    $stmt->execute($params);
    $pendingData = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingOrders = $pendingData['count'] ?? 0;
    $pendingRevenue = $pendingData['total'] ?? 0;

    // 4. Conversion Rate
    $conversionRate = $totalOrders > 0 ? ($paidOrders / $totalOrders) * 100 : 0;

    // 5. Sales by Product (Aggregation)
    // Fetch all paid/completed orders in range to aggregate products from JSON
    $stmt = $db->prepare("SELECT json_data FROM orders $whereClause AND status IN ('paid', 'completed', 'PAID', 'COMPLETED')");
    $stmt->execute($params);
    $ordersData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $productSales = [];

    foreach ($ordersData as $orderRow) {
        $data = json_decode($orderRow['json_data'], true);
        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $item) {
                // Determine a key (SKU or Name)
                $key = $item['sku'] ?? $item['name'] ?? 'Unknown';
                $name = $item['name'] ?? 'Unknown Product';
                $price = (float) ($item['price'] ?? 0);
                $qty = (int) ($item['qty'] ?? 1);

                if (!isset($productSales[$key])) {
                    $productSales[$key] = [
                        'name' => $name,
                        'qty' => 0,
                        'revenue' => 0
                    ];
                }

                $productSales[$key]['qty'] += $qty;
                $productSales[$key]['revenue'] += ($price * $qty);
            }
        }
    }

    // Convert to indexed array and sort by revenue desc
    $salesByProduct = array_values($productSales);
    usort($salesByProduct, function ($a, $b) {
        return $b['revenue'] <=> $a['revenue'];
    });

    // 6. Recent Orders (Last 5)
    // 6. Recent Orders (Last 5)
    $stmt = $db->prepare("SELECT id, customer_name, total_amount, status, created_at, json_data FROM orders $whereClause ORDER BY created_at DESC LIMIT 5");
    $stmt->execute($params);
    $recentOrdersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recentOrders = [];
    foreach ($recentOrdersRaw as $order) {
        $data = json_decode($order['json_data'], true);
        $productName = 'Produto desconhecido';

        if (isset($data['products']) && is_array($data['products']) && count($data['products']) > 0) {
            $names = array_column($data['products'], 'name');
            $productName = $names[0] . (count($names) > 1 ? ' + ' . (count($names) - 1) . ' item(s)' : '');
        }

        $recentOrders[] = [
            'id' => $order['id'],
            'customer_name' => $order['customer_name'],
            'total_amount' => $order['total_amount'],
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'product_name' => $productName
        ];
    }

    echo json_encode([
        'revenue' => (float) $revenue,
        'total_orders' => (int) $totalOrders,
        'paid_orders' => (int) $paidOrders,
        'pending_orders' => (int) $pendingOrders,
        'pending_revenue' => (float) $pendingRevenue,
        'conversion_rate' => round($conversionRate, 2),
        'sales_by_product' => $salesByProduct,
        'recent_orders' => $recentOrders
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>