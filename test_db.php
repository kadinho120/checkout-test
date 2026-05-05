<?php
require_once 'api/connection.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT * FROM orders LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
