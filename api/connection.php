<?php
class Database {
    private $db_file = __DIR__ . '/../database/database.sqlite';
    private $conn;

    public function getConnection() {
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

        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
