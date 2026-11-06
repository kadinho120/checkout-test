<?php
require_once 'auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$account = $_GET['account'] ?? '';
$level = $_GET['level'] ?? '';
$since = $_GET['since'] ?? '';
$until = $_GET['until'] ?? '';
$fields = ['campaigns' => 'meta_campaign_id', 'adsets' => 'meta_adset_id', 'ads' => 'meta_ad_id'];

if (empty($_SESSION['meta_ads_token']) || !is_string($account) || !is_string($level)
    || !isset($fields[$level]) || !isset($_SESSION['meta_ads_objects'][$account][$level])
    || !is_string($since) || !is_string($until)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $since) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $until)
    || $since > $until) {
    http_response_code(400);
    echo json_encode(['error' => 'Consulta indisponível.']);
    exit;
}
$visibleIds = array_fill_keys($_SESSION['meta_ads_objects'][$account][$level], true);
session_write_close();
require_once '../api/connection.php';
$db = (new Database())->getConnection();
$path = '$.tracking.' . $fields[$level];
$stmt = $db->prepare("
    SELECT CAST(json_extract(CASE WHEN json_valid(json_data) THEN json_data END, ?) AS TEXT) AS entity_id,
           COUNT(*) AS orders, SUM(total_amount) AS revenue
    FROM orders
    WHERE status = 'paid'
      AND COALESCE(NULLIF(updated_at, ''), created_at) >= ?
      AND COALESCE(NULLIF(updated_at, ''), created_at) < date(?, '+1 day')
    GROUP BY entity_id
");
$stmt->execute([$path, $since, $until]);
$rows = [];
foreach ($stmt->fetchAll() as $row) {
    if (isset($visibleIds[$row['entity_id']])) {
        $rows[$row['entity_id']] = ['orders' => (int) $row['orders'], 'revenue' => (float) $row['revenue']];
    }
}
echo json_encode((object) $rows);
