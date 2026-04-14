<?php
require_once 'api/connection.php';
$db = (new Database())->getConnection();
$cols = $db->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $col) {
    echo $col['name'] . " (" . $col['type'] . ")\n";
}
