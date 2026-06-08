<?php
// Agent/Admin Sidebar Template - LUXURY STYLED
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin = SessionManager::getUserRole() === 'admin';
$isAgent = SessionManager::getUserRole() === 'agent';
?>

<aside class="luxury-sidebar">
    <div class="sidebar-header">
        <h3><?php echo $isAdmin ? 'Admin Panel' : 'Agent Panel'; ?></h3>
        <p><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></p>
        <?php if ($isAgent && $_SESSION['user_verified'] ?? false): ?>
            <span class="verified-badge">✓ Verified Agent</span>
        <?php endif; ?>
    </div>
    
    <nav class="sidebar-nav">
        <?php if ($isAdmin): ?>
            <!-- Admin Navigation -->
            <a href="/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="/admin/agent-approvals.php" class="<?php echo $currentPage === 'agent-approvals.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Agent Approvals
                <?php
                // Get pending count
                $db = Database::getInstance()->getConnection();
                $stmt = $db->query("SELECT COUNT(*) as count FROM agent_profiles WHERE verification_status = 'pending'");
                $pendingCount = $stmt->fetch()['count'];
                if ($pendingCount > 0): ?>
                    <span class="badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="/admin/agent-management.php" class="<?php echo $currentPage === 'agent-management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Manage Agents
            </a>
            <a href="/admin/listing-management.php" class="<?php echo $currentPage === 'listing-management.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-ul"></i> Listings
            </a>
            <a href="/admin/flagged-listings.php" class="<?php echo $currentPage === 'flagged-listings.php' ? 'active' : ''; ?>">
                <i class="fas fa-flag"></i> Flagged Items
            </a>
            <a href="/admin/user-management.php" class="<?php echo $currentPage === 'user-management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Users
            </a>
            <a href="/admin/activity-logs.php" class="<?php echo $currentPage === 'activity-logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Activity Logs
            </a>
            <a href="/admin/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Reports
            </a>
            <a href="/admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        <?php elseif ($isAgent): ?>
            <!-- Agent Navigation -->
            <a href="/agent/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="/agent/listings.php" class="<?php echo $currentPage === 'listings.php' ? 'active' : ''; ?>">
                <i class="fas fa-list-ul"></i> My Listings
            </a>
            <a href="/agent/add-listing.php" class="<?php echo $currentPage === 'add-listing.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> Add Listing
            </a>
            <a href="/agent/messages.php" class="<?php echo $currentPage === 'messages.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Messages
                <?php
                if (SessionManager::isLoggedIn()) {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0");
                    $stmt->execute([SessionManager::getUserId()]);
                    $unreadCount = $stmt->fetch()['count'];
                    if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif;
                } ?>
            </a>
            <a href="/agent/analytics.php" class="<?php echo $currentPage === 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Analytics
            </a>
            <a href="/agent/earnings.php" class="<?php echo $currentPage === 'earnings.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i> Earnings
            </a>
            <a href="/agent/profile.php" class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
            <?php if (!($_SESSION['user_verified'] ?? false)): ?>
                <a href="/agent/verification.php" class="<?php echo $currentPage === 'verification.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i> Get Verified
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
        <hr class="sidebar-divider">
        
        <a href="/" class="back-to-site">
            <i class="fas fa-home"></i> Back to Site
        </a>
        <a href="/auth/logout.php" class="sign-out">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </nav>
</aside>

<style>
.luxury-sidebar {
    background: #F8F8F8;
    border-radius: 12px;
    padding: 30px 20px;
    border: 1px solid #E0E0E0;
    position: sticky;
    top: 100px;
    height: fit-content;
}

.sidebar-header {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #E0E0E0;
    margin-bottom: 20px;
}

.sidebar-header h3 {
    font-family: 'Prata', serif;
    font-size: 20px;
    color: var(--je-black, #0A0A0A);
    margin-bottom: 8px;
}

.sidebar-header p {
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: var(--je-gray-dark, #666666);
}

.verified-badge {
    display: inline-block;
    background: #E8F5E9;
    color: #2E7D32;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    margin-top: 10px;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sidebar-nav a {
    padding: 12px 16px;
    color: var(--je-gray-dark, #666666);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 500;
}

.sidebar-nav a i {
    width: 20px;
    font-size: 16px;
}

.sidebar-nav a:hover {
    background: rgba(198, 164, 63, 0.1);
    color: var(--je-gold, #C6A43F);
}

.sidebar-nav a.active {
    background: rgba(198, 164, 63, 0.15);
    color: var(--je-gold, #C6A43F);
    border-left: 3px solid var(--je-gold, #C6A43F);
}

.sidebar-nav .badge {
    margin-left: auto;
    background: var(--je-gold, #C6A43F);
    color: var(--je-black, #0A0A0A);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.sidebar-divider {
    border: none;
    height: 1px;
    background: #E0E0E0;
    margin: 20px 0;
}

.back-to-site {
    color: var(--je-gray-dark, #666666) !important;
}

.sign-out {
    color: #C62828 !important;
}

.sign-out:hover {
    background: rgba(198, 40, 40, 0.1) !important;
}

@media (max-width: 992px) {
    .luxury-sidebar {
        position: static;
        margin-bottom: 30px;
    }
}
</style>
