<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Kinas Group Debug</h1>";

// Test 1: PHP is working
echo "<p>✅ PHP is running</p>";

// Test 2: Check if database.php exists and works
echo "<p>Testing database.php...</p>";
if (file_exists(__DIR__ . '/api/config/database.php')) {
    echo "<p>✅ database.php found</p>";
    require_once __DIR__ . '/api/config/database.php';
    if (isset($pdo)) {
        echo "<p>✅ Database connection successful</p>";
    } else {
        echo "<p>❌ Database connection failed - \$pdo not set</p>";
    }
} else {
    echo "<p>❌ database.php NOT found at: " . __DIR__ . "/api/config/database.php</p>";
}

// Test 3: Check critical files
$critical_files = [
    'templates/header.php',
    'templates/footer.php',
    'includes/auth.php',
    'includes/functions.php',
    'includes/csrf.php'
];

echo "<p>Checking critical files:</p><ul>";
foreach ($critical_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<li>✅ $file</li>";
    } else {
        echo "<li>❌ $file MISSING</li>";
    }
}
echo "</ul>";

// Test 4: Check PHP syntax of main files
echo "<p>Checking PHP syntax:</p><ul>";
$test_files = [
    'auth/login.php',
    'user/dashboard.php',
    'user/my-inquiries.php'
];

foreach ($test_files as $file) {
    $output = shell_exec("php -l " . __DIR__ . "/$file 2>&1");
    if (strpos($output, "No syntax errors") !== false) {
        echo "<li>✅ $file - Syntax OK</li>";
    } else {
        echo "<li>❌ $file - Syntax Error:<br><pre>" . htmlspecialchars($output) . "</pre></li>";
    }
}
echo "</ul>";
?>
