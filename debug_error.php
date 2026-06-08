<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Try to include the failing file
echo "<h1>Debugging auth/login.php</h1>";

// Check if file exists
$file = __DIR__ . '/auth/login.php';
echo "<p>Checking: $file</p>";

if (file_exists($file)) {
    echo "<p>✅ File exists</p>";
    
    // Get file contents to check for issues
    $content = file_get_contents($file);
    echo "<p>File size: " . strlen($content) . " bytes</p>";
    
    // Check for BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        echo "<p>⚠️ BOM detected at start of file</p>";
    }
    
    // Check first 100 chars
    echo "<p>First 100 chars: <pre>" . htmlspecialchars(substr($content, 0, 100)) . "</pre></p>";
    
    // Now try to include it
    try {
        include($file);
        echo "<p>✅ File included successfully</p>";
    } catch (Throwable $e) {
        echo "<p>❌ Error: " . $e->getMessage() . "</p>";
        echo "<p>Line: " . $e->getLine() . "</p>";
    }
} else {
    echo "<p>❌ File does NOT exist</p>";
}

// Check PHP error log
echo "<h2>Recent PHP Errors:</h2>";
$log = error_get_last();
if ($log) {
    echo "<pre>";
    print_r($log);
    echo "</pre>";
}
?>
