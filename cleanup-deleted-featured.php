<?php
// cleanup-deleted-featured.php - Run once to clean up existing data

require_once 'api/config/database.php';

// Security: Only allow from localhost or require admin login
// Uncomment one of these protection methods:

// Option A: Only allow if logged in as admin
// require_once 'includes/session.php';
// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
//     die('Admin access required.');
// }

// Option B: Only allow from local IP
// if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
//     die('Access denied.');
// }

$db = Database::getInstance()->getConnection();

$tables = ['car_listings', 'property_listings', 'solar_listings', 'marketplace_listings'];
$totalUpdated = 0;

echo "<h1>Featured Cleanup Script</h1>";
echo "<pre>";

foreach ($tables as $table) {
    try {
        // Check if table has a 'featured' column
        $checkCol = $db->query("SHOW COLUMNS FROM $table LIKE 'featured'");
        if ($checkCol->rowCount() > 0) {
            // Reset featured flag for any deleted/archived listings
            $stmt = $db->exec("UPDATE $table SET featured = 0 WHERE status = 'deleted' OR status = 'archived' OR status = 'inactive'");
            echo "Updated $table: $stmt rows cleared<br>";
            $totalUpdated += $stmt;
        } else {
            echo "Table $table has no 'featured' column. Skipping.<br>";
        }
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "<br>";
    }
}

// Also check for featured_cache table
try {
    $db->exec("DELETE FROM featured_cache WHERE listing_id NOT IN (SELECT id FROM car_listings)");
    $db->exec("DELETE FROM featured_cache WHERE listing_id NOT IN (SELECT id FROM property_listings)");
    $db->exec("DELETE FROM featured_cache WHERE listing_id NOT IN (SELECT id FROM solar_listings)");
    $db->exec("DELETE FROM featured_cache WHERE listing_id NOT IN (SELECT id FROM marketplace_listings)");
    echo "Featured cache cleared<br>";
} catch (Exception $e) {
    echo "Featured cache table not found or error: " . $e->getMessage() . "<br>";
}

echo "</pre>";
echo "<h2>Done! Total featured flags cleared: $totalUpdated</h2>";
echo "<p>You can now delete this file.</p>";
?>
