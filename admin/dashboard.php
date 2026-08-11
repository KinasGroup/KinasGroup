<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Admin Dashboard - KINAS GROUP
 *
 * AMENDED:
 * - Adds Product Reviews moderation shortcut.
 * - Shows pending product reviews count.
 * - Shows open review report count.
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$adminName = $_SESSION['user_name'] ?? 'Admin';

// Get admin stats
$stats = [
    'total_users' => 0,
    'total_agents' => 0,
    'total_admins' => 0,
    'total_listings' => 0,
    'pending_verifications' => 0,
    'pending_reviews' => 0,
    'open_review_reports' => 0,
];

try {
    // Total users
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetchColumn();
} catch (Exception $e) {
    $stats['total_users'] = 'N/A';
}

try {
    // Total agents - using the 'role' column
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'");
    $stats['total_agents'] = $result->fetchColumn();
} catch (Exception $e) {
    $stats['total_agents'] = 'N/A';
}

try {
    // Total admins
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stats['total_admins'] = $result->fetchColumn();
} catch (Exception $e) {
    $stats['total_admins'] = 'N/A';
}

try {
    // Total listings across all divisions
    $tables = ['solar_listings', 'car_listings', 'property_listings', 'marketplace_listings'];
    $total = 0;

    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table WHERE status = 'active'");
            $total += $result->fetchColumn();
        } catch (Exception $e) {
            // Table might not exist
        }
    }

    $stats['total_listings'] = $total;
} catch (Exception $e) {
    $stats['total_listings'] = 'N/A';
}

try {
    // Pending verifications - check if table exists
    $tables = $db->query("SHOW TABLES LIKE 'agent_profiles'")->fetchAll();

    if (!empty($tables)) {
        $result = $db->query("SELECT COUNT(*) as count FROM agent_profiles WHERE verification_status = 'pending'");
        $stats['pending_verifications'] = $result->fetchColumn();
    } else {
        $stats['pending_verifications'] = 0;
    }
} catch (Exception $e) {
    $stats['pending_verifications'] = 'N/A';
}

try {
    // Pending product reviews
    $result = $db->query("SELECT COUNT(*) as count FROM product_reviews WHERE status = 'pending'");
    $stats['pending_reviews'] = (int)$result->fetchColumn();
} catch (Exception $e) {
    $stats['pending_reviews'] = 0;
}

try {
    // Open review reports
    $result = $db->query("SELECT COUNT(*) as count FROM product_review_reports WHERE status = 'open'");
    $stats['open_review_reports'] = (int)$result->fetchColumn();
} catch (Exception $e) {
    $stats['open_review_reports'] = 0;
}

$pageTitle = 'Admin Dashboard - KINAS GROUP';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-tachometer-alt" style="color: #C6A43F;"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($adminName); ?>!</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="je-card-grid">
            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div class="je-stat-label">Total Users</div>
                    <div class="je-stat-value"><?php echo $stats['total_users']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-user-tie"></i></div>
                <div>
                    <div class="je-stat-label">Total Agents</div>
                    <div class="je-stat-value"><?php echo $stats['total_agents']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-user-shield"></i></div>
                <div>
                    <div class="je-stat-label">Total Admins</div>
                    <div class="je-stat-value"><?php echo $stats['total_admins']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-list-ul"></i></div>
                <div>
                    <div class="je-stat-label">Total Listings</div>
                    <div class="je-stat-value"><?php echo $stats['total_listings']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="je-stat-label">Pending Verifications</div>
                    <div class="je-stat-value"><?php echo $stats['pending_verifications']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-star-half-alt"></i></div>
                <div>
                    <div class="je-stat-label">Pending Reviews</div>
                    <div class="je-stat-value"><?php echo (int)$stats['pending_reviews']; ?></div>
                </div>
            </div>

            <div class="je-stat-card">
                <div class="je-stat-icon"><i class="fas fa-flag"></i></div>
                <div>
                    <div class="je-stat-label">Open Review Reports</div>
                    <div class="je-stat-value"><?php echo (int)$stats['open_review_reports']; ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 32px;">
            <a href="test-featured.php" style="background: #1A3A2A; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-chart-line" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Test Algorithm</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Preview featured scores</p>
            </a>

            <a href="update-featured.php" style="background: #C6A43F; color: #0A0A0A; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-sync-alt" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Update Featured</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Run algorithm & update</p>
            </a>

            <a href="flagged-listings.php" style="background: #DC2626; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-flag" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Flagged Listings</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">Review reported content</p>
            </a>

            <a href="reviews.php" style="background: #6A1B9A; color: white; padding: 20px; border-radius: 12px; text-decoration: none; text-align: center; transition: all 0.3s;">
                <i class="fas fa-comments" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                <strong>Product Reviews</strong>
                <p style="font-size: 12px; margin-top: 4px; opacity: 0.8;">
                    <?php echo (int)$stats['pending_reviews']; ?> pending ·
                    <?php echo (int)$stats['open_review_reports']; ?> reports
                </p>
            </a>
        </div>

        <!-- Quick Links Section -->
        <div style="margin-top: 40px; background: white; border-radius: 16px; padding: 24px; border: 1px solid #E0E0E0;">
            <h3 style="font-family: 'Prata', serif; margin-bottom: 16px; color: #0A0A0A;">
                <i class="fas fa-clock" style="color: #C6A43F; margin-right: 8px;"></i> Quick Links
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                <a href="reviews.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-star" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">Product Reviews</span>
                </a>

                <a href="reports.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-chart-bar" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">View Reports</span>
                </a>

                <a href="activity-logs.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-history" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">Activity Logs</span>
                </a>

                <a href="users.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-users" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">Manage Users</span>
                </a>

                <a href="listings.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-list-ul" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">All Listings</span>
                </a>

                <a href="marketplace-orders.php" style="padding: 12px; background: #F5F7FA; border-radius: 8px; text-decoration: none; color: #333; text-align: center; transition: all 0.3s; border: 1px solid #E0E0E0;">
                    <i class="fas fa-receipt" style="color: #C6A43F; font-size: 20px; display: block; margin-bottom: 4px;"></i>
                    <span style="font-size: 13px;">Marketplace Orders</span>
                </a>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
