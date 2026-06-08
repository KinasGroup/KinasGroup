<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<h1 style='color:green'>PHP IS WORKING!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
try {
    require_once __DIR__ . '/api/config/database.php';
    if (isset($pdo)) {
        echo "<p style='color:green'>✅ Database connection works!</p>";
    } else {
        echo "<p style='color:red'>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
