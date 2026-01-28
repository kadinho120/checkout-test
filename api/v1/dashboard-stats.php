<?php
// api/v1/dashboard-stats.php

header('Content-Type: application/json');
require_once __DIR__ . '/../connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Total Revenue (Approved/Paid orders)
    // Assuming status can be 'paid' (from Woovi webhook) or 'completed'
    $revenueStmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('paid', 'completed', 'PAID', 'COMPLETED')");
    $revenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Total Orders (All time)
    $totalOrdersStmt = $db->query("SELECT COUNT(*) as count FROM orders");
    $totalOrders = $totalOrdersStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Paid Orders (Conversion)
    $paidOrdersStmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('paid', 'completed', 'PAID', 'COMPLETED')");
    $paidOrders = $paidOrdersStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Conversion Rate
    $conversionRate = 0;
    if ($totalOrders > 0) {
        $conversionRate = ($paidOrders / $totalOrders) * 100;
    }

    // 5. Recent Orders (Last 5)
    $recentOrdersStmt = $db->query("SELECT id, customer_name, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

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