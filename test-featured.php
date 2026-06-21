<?php
/**
 * Test Featured Algorithm
 * Shows how listings are scored without updating the database
 */

require_once 'api/config/database.php';
require_once 'includes/featured-algorithm.php';

$db = Database::getInstance()->getConnection();
$algorithm = new FeaturedAlgorithm($db);

echo "🧪 Testing Featured Algorithm\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Get all listings with scores
$featured = $algorithm->getFeaturedListings(20);

echo "📊 Top 20 Listings by Score:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
printf("%-4s %-12s %-25s %-10s %-8s %-8s\n", "Rank", "Division", "Title", "Price", "Views", "Score");
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

foreach ($featured as $index => $listing) {
    $rank = $index + 1;
    $division = ucfirst($listing['division']);
    $title = substr($listing['title'], 0, 24) . (strlen($listing['title']) > 24 ? '...' : '');
    $price = '₦' . number_format($listing['price'] / 1000, 0) . 'k';
    $views = $listing['views'] ?? 0;
    $score = $listing['score'] ?? 0;
    
    printf("%-4d %-12s %-25s %-10s %-8d %-8.1f\n", 
        $rank, $division, $title, $price, $views, $score);
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📝 Scoring Criteria:\n";
echo "  - Views (30%): Popularity based on view count\n";
echo "  - Recency (25%): Newer listings get higher scores\n";
echo "  - Price Value (20%): Best value for money\n";
echo "  - Completeness (15%): Complete listing with images\n";
echo "  - Engagement (10%): Messages and inquiries\n";

echo "\n🚀 To update the database with new featured listings:\n";
echo "   php update-featured.php\n";
