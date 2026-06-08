<?php
// Try to use your app's database configuration
$config_paths = [
    __DIR__ . '/api/config/database.php',
    __DIR__ . '/config/database.php',
    __DIR__ . '/includes/database.php'
];

$db = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "Loading config from: $path\n";
        require_once $path;
        break;
    }
}

if (class_exists('Database')) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->query("SELECT DATABASE()");
        echo "✓ Connected to: " . $stmt->fetchColumn() . "\n\n";
        
        // List tables
        $stmt = $conn->query("SHOW TABLES");
        echo "Tables in database:\n";
        $tables = [];
        while($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
            echo "- " . $row[0] . "\n";
        }
        
        // Check car_listings
        if (in_array('car_listings', $tables)) {
            echo "\n✓ car_listings exists\n";
            
            // Check columns
            $stmt = $conn->query("DESCRIBE car_listings");
            $columns = [];
            while($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $col['Field'];
            }
            
            if (in_array('brand', $columns)) {
                echo "✓ brand column exists\n";
            } else {
                echo "✗ brand column missing\n";
            }
        } else {
            echo "\n✗ car_listings table missing\n";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database class not found\n";
    
    // Try direct connection with hardcoded values (for testing)
    echo "\nTrying direct connection...\n";
    try {
        $pdo = new PDO(
            "mysql:host=mysql.railway.internal;port=3306;dbname=railway",
            "root",
            "qUqBNxCgzyWDaKhQvomKbyvBQJwvQpKo"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT DATABASE()");
        echo "✓ Direct connection to: " . $stmt->fetchColumn() . "\n";
    } catch (PDOException $e) {
        echo "Direct connection failed: " . $e->getMessage() . "\n";
        echo "\nThis is expected from codespace - you need to run this on Railway!\n";
    }
}
