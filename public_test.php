<?php
// Temporary test file - DELETE AFTER USE
header('Content-Type: text/plain');

echo "=== LOGIN REGRESSION TEST ===\n\n";

try {
    require_once __DIR__ . '/includes/dotenv.php';
    require_once __DIR__ . '/api/config/database.php';
    
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected\n";
    
    $stmt = $db->query("SELECT email, role, verified FROM users WHERE email IN ('info7admin@gmail.com', 'listing@kinas-group.com')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "✅ User found: " . $user['email'] . " (role: " . $user['role'] . ", verified: " . $user['verified'] . ")\n";
    }
    
    $adminHash = '$2y$10$A4Ho5xnCOTDIU9tIOaJ83.rSg8cJAMMEBqz1ZOADn.GNbodrHy9ty';
    $testPassword = 'Admin@Kinas2025!';
    
    if (password_verify($testPassword, $adminHash)) {
        echo "✅ Admin password verification: SUCCESS\n";
    } else {
        echo "❌ Admin password verification: FAILED\n";
    }
    
    echo "\n✅ Login system is ready\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}<?php
// Temporary test file - DELETE AFTER USE
header('Content-Type: text/plain');

echo "=== LOGIN REGRESSION TEST ===\n\n";

try {
    require_once __DIR__ . '/includes/dotenv.php';
    require_once __DIR__ . '/api/config/database.php';
    
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected\n";
    
    $stmt = $db->query("SELECT email, role, verified FROM users WHERE email IN ('info7admin@gmail.com', 'listing@kinas-group.com')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "✅ User found: " . $user['email'] . " (role: " . $user['role'] . ", verified: " . $user['verified'] . ")\n";
    }
    
    $adminHash = '$2y$10$A4Ho5xnCOTDIU9tIOaJ83.rSg8cJAMMEBqz1ZOADn.GNbodrHy9ty';
    $testPassword = 'Admin@Kinas2025!';
    
    if (password_verify($testPassword, $adminHash)) {
        echo "✅ Admin password verification: SUCCESS\n";
    } else {
        echo "❌ Admin password verification: FAILED\n";
    }
    
    echo "\n✅ Login system is ready\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
