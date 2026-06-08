<?php
header('Content-Type: text/plain');
echo "=== Adding Responsive CSS & Viewport to All Pages ===\n\n";

// The code to add to each page
$responsiveCode = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="/assets/css/responsive.css">
';

$pages = [
    'index.php',
    'kinas-automobile/index.php',
    'williams-connect-home/index.php',
    'divisions/kinas-marketplace/index.php'
];

$updated = 0;

foreach ($pages as $page) {
    if (!file_exists($page)) {
        echo "✗ File not found: $page\n";
        continue;
    }
    
    $content = file_get_contents($page);
    
    // Check if page already has responsive code
    if (strpos($content, 'responsive.css') !== false && strpos($content, 'viewport') !== false) {
        echo "✓ Already has responsive code: $page\n";
        continue;
    }
    
    // Check if page has HTML structure
    if (strpos($content, '<head>') !== false) {
        // Add responsive code after <head> tag
        $newContent = str_replace('<head>', "<head>\n" . $responsiveCode, $content);
        file_put_contents($page, $newContent);
        echo "✓ Added responsive code to: $page\n";
        $updated++;
    } 
    elseif (strpos($content, '<?php') !== false && strpos($content, '<!DOCTYPE') === false) {
        // PHP file without HTML head - wrap with HTML
        $newContent = "<?php\n// Auto-added responsive header\ndefined('RESPONSIVE_ADDED') or define('RESPONSIVE_ADDED', true);\n?>\n" . 
                      "<!DOCTYPE html>\n<html>\n<head>\n" . $responsiveCode . "</head>\n<body>\n" . $content . "\n</body>\n</html>";
        file_put_contents($page, $newContent);
        echo "⚠️  Wrapped with HTML: $page\n";
        $updated++;
    }
    else {
        echo "❌ Could not add responsive code to: $page (no <head> tag found)\n";
    }
}

echo "\n--- Summary ---\n";
echo "Updated: $updated pages\n";

// Verify the changes
echo "\n=== Verification ===\n";
foreach ($pages as $page) {
    $content = file_get_contents($page);
    if (strpos($content, 'responsive.css') !== false) {
        echo "✅ $page - Now has responsive.css\n";
    } else {
        echo "❌ $page - Still missing responsive.css\n";
    }
}

echo "\n✅ Done! Delete this file now for security.\n";
