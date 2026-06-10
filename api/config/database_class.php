<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'kinas_group';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        
        // Write to a debug file
        $debug = "/tmp/db_debug.log";
        file_put_contents($debug, date('Y-m-d H:i:s') . " - Host: $host, Port: $port, DB: $dbname, User: $username\n", FILE_APPEND);
        
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            file_put_contents($debug, "✅ Connection SUCCESS\n", FILE_APPEND);
        } catch (PDOException $e) {
            file_put_contents($debug, "❌ Connection FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
            $this->connection = null;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
