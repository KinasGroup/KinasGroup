<?php
// check-statuses.php - Run once to see status values

require_once 'api/config/database.php';
$db = Database::getInstance()->getConnection();

$tables = ['car_listings', 'property_listings', 'solar_listings', 'marketplace_listings'];

echo "<h1>Listing Status Values</h1><pre>";

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT DISTINCT status, COUNT(*) as count FROM $table GROUP BY status");
        echo "\n=== $table ===\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['status'] . ": " . $row['count'] . " listings\n";
        }
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}

echo "</pre>";
?>
