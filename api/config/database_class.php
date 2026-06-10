<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // HARDCODED for Railway production
        $host = 'mainline.proxy.rlwy.net';
        $port = '50184';
        $dbname = 'kinas_group';
        $username = 'root';
        $password = 'qUqBNxCgzyWDaKhQvomKbyvBQJwvQpKo';
        
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
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
