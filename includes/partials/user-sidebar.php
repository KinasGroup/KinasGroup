<?php
/**
 * User Sidebar - Navigation for user dashboard
 */
$current_page = $current_page ?? 'dashboard';
?>
<aside class="je-dash-sidebar">
    <div class="je-dash-sidebar-inner">
        <!-- Simple Logo/Brand at top instead of user name -->
        <div class="je-dash-brand">
            <i class="fas fa-user-circle" style="font-size: 32px; color: #C6A43F; display: block; margin-bottom: 8px;"></i>
            <div class="je-dash-user-role" style="color: #888; font-size: 13px;">Member Dashboard</div>
        </div>
        
        <!-- Navigation -->
        <nav class="je-dash-nav">
            <a href="/user/dashboard.php" class="je-dash-nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="/user/messages.php" class="je-dash-nav-item <?php echo $current_page === 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Messages
                <?php 
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
                    $stmt->execute([$_SESSION['user_id']]);
                    $unread = $stmt->fetchColumn();
                    if ($unread > 0): ?>
                        <span class="je-dash-badge"><?php echo $unread; ?></span>
                    <?php endif; 
                } catch (Exception $e) { /* ignore */ }
                ?>
            </a>
            <a href="/user/saved-listings.php" class="je-dash-nav-item <?php echo $current_page === 'saved' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> Saved Listings
            </a>
            <a href="/user/profile.php" class="je-dash-nav-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="/user/settings.php" class="je-dash-nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="/auth/logout.php" class="je-dash-nav-item je-dash-nav-logout">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </nav>
    </div>
</aside>
