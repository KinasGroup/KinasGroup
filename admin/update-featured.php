<?php
require_once '../includes/session.php';
require_once '../api/config/database.php';
require_once '../includes/featured-algorithm.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Admin access required. Please login as admin.");
}

$db = Database::getInstance()->getConnection();
$algorithm = new FeaturedAlgorithm($db);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Featured Listings</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0A0A0A; font-family: 'Prata', serif; }
        .success { color: #2E7D32; }
        .error { color: #C62828; }
        .listing-item { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .listing-item:last-child { border-bottom: none; }
        .score { background: #C6A43F; color: #0A0A0A; padding: 2px 10px; border-radius: 12px; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: 600; margin: 20px 10px 0 0; }
        .btn:hover { background: #A8882E; }
        .stats { background: #f0f0f0; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .btn-dark { background: #0A0A0A; color: white; }
        .btn-dark:hover { background: #333; }
    </style>
</head>
<body>
<div class="card">
<h1>🔄 Update Featured Listings</h1>
<p>This will automatically select the best listings to feature based on:</p>
<ul>
<li><strong>Views (30%)</strong> - Popular listings</li>
<li><strong>Recency (25%)</strong> - Newer listings</li>
<li><strong>Price Value (20%)</strong> - Best value for money</li>
<li><strong>Completeness (15%)</strong> - Complete listings with images</li>
<li><strong>Engagement (10%)</strong> - Messages and inquiries</li>
</ul>
<hr>
<?php
if (isset($_GET['run']) && $_GET['run'] == '1') {
    echo "<h2>📊 Running Featured Algorithm...</h2>";
    try {
        $featured = $algorithm->getFeaturedListings(8);
        echo "<div class='stats'><strong>🏆 Top 8 Featured Listings:</strong><br><br>";
        foreach ($featured as $index => $listing) {
            $rank = $index + 1;
            $division = ucfirst($listing['division']);
            echo "<div class='listing-item'>";
            echo "<span><strong>{$rank}.</strong> [{$division}] " . htmlspecialchars($listing['title']) . "</span>";
            echo "<span>💰 ₦" . number_format($listing['price']) . " | ⭐ <span class='score'>" . ($listing['score'] ?? 0) . "</span></span>";
            echo "</div>";
        }
        echo "</div>";
        echo "<h3>💾 Updating Database...</h3>";
        $updated = $algorithm->updateFeaturedListings(8);
        echo "<p class='success'>✅ Successfully updated <strong>{$updated}</strong> listings as featured!</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    echo "<a href='update-featured.php' class='btn'>🔄 Run Again</a>";
    echo "<a href='dashboard.php' class='btn btn-dark'>← Back to Dashboard</a>";
} else {
    echo "<p>Click the button below to automatically select and update featured listings.</p>";
    echo "<a href='?run=1' class='btn'>🚀 Run Featured Algorithm</a>";
    echo "<a href='dashboard.php' class='btn btn-dark'>← Back to Dashboard</a>";
}
?>
</div>
</body>
</html>
