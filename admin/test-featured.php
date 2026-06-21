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
    <title>Test Featured Algorithm</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0A0A0A; font-family: 'Prata', serif; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #0A0A0A; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8f8f8; }
        .score-high { color: #2E7D32; font-weight: bold; }
        .score-medium { color: #F57C00; font-weight: bold; }
        .score-low { color: #C62828; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: 600; margin: 20px 10px 0 0; }
        .btn:hover { background: #A8882E; }
        .btn-dark { background: #0A0A0A; color: white; }
        .btn-dark:hover { background: #333; }
    </style>
</head>
<body>
<div class="card">
<h1>🧪 Test Featured Algorithm</h1>
<p>Shows how listings are scored without updating the database.</p>
<hr>
<?php
$featured = $algorithm->getFeaturedListings(20);
echo "<h2>📊 Top 20 Listings by Score</h2>";
echo "<table>";
echo "<tr><th>Rank</th><th>Division</th><th>Title</th><th>Price</th><th>Views</th><th>Score</th></tr>";
foreach ($featured as $index => $listing) {
    $rank = $index + 1;
    $division = ucfirst($listing['division']);
    $score = $listing['score'] ?? 0;
    $scoreClass = $score >= 70 ? 'score-high' : ($score >= 40 ? 'score-medium' : 'score-low');
    echo "<tr>";
    echo "<td><strong>{$rank}</strong></td>";
    echo "<td>{$division}</td>";
    echo "<td>" . htmlspecialchars($listing['title']) . "</td>";
    echo "<td>₦" . number_format($listing['price']) . "</td>";
    echo "<td>" . ($listing['views'] ?? 0) . "</td>";
    echo "<td class='{$scoreClass}'>{$score}</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>📝 Scoring Criteria:</strong></p>";
echo "<ul>";
echo "<li><strong>Views (30%):</strong> Popularity based on view count</li>";
echo "<li><strong>Recency (25%):</strong> Newer listings get higher scores</li>";
echo "<li><strong>Price Value (20%):</strong> Best value for money</li>";
echo "<li><strong>Completeness (15%):</strong> Complete listing with images</li>";
echo "<li><strong>Engagement (10%):</strong> Messages and inquiries</li>";
echo "</ul>";
echo "<a href='update-featured.php' class='btn'>🚀 Update Featured Listings</a>";
echo "<a href='dashboard.php' class='btn btn-dark'>← Back to Dashboard</a>";
?>
</div>
</body>
</html>
