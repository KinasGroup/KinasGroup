<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Admin: Update Featured Listings
 * Runs the algorithm and updates the database
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

$success = false;
$featured = [];
$updated = 0;

// Check if the action was triggered
$action = isset($_GET['action']) ? $_GET['action'] : '';
$run = isset($_GET['run']) ? $_GET['run'] : '';

if ($action === 'run' || $run === '1') {
    // Get the featured listings
    $featured = $algorithm->getFeaturedListings(8);
    // Update the database
    $updated = $algorithm->updateFeaturedListings(8);
    $success = true;
}

$pageTitle = 'Update Featured Listings - Admin';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>

    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-sync-alt" style="color: #C6A43F;"></i> Update Featured Listings</h1>
                <p>Run the featured algorithm and update the database</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="je-banner is-success">
                <i class="je-banner-icon fas fa-check-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-title">Featured Listings Updated!</div>
                    <div class="je-banner-text">
                        Successfully marked <strong><?php echo $updated; ?></strong> listings as featured.
                        <?php if ($updated > 0): ?>
                            The home page will now display these featured listings.
                        <?php else: ?>
                            No listings were eligible for featuring.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($featured)): ?>
                <div class="je-panel" style="margin-top: 20px;">
                    <div class="je-panel-header">
                        <div class="je-panel-title">
                            <i class="fas fa-star" style="color: #C6A43F;"></i> Featured Listings Set
                        </div>
                    </div>
                    <div class="je-panel-body">
                        <div class="table-responsive">
                        <table class="je-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Division</th>
                                    <th>Title</th>
                                    <th>Price</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($featured as $index => $listing): 
                                    $rank = $index + 1;
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $rank; ?></strong></td>
                                    <td><?php echo ucfirst($listing['division']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($listing['title']); ?></strong></td>
                                    <td>₦<?php echo number_format($listing['price']); ?></td>
                                    <td><?php echo $listing['score'] ?? 0; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="je-panel">
            <div class="je-panel-body" style="text-align: center; padding: 40px;">
                <?php if (!$success): ?>
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-robot" style="font-size: 48px; color: #C6A43F; display: block; margin-bottom: 16px;"></i>
                        <h3 style="font-family: 'Prata', serif; margin-bottom: 8px;">Run Featured Algorithm</h3>
                        <p style="color: #666; max-width: 500px; margin: 0 auto;">
                            This will analyze all active listings and automatically mark the top 8 as featured on your home page.
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="?action=run" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A; padding: 12px 32px;">
                            <i class="fas fa-play"></i> Run Featured Algorithm
                        </a>
                        <a href="test-featured.php" class="je-btn je-btn-outline">
                            <i class="fas fa-chart-line"></i> Preview Scores First
                        </a>
                    </div>

                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
                        <p style="font-size: 12px; color: #888;">
                            <i class="fas fa-info-circle"></i> This will update the <strong>featured</strong> column in your database.
                            The home page will display these listings in the "Featured Listings" section.
                        </p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="?action=run" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A; padding: 12px 32px;">
                            <i class="fas fa-redo"></i> Run Again
                        </a>
                        <a href="/" target="_blank" class="je-btn je-btn-outline">
                            <i class="fas fa-eye"></i> View Home Page
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
