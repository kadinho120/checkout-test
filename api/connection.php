<?php
class Database
{
    private $db_file = __DIR__ . '/../database/database.sqlite';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;

        try {
            // Ensure database directory exists
            $db_dir = dirname($this->db_file);
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0755, true);
            }

            $this->conn = new PDO("sqlite:" . $this->db_file);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable foreign keys
            $this->conn->exec("PRAGMA foreign_keys = ON;");

            // Temporary: Initialize schema if table users missing
            // In a real migration system, this would be separate
            $result = $this->conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            if (!$result->fetch()) {
                $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
                if ($schema) {
                    $this->conn->exec($schema);
                }
            }

            // --- AUTO-MIGRATION FIX (Self-Healing) ---
            // Fixes "no such column: updated_at" for existing installations
            $cols = $this->conn->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
            $hasUpdatedAt = false;
            foreach ($cols as $col) {
                if ($col['name'] === 'updated_at') {
                    $hasUpdatedAt = true;
                    break;
                }
            }
            if (!$hasUpdatedAt) {
                $this->conn->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME;");
            }

            // Check for request_email and request_phone in products
            $pCols = $this->conn->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
            $hasReqEmail = false;
            $hasReqPhone = false;
            foreach ($pCols as $col) {
                if ($col['name'] === 'request_email')
                    $hasReqEmail = true;
                if ($col['name'] === 'request_phone')
                    $hasReqPhone = true;
            }
            if (!$hasReqEmail) {
                $this->conn->exec("ALTER TABLE products ADD COLUMN request_email INTEGER DEFAULT 1;");
            }
            if (!$hasReqPhone) {
                $this->conn->exec("ALTER TABLE products ADD COLUMN request_phone INTEGER DEFAULT 1;");
            }

            // Check for token in pixels verification
            $pixCols = $this->conn->query("PRAGMA table_info(pixels)")->fetchAll(PDO::FETCH_ASSOC);
            $hasToken = false;
            foreach ($pixCols as $col) {
                if ($col['name'] === 'token')
                    $hasToken = true;
            }
            if (!$hasToken) {
                $this->conn->exec("ALTER TABLE pixels ADD COLUMN token TEXT;");
            }

            // Check for Evolution API columns in products
            $prodCols = $this->conn->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
            $hasEvoInstance = false;
            foreach ($prodCols as $col) {
                if ($col['name'] === 'evolution_instance')
                    $hasEvoInstance = true;
            }
            if (!$hasEvoInstance) {
                $this->conn->exec("ALTER TABLE products ADD COLUMN evolution_instance TEXT;");
                $this->conn->exec("ALTER TABLE products ADD COLUMN evolution_token TEXT;");
                $this->conn->exec("ALTER TABLE products ADD COLUMN evolution_url TEXT;");
                $this->conn->exec("ALTER TABLE products ADD COLUMN deliverable_type TEXT;");
                $this->conn->exec("ALTER TABLE products ADD COLUMN deliverable_text TEXT;");
                $this->conn->exec("ALTER TABLE products ADD COLUMN deliverable_file TEXT;");
            }

            // Check for Evolution API columns in order_bumps
            $bumpCols = $this->conn->query("PRAGMA table_info(order_bumps)")->fetchAll(PDO::FETCH_ASSOC);
            $hasBumpDeliv = false;
            foreach ($bumpCols as $col) {
                if ($col['name'] === 'deliverable_type')
                    $hasBumpDeliv = true;
            }
            if (!$hasBumpDeliv) {
                $this->conn->exec("ALTER TABLE order_bumps ADD COLUMN deliverable_type TEXT;");
                $this->conn->exec("ALTER TABLE order_bumps ADD COLUMN deliverable_text TEXT;");
                $this->conn->exec("ALTER TABLE order_bumps ADD COLUMN deliverable_file TEXT;");
            }
            // -----------------------------------------

        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
