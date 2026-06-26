<?php
/**
 * KINAS GROUP — Dashboard sidebar component
 * Used by all user/agent/admin dashboards.
 *
 * Call:
 *   je_render_sidebar('user', 'dashboard.php');
 *   je_render_sidebar('agent', 'listings.php');
 *   je_render_sidebar('admin', 'dashboard.php');
 */

// Ensure database is loaded before using it
if (!class_exists('Database')) {
    require_once __DIR__ . '/../api/config/database.php';
}

function je_render_sidebar(string $role, string $currentPage, int $headerDepth = 1): void
{
    $base = str_repeat('../', $headerDepth);

    // ── FORCE REFRESH SUPER AGENT STATUS ──────────────────────────────
    // This ensures the sidebar shows correctly even if the session is stale
    if ($role === 'agent' && isset($_SESSION['user_id'])) {
        try {
            // Check if Database class exists before using it
            if (class_exists('Database')) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT is_super_agent FROM agent_profiles WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    $_SESSION['is_super_agent'] = !empty($row['is_super_agent']);
                }
            }
        } catch (Exception $e) {
            // ignore - keep existing session value
        }
    }
    // ────────────────────────────────────────────────────────────────────

    // Check if user is a Super Agent
    $isSuperAgent = !empty($_SESSION['is_super_agent']);

    $userNav = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Dashboard',     'href' => 'dashboard.php'],
        ['key' => 'saved',        'icon' => 'heart',            'label' => 'Saved Listings', 'href' => 'saved-listings.php'],
        ['key' => 'inquiries',    'icon' => 'envelope',         'label' => 'My Inquiries',   'href' => 'my-inquiries.php'],
        ['key' => 'messages',     'icon' => 'comments',         'label' => 'Messages',       'href' => 'messages.php'],
        ['key' => 'profile',      'icon' => 'user-circle',      'label' => 'Profile',        'href' => 'profile.php'],
        ['key' => 'settings',     'icon' => 'cog',              'label' => 'Settings',       'href' => 'settings.php'],
    ];
    
    // ─── AGENT NAVIGATION ────────────────────────────────────────────────
    // Regular agents see: Dashboard, My Listings, Add Listing, Verification,
    // Messages, Analytics, Earnings, Profile
    // Super agents ALSO see: Hardware, Add Hardware (Kinas Volt only)
    // ─────────────────────────────────────────────────────────────────────
    
    // Define the base agent navigation (common to all agents)
    $agentNav = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Dashboard',     'href' => 'dashboard.php'],
        ['key' => 'listings',     'icon' => 'list-alt',         'label' => 'My Listings',   'href' => 'listings.php'],
        ['key' => 'add',          'icon' => 'plus-circle',      'label' => 'Add Listing',   'href' => 'add-listing.php'],
        ['key' => 'verification', 'icon' => 'shield-alt',       'label' => 'Verification',  'href' => 'verification.php'],
        ['key' => 'messages',     'icon' => 'comments',         'label' => 'Messages',      'href' => 'messages.php'],
        ['key' => 'analytics',    'icon' => 'chart-line',       'label' => 'Analytics',     'href' => 'analytics.php'],
        ['key' => 'earnings',     'icon' => 'wallet',           'label' => 'Earnings',      'href' => 'earnings.php'],
        ['key' => 'profile',      'icon' => 'user-circle',      'label' => 'Profile',       'href' => 'profile.php'],
    ];
    
    // Insert hardware links right after 'Add Listing' if user is a Super Agent
    if ($isSuperAgent) {
        $agentNav = array_merge(
            array_slice($agentNav, 0, 3), // Dashboard, My Listings, Add Listing
            [
                ['key' => 'hardware',    'icon' => 'microchip', 'label' => 'Hardware',     'href' => 'hardware.php'],
                ['key' => 'addhardware', 'icon' => 'plus',       'label' => 'Add Hardware', 'href' => 'add-hardware.php'],
            ],
            array_slice($agentNav, 3) // Verification, Messages, Analytics, Earnings, Profile
        );
    }
    
    $adminNav = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Dashboard',       'href' => 'dashboard.php'],
        ['key' => 'users',        'icon' => 'users',            'label' => 'Users',          'href' => 'user-management.php'],
        ['key' => 'agents',       'icon' => 'user-tie',         'label' => 'Agents',         'href' => 'agents.php'],
        ['key' => 'listings',     'icon' => 'list-alt',         'label' => 'Listings',       'href' => 'listings.php'],
        ['type' => 'heading',     'label' => 'MODERATION'],
        ['key' => 'flagged',      'icon' => 'flag',             'label' => 'Flagged Listings', 'href' => 'flagged-listings.php'],
        ['type' => 'heading',     'label' => 'ANALYTICS'],
        ['key' => 'reports',      'icon' => 'chart-bar',        'label' => 'Reports',        'href' => 'reports.php'],
        ['key' => 'activity',     'icon' => 'history',          'label' => 'Activity Log',   'href' => 'activity-logs.php'],
        ['type' => 'heading',     'label' => 'FEATURED MANAGEMENT'],
        ['key' => 'test_algo',    'icon' => 'chart-line',       'label' => 'Test Algorithm', 'href' => 'test-featured.php'],
        ['key' => 'update_feat',  'icon' => 'sync-alt',         'label' => 'Update Featured', 'href' => 'update-featured.php'],
        ['type' => 'heading',     'label' => 'SYSTEM'],
        ['key' => 'settings',     'icon' => 'cog',              'label' => 'Settings',       'href' => 'settings.php'],
    ];

    // Select the correct navigation based on role
    if ($role === 'admin') {
        $nav = $adminNav;
        $brandLabel = 'ADMIN PANEL';
    } elseif ($role === 'agent') {
        $nav = $agentNav;
        // Show "SUPER AGENT" for super agents, "AGENT PANEL" for regular agents
        $brandLabel = $isSuperAgent ? 'SUPER AGENT' : 'AGENT PANEL';
    } else {
        $nav = $userNav;
        $brandLabel = 'MY ACCOUNT';
    }
    
    $homeHref = $base . 'index.php';
    $logoutHref = $base . 'auth/logout.php';

    ?>
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-gem" style="color:#C6A43F;"></i> <?= htmlspecialchars($brandLabel) ?>
        </div>
        <ul class="je-dash-nav">
            <?php foreach ($nav as $item): ?>
                <?php if (($item['type'] ?? '') === 'heading'): ?>
                    <li class="je-dash-nav-heading"><?= htmlspecialchars($item['label']) ?></li>
                <?php else: ?>
                    <li>
                        <a href="<?= htmlspecialchars($base . $role . '/' . $item['href']) ?>"
                           class="<?= $currentPage === $item['href'] ? 'is-active' : '' ?>">
                            <i class="fas fa-<?= htmlspecialchars($item['icon']) ?>"></i>
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li class="je-dash-nav-divider"></li>
            <li><a href="<?= htmlspecialchars($homeHref) ?>"><i class="fas fa-home"></i> Back to Site</li>
            <li class="je-dash-signout"><a href="<?= htmlspecialchars($logoutHref) ?>"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>
    <?php
}
