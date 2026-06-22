<?php
/**
 * Admin: Test Featured Algorithm
 * Shows how listings are scored without updating the database
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';
require_once '../includes/featured-algorithm.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$algorithm = new FeaturedAlgorithm($db);

$pageTitle = 'Test Featured Algorithm - Admin';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-crown"></i> KINAS GROUP
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="agents.php"><i class="fas fa-user-tie"></i> Agents</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> Listings</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li class="je-dash-nav-heading">FEATURED MANAGEMENT</li>
            <li><a href="test-featured.php" class="is-active"><i class="fas fa-chart-line"></i> Test Algorithm</a></li>
            <li><a href="update-featured.php"><i class="fas fa-sync-alt"></i> Update Featured</a></li>
            <li class="je-dash-nav-divider"></li>
            <li><a href="/"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-chart-line" style="color: #C6A43F;"></i> Test Featured Algorithm</h1>
                <p>Preview how listings are scored without updating the database</p>
            </div>
        </div>

        <div class="je-panel">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-info-circle" style="color: #C6A43F;"></i> How Scoring Works
                </div>
            </div>
            <div class="je-panel-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px;">
                    <div style="background: #f8f8f8; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">30%</div>
                        <div style="font-size: 12px; color: #666;">Views</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">25%</div>
                        <div style="font-size: 12px; color: #666;">Recency</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">20%</div>
                        <div style="font-size: 12px; color: #666;">Price Value</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">15%</div>
                        <div style="font-size: 12px; color: #666;">Completeness</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 12px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">10%</div>
                        <div style="font-size: 12px; color: #666;">Engagement</div>
                    </div>
                </div>
                <p style="font-size: 13px; color: #666; margin: 0;">
                    <i class="fas fa-info-circle"></i> This is a preview only. No changes are made to the database.
                </p>
            </div>
        </div>

        <div class="je-panel">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-list-ul" style="color: #C6A43F;"></i> Top 20 Listings by Score
                </div>
            </div>
            <div class="je-panel-body">
                <?php
                $featured = $algorithm->getFeaturedListings(20);
                if (empty($featured)):
                ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-chart-line"></i>
                        <p>No listings found to score.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="je-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Division</th>
                                <th>Title</th>
                                <th>Price</th>
                                <th>Views</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($featured as $index => $listing): 
                                $rank = $index + 1;
                                $division = ucfirst($listing['division']);
                                $score = $listing['score'] ?? 0;
                                $scoreColor = $score >= 70 ? '#2E7D32' : ($score >= 40 ? '#F57C00' : '#C62828');
                            ?>
                            <tr>
                                <td><strong>#<?php echo $rank; ?></strong></td>
                                <td><?php echo $division; ?></td>
                                <td><strong><?php echo htmlspecialchars($listing['title']); ?></strong></td>
                                <td>₦<?php echo number_format($listing['price']); ?></td>
                                <td><?php echo number_format($listing['views'] ?? 0); ?></td>
                                <td>
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-weight: 700; font-size: 14px; background: <?php echo $scoreColor; ?>20; color: <?php echo $scoreColor; ?>;">
                                        <?php echo $score; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
