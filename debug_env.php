<?php
// Temporary debug file
header('Content-Type: text/plain');

echo "=== ENVIRONMENT DEBUG ===\n\n";

// Check if dotenv loads
require_once 'includes/dotenv.php';
echo "✅ dotenv loaded\n";

// Check environment variables
$vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT'];
foreach ($vars as $var) {
    $val = getenv($var) ?: $_ENV[$var] ?? 'NOT SET';
    if ($var == 'DB_PASS' && $val != 'NOT SET') {
        $val = '***HIDDEN***';
    }
    echo "$var: $val\n";
}

// Test database connection
require_once 'api/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    if ($db) {
        echo "\n✅ Database connection successful\n";
        
        // Test query
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo "✅ Users table has $count records\n";
    } else {
        echo "\n❌ Database connection returned null\n";
    }
} catch (Exception $e) {
    echo "\n❌ Database error: " . $e->getMessage() . "\n";
}
