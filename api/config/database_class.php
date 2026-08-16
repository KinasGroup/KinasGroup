<?php

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
        $port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '3306');
        $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: '');
        $username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: '');
        $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

        if ($dbname === '') {
            error_log('Database connection failed: DB_NAME is not configured.');
            $this->connection = null;
            return;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            $this->connection = null;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): ?PDO
    {
        return $this->connection;
    }
}
