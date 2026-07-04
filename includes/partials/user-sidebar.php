<?php
/**
 * User Sidebar - Navigation for user dashboard
 */
$current_page = $current_page ?? 'dashboard';
?>
<aside class="je-dash-sidebar">
    <div class="je-dash-sidebar-inner">
        <!-- Role Badge - Like "SUPER AGENT" but "MEMBER" -->
        <div class="je-dash-user" style="text-align: center; padding: 20px 0 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
            <div class="je-dash-avatar" style="width: 60px; height: 60px; border-radius: 50%; background: #C6A43F; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: 700; font-size: 24px; color: #0A0A0A;">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="je-dash-user-role" style="font-weight: 700; font-size: 14px; color: #C6A43F; letter-spacing: 1px; text-transform: uppercase; font-family: 'Inter', sans-serif;">Member</div>
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
            <a href="/user/orders.php" class="je-dash-nav-item <?php echo $current_page === 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> My Orders
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
