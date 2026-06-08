<?php
/**
 * Database Singleton Class for KINAS GROUP
 * This class provides a single database connection instance
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Get database credentials from environment or config
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'kinas_group';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        
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
?>
