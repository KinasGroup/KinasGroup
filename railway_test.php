<?php
header('Content-Type: text/plain');

echo "=== RAILWAY PRODUCTION DEBUG ===\n\n";

// What variables are actually set?
echo "Direct getenv() checks:\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "\n";

// Check if .env file exists
echo "\n.env file exists: " . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO') . "\n";

// Try direct PDO connection
echo "\nTrying direct PDO connection...\n";
try {
    $host = getenv('DB_HOST') ?: 'mainline.proxy.rlwy.net';
    $port = getenv('DB_PORT') ?: '50184';
    $dbname = getenv('DB_NAME') ?: 'kinas_group';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'qUqBNxCgzyWDaKhQvomKbyvBQJwvQpKo';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Direct PDO connection SUCCESSFUL\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "✅ Users count: " . $stmt->fetchColumn() . "\n";
    
} catch (Exception $e) {
    echo "❌ Direct PDO failed: " . $e->getMessage() . "\n";
}
