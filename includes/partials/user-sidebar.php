<?php
/**
 * User Sidebar - Navigation for user dashboard
 *
 * AMENDED: Added real-time unread message badge with 15-second polling.
 * Badge style matches the header notification system (red circle, pulse).
 */
$current_page = $current_page ?? 'dashboard';
?>
<aside class="je-dash-sidebar">
    <div class="je-dash-sidebar-inner">

        <!-- Role Badge -->
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

            <a href="/user/messages.php" class="je-dash-nav-item <?php echo $current_page === 'messages' ? 'active' : ''; ?>" style="position:relative;">
                <i class="fas fa-envelope"></i> Messages
                <span id="sidebarMsgBadge" class="je-dash-badge" style="display:none;">0</span>
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

<style>
/* Sidebar notification badge — matches header style */
.je-dash-badge {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    background: #dc3545;
    color: #ffffff;
    border-radius: 50%;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    text-align: center;
    line-height: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    font-family: 'Inter', Arial, sans-serif;
    pointer-events: none;
}
.je-dash-badge.show {
    display: inline-block !important;
    animation: sidebarBadgePulse 0.5s ease-in-out 2;
}
@keyframes sidebarBadgePulse {
    0% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.3); }
    100% { transform: translateY(-50%) scale(1); }
}
</style>

<script>
// ============================================================
// SIDEBAR NOTIFICATION — 15-second polling (matches header)
// ============================================================
(function() {
    'use strict';

    var CONFIG = {
        refreshInterval: 15000,
        apiEndpoint: '/api/messages/unread-count.php'
    };

    var timeout = null;
    var lastCount = -1;

    function updateSidebarBadge() {
        fetch(CONFIG.apiEndpoint, {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(response) {
            if (response.status === 401 || response.status === 403) return;
            if (!response.ok) throw new Error('Failed to fetch');
            return response.json();
        })
        .then(function(data) {
            if (data && data.success) {
                var count = data.unread_count || 0;
                var badge = document.getElementById('sidebarMsgBadge');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.add('show');
                        badge.style.display = 'inline-block';
                    } else {
                        badge.classList.remove('show');
                        badge.style.display = 'none';
                    }
                }
                lastCount = count;
            }
        })
        .catch(function() {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            updateSidebarBadge();
            timeout = setInterval(updateSidebarBadge, CONFIG.refreshInterval);
        });
    } else {
        updateSidebarBadge();
        timeout = setInterval(updateSidebarBadge, CONFIG.refreshInterval);
    }

    window.addEventListener('beforeunload', function() {
        if (timeout) { clearInterval(timeout); timeout = null; }
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) { updateSidebarBadge(); }
    });
})();
</script>
