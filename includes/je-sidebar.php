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
function je_render_sidebar(string $role, string $currentPage, int $headerDepth = 1): void
{
    $base = str_repeat('../', $headerDepth);

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
    $agentNavBase = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Dashboard',     'href' => 'dashboard.php'],
        ['key' => 'listings',     'icon' => 'list-alt',         'label' => 'My Listings',   'href' => 'listings.php'],
        ['key' => 'add',          'icon' => 'plus-circle',      'label' => 'Add Listing',   'href' => 'add-listing.php'],
        // Hardware links will be conditionally added here
        ['key' => 'verification', 'icon' => 'shield-alt',       'label' => 'Verification',  'href' => 'verification.php'],
        ['key' => 'messages',     'icon' => 'comments',         'label' => 'Messages',      'href' => 'messages.php'],
        ['key' => 'analytics',    'icon' => 'chart-line',       'label' => 'Analytics',     'href' => 'analytics.php'],
        ['key' => 'earnings',     'icon' => 'wallet',           'label' => 'Earnings',      'href' => 'earnings.php'],
        ['key' => 'profile',      'icon' => 'user-circle',      'label' => 'Profile',       'href' => 'profile.php'],
    ];
    
    // Check if user is a Super Agent
    $isSuperAgent = !empty($_SESSION['is_super_agent']);
    
    // Build agent navigation with conditional hardware links
    $agentNav = [];
    foreach ($agentNavBase as $item) {
        $agentNav[] = $item;
        // Insert hardware links right after 'Add Listing' if user is a Super Agent
        if ($item['key'] === 'add' && $isSuperAgent) {
            $agentNav[] = ['key' => 'hardware',    'icon' => 'microchip', 'label' => 'Hardware',     'href' => 'hardware.php'];
            $agentNav[] = ['key' => 'addhardware', 'icon' => 'plus',       'label' => 'Add Hardware', 'href' => 'add-hardware.php'];
        }
    }
    
    $adminNav = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Overview',       'href' => 'dashboard.php'],
        ['key' => 'agents',       'icon' => 'user-tie',         'label' => 'Agent Approvals', 'href' => 'agent-approvals.php'],
        ['key' => 'agents_all',   'icon' => 'users',            'label' => 'All Agents',     'href' => 'agents.php'],
        ['key' => 'users',        'icon' => 'user',             'label' => 'Users',          'href' => 'users.php'],
        ['key' => 'listings',     'icon' => 'list-alt',         'label' => 'Listings',       'href' => 'listings.php'],
        ['key' => 'flagged',      'icon' => 'flag',             'label' => 'Flagged',        'href' => 'flagged-listings.php'],
        ['key' => 'reports',      'icon' => 'chart-bar',        'label' => 'Reports',        'href' => 'reports.php'],
        ['key' => 'activity',     'icon' => 'history',          'label' => 'Activity Log',   'href' => 'activity-logs.php'],
        ['key' => 'settings',     'icon' => 'cog',              'label' => 'Settings',       'href' => 'settings.php'],
    ];

    // Select the correct navigation based on role
    if ($role === 'admin') {
        $nav = $adminNav;
        $brandLabel = 'ADMIN PANEL';
    } elseif ($role === 'agent') {
        $nav = $agentNav;
        $brandLabel = 'AGENT PANEL';
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
                <li>
                    <a href="<?= htmlspecialchars($base . $role . '/' . $item['href']) ?>"
                       class="<?= $currentPage === $item['href'] ? 'is-active' : '' ?>">
                        <i class="fas fa-<?= htmlspecialchars($item['icon']) ?>"></i>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="je-dash-nav-divider"></li>
            <li><a href="<?= htmlspecialchars($homeHref) ?>"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="<?= htmlspecialchars($logoutHref) ?>"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>
    <?php
}
