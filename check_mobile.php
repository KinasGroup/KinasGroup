<?php
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mobile Responsiveness Checker</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .good { color: green; }
        .bad { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Mobile Responsiveness Diagnostic</h1>
    
    <h2>Current Meta Tags (from your pages):</h2>
    <?php
    // Check main index.php
    $files = [
        'index.php',
        'kinas-automobile/index.php',
        'williams-connect-home/index.php',
        'divisions/kinas-marketplace/index.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            echo "<h3>$file</h3>";
            
            // Check for viewport meta tag
            if (preg_match('/<meta name="viewport"[^>]*>/i', $content, $matches)) {
                echo "<span class='good'>✅ Viewport tag found:</span><br>";
                echo "<code>" . htmlspecialchars($matches[0]) . "</code><br>";
            } else {
                echo "<span class='bad'>❌ Missing viewport meta tag!</span><br>";
            }
            
            // Check for responsive CSS
            if (strpos($content, 'media query') !== false || strpos($content, '@media') !== false) {
                echo "<span class='good'>✅ Media queries detected</span><br>";
            } else {
                echo "<span class='warning'>⚠️ No media queries found</span><br>";
            }
            
            // Check for flexbox/grid
            if (strpos($content, 'flex') !== false || strpos($content, 'grid') !== false) {
                echo "<span class='good'>✅ Modern CSS layout detected</span><br>";
            }
            
            echo "<br>";
        }
    }
    ?>
    
    <h2>Recommendations:</h2>
    <ul>
        <li>Add viewport meta tag to all pages</li>
        <li>Use CSS media queries for different screen sizes</li>
        <li>Make images responsive with max-width: 100%</li>
        <li>Use relative units (%, rem, vw) instead of fixed pixels</li>
        <li>Implement mobile-first design approach</li>
    </ul>
</body>
</html>
