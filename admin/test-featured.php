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

<!-- ============================================================
     RESPONSIVE FIX - Added container wrapper
     ============================================================ -->
<style>
.je-dash-shell {
    max-width: 100% !important;
    overflow-x: hidden !important;
}
.je-dash-main {
    overflow-x: hidden !important;
    width: 100% !important;
    max-width: 100% !important;
    padding: 15px !important;
}
.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    width: 100% !important;
}
.je-table {
    min-width: 500px !important;
    width: 100% !important;
}
@media (max-width: 768px) {
    .je-dash-main { padding: 10px !important; }
    .je-table th, .je-table td { padding: 6px 8px; font-size: 11px; }
    .je-table th:nth-child(1), .je-table td:nth-child(1) { display: none; }
}
@media (max-width: 480px) {
    .je-table th:nth-child(3), .je-table td:nth-child(3) { display: none; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
    <?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>

    <main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
        <div class="je-dash-header" style="flex-wrap: wrap;">
            <div>
                <h1><i class="fas fa-chart-line" style="color: #C6A43F;"></i> Test Featured Algorithm</h1>
                <p>Preview how listings are scored without updating the database</p>
            </div>
        </div>

        <div class="je-panel" style="overflow-x: hidden;">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-info-circle" style="color: #C6A43F;"></i> How Scoring Works
                </div>
            </div>
            <div class="je-panel-body" style="overflow-x: hidden;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px; margin-bottom: 16px;">
                    <div style="background: #f8f8f8; padding: 10px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">30%</div>
                        <div style="font-size: 11px; color: #666;">Views</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 10px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">25%</div>
                        <div style="font-size: 11px; color: #666;">Recency</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 10px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">20%</div>
                        <div style="font-size: 11px; color: #666;">Price Value</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 10px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">15%</div>
                        <div style="font-size: 11px; color: #666;">Completeness</div>
                    </div>
                    <div style="background: #f8f8f8; padding: 10px; border-radius: 8px; text-align: center;">
                        <div style="font-weight: 700; color: #C6A43F;">10%</div>
                        <div style="font-size: 11px; color: #666;">Engagement</div>
                    </div>
                </div>
                <p style="font-size: 13px; color: #666; margin: 0;">
                    <i class="fas fa-info-circle"></i> This is a preview only. No changes are made to the database.
                </p>
            </div>
        </div>

        <div class="je-panel" style="overflow-x: hidden;">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-list-ul" style="color: #C6A43F;"></i> Top 20 Listings by Score
                </div>
            </div>
            <div class="je-panel-body" style="overflow-x: hidden;">
                <?php
                $featured = $algorithm->getFeaturedListings(20);
                if (empty($featured)):
                ?>
                    <div class="je-panel-empty">
                        <i class="fas fa-chart-line"></i>
                        <p>No listings found to score.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%;">
                    <table class="je-table" style="min-width: 500px; width: 100%;">
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
