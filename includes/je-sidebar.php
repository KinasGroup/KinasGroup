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
    $adminNav = [
        ['key' => 'dashboard',    'icon' => 'tachometer-alt',  'label' => 'Overview',       'href' => 'dashboard.php'],
        ['key' => 'agents',       'icon' => 'user-tie',         'label' => 'Agent Approvals', 'href' => 'agent-approvals.php'],
        ['key' => 'agents_all',   'icon' => 'users',            'label' => 'All Agents',     'href' => 'agent-management.php'],
        ['key' => 'users',        'icon' => 'user',             'label' => 'Users',          'href' => 'user-management.php'],
        ['key' => 'listings',     'icon' => 'list-alt',         'label' => 'Listings',       'href' => 'listing-management.php'],
        ['key' => 'flagged',      'icon' => 'flag',             'label' => 'Flagged',        'href' => 'flagged-listings.php'],
        ['key' => 'reports',      'icon' => 'chart-bar',        'label' => 'Reports',        'href' => 'reports.php'],
        ['key' => 'activity',     'icon' => 'history',          'label' => 'Activity Log',   'href' => 'activity-logs.php'],
        ['key' => 'settings',     'icon' => 'cog',              'label' => 'Settings',       'href' => 'settings.php'],
    ];

    $nav = $role === 'admin' ? $adminNav : ($role === 'agent' ? $agentNav : $userNav);
    $brandLabel = $role === 'admin' ? 'ADMIN PANEL' : ($role === 'agent' ? 'AGENT PANEL' : 'MY ACCOUNT');
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
