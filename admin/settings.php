<?php
/**
 * KINAS GROUP — Admin: System Settings
 *
 * The application does not yet have a settings table; most platform
 * settings are managed via the .env file (loaded by includes/env.php).
 * This page summarises the current configuration and the actions
 * available elsewhere in the admin panel.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/constants.php';

SessionManager::requireAdmin();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .admin-container { max-width: 1200px; margin: 0 auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
        .flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .settings-card { background: white; border-radius: 16px; border: 1px solid #E0E0E0; margin-bottom: 24px; overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #E0E0E0; display: flex; align-items: center; gap: 12px; }
        .card-header h2 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; }
        .card-header i { color: #C6A43F; font-size: 20px; }
        .card-body { padding: 25px; }
        .kv-grid { display: grid; grid-template-columns: 200px 1fr; gap: 14px 24px; }
        .kv-grid dt { font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .kv-grid dd { font-size: 14px; color: #0A0A0A; margin: 0; font-family: 'Inter', sans-serif; }
        .kv-grid dd code { background: #F8F8F8; padding: 3px 8px; border-radius: 6px; font-size: 13px; color: #C6A43F; }
        .notice { background: #FFF8E1; border: 1px solid #FFE0B2; border-radius: 12px; padding: 16px 20px; color: #5D4037; font-size: 14px; display: flex; gap: 12px; align-items: flex-start; margin-bottom: 24px; }
        .notice i { color: #F57C00; font-size: 20px; margin-top: 2px; }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .action-link { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #F8F8F8; border: 1px solid #E0E0E0; border-radius: 12px; text-decoration: none; color: #0A0A0A; transition: all 0.3s; }
        .action-link:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; transform: translateY(-2px); }
        .action-link i { color: #C6A43F; font-size: 18px; }
        .action-link:hover i { color: #0A0A0A; }
        .action-link strong { display: block; font-size: 14px; }
        .action-link small { display: block; color: #666; font-size: 12px; }
        .action-link:hover small { color: #0A0A0A; }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .kv-grid { grid-template-columns: 1fr; gap: 4px 0; } .kv-grid dt { margin-top: 12px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
<div class="admin-container">
    <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-cog" style="color: #C6A43F; margin-right: 10px;"></i>System Settings</h1>
        <p>Platform configuration. Most settings are managed via environment variables.</p>
    </div>

    <div class="notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Runtime configuration only.</strong> Platform-level settings (database credentials, API keys, feature flags) live in the <code>.env</code> file on the server. To change them, edit <code>env.example</code> in the repo and redeploy, or update the live environment on Railway.
        </div>
    </div>

    <div class="settings-card">
        <div class="card-header"><i class="fas fa-globe"></i><h2>General</h2></div>
        <div class="card-body">
            <dl class="kv-grid">
                <dt>Site name</dt><dd><?= htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'KINAS GROUP') ?></dd>
                <dt>Site URL</dt><dd><code><?= htmlspecialchars(SITE_URL) ?></code></dd>
                <dt>Admin email</dt><dd><code><?= htmlspecialchars(ADMIN_EMAIL) ?></code></dd>
                <dt>Support email</dt><dd><code><?= htmlspecialchars(SUPPORT_EMAIL) ?></code></dd>
                <dt>Default country</dt><dd>Nigeria</dd>
            </dl>
        </div>
    </div>

    <div class="settings-card">
        <div class="card-header"><i class="fas fa-percentage"></i><h2>Integrations</h2></div>
        <div class="card-body">
            <dl class="kv-grid">
                <dt>R2 storage</dt><dd><?= getenv('R2_ENABLED') === 'true' ? '<span style="color:#2E7D32;">✓ Enabled</span>' : '<span style="color:#F57C00;">Disabled (local fallback)</span>' ?></dd>
                <dt>SMS provider</dt><dd>Termii</dd>
                <dt>Email provider</dt><dd>Resend</dd>
                <dt>Identity KYC</dt><dd>MetaMap</dd>
            </dl>
        </div>
    </div>

    <div class="settings-card">
        <div class="card-header"><i class="fas fa-link"></i><h2>Social</h2></div>
        <div class="card-body">
            <dl class="kv-grid">
                <?php
                    $socials = defined('SOCIAL_MEDIA') ? SOCIAL_MEDIA : [];
                ?>
                <?php foreach (['facebook','twitter','instagram','linkedin'] as $net): ?>
                    <dt><?= ucfirst($net) ?></dt>
                    <dd><code><?= htmlspecialchars($socials[$net] ?? '—') ?></code></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <div class="settings-card">
        <div class="card-header"><i class="fas fa-bolt"></i><h2>Quick Admin Actions</h2></div>
        <div class="card-body">
            <p style="color:#666; font-size:13px; margin-bottom:16px;">Manage the platform from these entry points:</p>
            <div class="quick-actions">
                <a href="user-management.php" class="action-link">
                    <i class="fas fa-users"></i>
                    <div><strong>Manage Users</strong><small>Roles, status, suspensions</small></div>
                </a>
                <a href="agent-management.php" class="action-link">
                    <i class="fas fa-user-tie"></i>
                    <div><strong>Manage Agents</strong><small>Approve, suspend, verify</small></div>
                </a>
                <a href="agent-approvals.php" class="action-link">
                    <i class="fas fa-user-check"></i>
                    <div><strong>Agent Approvals</strong><small>Review pending KYC submissions</small></div>
                </a>
                <a href="listing-management.php" class="action-link">
                    <i class="fas fa-list-ul"></i>
                    <div><strong>Manage Listings</strong><small>Flag, approve, remove</small></div>
                </a>
                <a href="flagged-listings.php" class="action-link">
                    <i class="fas fa-flag"></i>
                    <div><strong>Flagged Listings</strong><small>Review reported content</small></div>
                </a>
                <a href="activity-logs.php" class="action-link">
                    <i class="fas fa-history"></i>
                    <div><strong>Activity Logs</strong><small>Audit trail of admin & user actions</small></div>
                </a>
            </div>
        </div>
    </div>
</div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
