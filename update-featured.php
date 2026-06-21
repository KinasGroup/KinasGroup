<?php
/**
 * Update Featured Listings
 * Run this script daily or on-demand to refresh featured listings
 */

require_once 'api/config/database.php';
require_once 'includes/featured-algorithm.php';

$db = Database::getInstance()->getConnection();
$algorithm = new FeaturedAlgorithm($db);

echo "🔄 Updating Featured Listings...\n\n";

// Get the current top listings
echo "📊 Calculating scores for all listings...\n";
$featured = $algorithm->getFeaturedListings(8);

echo "\n🏆 Top Featured Listings:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
foreach ($featured as $index => $listing) {
    $rank = $index + 1;
    $division = ucfirst($listing['division']);
    echo "{$rank}. [{$division}] {$listing['title']}\n";
    echo "   💰 ₦" . number_format($listing['price']) . " | 👁️ " . ($listing['views'] ?? 0) . " views\n";
    echo "   ⭐ Score: " . ($listing['score'] ?? 0) . "/100\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Update the database
echo "\n💾 Updating database featured flags...\n";
$updated = $algorithm->updateFeaturedListings(8);

echo "✅ Updated $updated listings as featured!\n\n";
echo "📝 To run this automatically daily, add to cron:\n";
echo "   0 0 * * * php /path/to/update-featured.php\n";
