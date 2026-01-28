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
                $whereClause .= " AND date(created_at) = :today";
                $params[':today'] = $today;
                break;
            case 'yesterday':
                $whereClause .= " AND date(created_at) = date('now', '-1 day')";
                break;
            case 'last7':
                $whereClause .= " AND created_at >= date('now', '-7 days')";
                break;
            case 'last14':
                $whereClause .= " AND created_at >= date('now', '-14 days')";
                break;
            case 'last30':
                $whereClause .= " AND created_at >= date('now', '-30 days')";
                break;
            case 'this_month':
                $whereClause .= " AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')";
                break;
            case 'this_year':
                $whereClause .= " AND strftime('%Y', created_at) = strftime('%Y', 'now')";
                break;
            default:
                // 'all' or fallback -> No filter
                break;
        }
    }

    // 1. Total Revenue (Paid)
    // Using broad paid status check
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

    // 4. Conversion Rate
    $conversionRate = $totalOrders > 0 ? ($paidOrders / $totalOrders) * 100 : 0;

    // 5. Recent Orders (Last 5)
    // We filter these too as requested implies filtering the dashboard views. 
    // If not desired, remove $whereClause from here.
    $stmt = $db->prepare("SELECT id, customer_name, total_amount, status, created_at FROM orders $whereClause ORDER BY created_at DESC LIMIT 5");
    $stmt->execute($params);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'revenue' => (float) $revenue,
        'total_orders' => (int) $totalOrders,
        'paid_orders' => (int) $paidOrders,
        'conversion_rate' => round($conversionRate, 2),
        'recent_orders' => $recentOrders
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>