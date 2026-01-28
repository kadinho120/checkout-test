<?php
require_once __DIR__ . '/connection.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Check if column exists
    $cols = $db->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
    $hasTheme = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'theme') {
            $hasTheme = true;
            break;
        }
    }

    if (!$hasTheme) {
        $db->exec("ALTER TABLE products ADD COLUMN theme TEXT DEFAULT 'dark'");
        echo "Column 'theme' added successfully.\n";
    } else {
        echo "Column 'theme' already exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>