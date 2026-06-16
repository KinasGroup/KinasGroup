<?php
require_once "autouploader.php";

$html = "<h1 style=\"color: #4CAF50;\">Test PDF</h1>" .
       "<p><strong>Generated at:</strong> " . date("Y-m-d H:i:s") . "</p>" .
       "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>" .
       "<hr>" .
       "<p>This PDF was created with mPDF via AutoUploader</p>" .
       "<p>The vendor folder and autoloader are working correctly!</p>";

$filename = generatePDF($html, "test-output.pdf");

if (file_exists($filename)) {
    echo "\n✅ SUCCESS!\n";
    echo "📄 PDF created: $filename\n";
    echo "📊 File size: " . filesize($filename) . " bytes\n";
    echo "📍 Location: " . __DIR__ . "/$filename\n";
} else {
    echo "\n❌ Failed to create PDF\n";
}
?>
