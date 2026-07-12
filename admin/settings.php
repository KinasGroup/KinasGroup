<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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
require_once __DIR__ . '/../api/config/database.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle social media update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_socials'])) {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $flashError = 'Please refresh the page and try again.';
    } else {
        try {
            // Check if settings table exists, create if not
            $tableCheck = $db->query("SHOW TABLES LIKE 'settings'")->fetchAll();
            if (empty($tableCheck)) {
                $db->exec("CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
            }

            // Update social media settings
            $socials = [
                'facebook' => trim($_POST['facebook'] ?? ''),
                'youtube' => trim($_POST['youtube'] ?? ''),
                'x' => trim($_POST['x'] ?? ''),
                'instagram' => trim($_POST['instagram'] ?? ''),
                'linkedin' => trim($_POST['linkedin'] ?? '')
            ];

            foreach ($socials as $key => $value) {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }

            Security::logActivity($_SESSION['user_id'], 'settings_updated', 'Social media links updated');
            $flashSuccess = 'Social media links updated successfully!';
            
            // Refresh to show updated values
            header('Location: settings.php');
            exit;
            
        } catch (Exception $e) {
            $flashError = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Fetch current social media settings
$socials = [
    'facebook' => '',
    'youtube' => '',
    'x' => '',
    'instagram' => '',
    'linkedin' => ''
];

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('facebook', 'youtube', 'x', 'instagram', 'linkedin')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $socials[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

$headerDepth = '../';
$pageTitle = 'Settings - KINAS GROUP';
require_once __DIR__ . '/../templates/header.php';
?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .admin-container { max-width: 1200px; margin: 0 auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
        .flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .flash i { font-size: 18px; }
        .settings-card { background: white; border-radius: 16px; border: 1px solid #E0E0E0; margin-bottom: 24px; overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #E0E0E0; display: flex; align-items: center; gap: 12px; }
        .card-header h2 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; margin: 0; }
        .card-header i { color: #C6A43F; font-size: 20px; }
        .card-body { padding: 25px; }
        .kv-grid { display: grid; grid-template-columns: 200px 1fr; gap: 14px 24px; }
        .kv-grid dt { font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .kv-grid dd { font-size: 14px; color: #0A0A0A; margin: 0; font-family: 'Inter', sans-serif; }
        .kv-grid dd code { background: #F8F8F8; padding: 3px 8px; border-radius: 6px; font-size: 13px; color: #C6A43F; }
        .notice { background: #FFF8E1; border: 1px solid #FFE0B2; border-radius: 12px; padding: 16px 20px; color: #5D4037; font-size: 14px; display: flex; gap: 12px; align-items: flex-start; margin-bottom: 24px; }
        .notice i { color: #F57C00; font-size: 20px; margin-top: 2px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .form-group label i { color: #C6A43F; margin-right: 6px; }
        .form-group input[type="text"], .form-group input[type="url"] { 
            width: 100%; 
            padding: 10px 14px; 
            border: 1px solid #E0E0E0; 
            border-radius: 8px; 
            font-family: 'Inter', sans-serif; 
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus { 
            outline: none; 
            border-color: #C6A43F; 
            box-shadow: 0 0 0 3px rgba(198,164,63,0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn-save { 
            background: #C6A43F; 
            color: #0A0A0A; 
            border: none; 
            padding: 12px 32px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 14px;
            cursor: pointer; 
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save:hover { 
            background: #A8882E; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(198,164,63,0.3);
        }
        .btn-save i { font-size: 16px; }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .action-link { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #F8F8F8; border: 1px solid #E0E0E0; border-radius: 12px; text-decoration: none; color: #0A0A0A; transition: all 0.3s; }
        .action-link:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; transform: translateY(-2px); }
        .action-link i { color: #C6A43F; font-size: 18px; }
        .action-link:hover i { color: #0A0A0A; }
        .action-link strong { display: block; font-size: 14px; }
        .action-link small { display: block; color: #666; font-size: 12px; }
        .action-link:hover small { color: #0A0A0A; }
        .social-preview { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 600;
        }
        .social-preview.active { background: #E8F5E9; color: #2E7D32; }
        .social-preview.inactive { background: #F5F5F5; color: #999; }
        @media (max-width: 768px) { 
            .admin-main { padding: 20px; } 
            .kv-grid { grid-template-columns: 1fr; gap: 4px 0; } 
            .kv-grid dt { margin-top: 12px; }
            .form-row { grid-template-columns: 1fr; }
        }
    
/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    body { background: #F5F7FA !important; }
    .admin-main { background: #F5F7FA !important; }
    .page-header h1 { color: #0A0A0A !important; }
    .page-header p { color: #666 !important; }
    .flash.success { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .flash.error { background: #FEF2F2 !important; color: #DC2626 !important; }
    .settings-card { background: white !important; }
    .card-header h2 { color: #0A0A0A !important; }
    .card-header i { color: #C6A43F !important; }
    .kv-grid dt { color: #666 !important; }
    .kv-grid dd { color: #0A0A0A !important; }
    .kv-grid dd code { background: #F8F8F8 !important; color: #C6A43F !important; }
    .notice { background: #FFF8E1 !important; color: #5D4037 !important; }
    .notice i { color: #F57C00 !important; }
    .form-group label { color: #333 !important; }
    .form-group label i { color: #C6A43F !important; }
    .form-group input:focus { border-color: #C6A43F !important; }
    .btn-save { background: #C6A43F !important; color: #0A0A0A !important; }
    .btn-save:hover { background: #A8882E !important; }
    .action-link { background: #F8F8F8 !important; color: #0A0A0A !important; }
    .action-link:hover { background: #C6A43F !important; border-color: #C6A43F !important; color: #0A0A0A !important; }
    .action-link i { color: #C6A43F !important; }
    .action-link:hover i { color: #0A0A0A !important; }
    .action-link small { color: #666 !important; }
    .action-link:hover small { color: #0A0A0A !important; }
    .social-preview.active { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .social-preview.inactive { background: #F5F5F5 !important; color: #999 !important; }
}
</style>
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

    <!-- Social Media Settings - Editable Form -->
    <div class="settings-card">
        <div class="card-header"><i class="fas fa-share-alt"></i><h2>Social Media Links</h2></div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRFToken() ?>">
                <input type="hidden" name="update_socials" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fab fa-facebook"></i> Facebook</label>
                        <input type="url" name="facebook" value="<?= htmlspecialchars($socials['facebook']) ?>" placeholder="https://facebook.com/yourpage">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-youtube"></i> Youtube</label>
                        <input type="url" name="youtube" value="<?= htmlspecialchars($socials['youtube']) ?>" placeholder="https://youtube.com/yourhandle">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fab fa-x-twitter"></i> X (formerly Twitter)</label>
                        <input type="url" name="x" value="<?= htmlspecialchars($socials['x']) ?>" placeholder="https://x.com/yourhandle">
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-instagram"></i> Instagram</label>
                        <input type="url" name="instagram" value="<?= htmlspecialchars($socials['instagram']) ?>" placeholder="https://instagram.com/yourhandle">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fab fa-linkedin"></i> LinkedIn</label>
                        <input type="url" name="linkedin" value="<?= htmlspecialchars($socials['linkedin']) ?>" placeholder="https://linkedin.com/company/yourcompany">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Social Links</button>
                    </div>
                </div>

                <!-- Current Status Display -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #E0E0E0;">
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Current Status:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php 
                        $socialIcons = [
                            'facebook' => 'fab fa-facebook',
                            'youtube' => 'fab fa-youtube', 
                            'x' => 'fab fa-x-twitter',
                            'instagram' => 'fab fa-instagram',
                            'linkedin' => 'fab fa-linkedin'
                        ];
                        foreach ($socials as $key => $value): 
                            $isActive = !empty($value);
                        ?>
                            <span class="social-preview <?= $isActive ? 'active' : 'inactive' ?>">
                                <i class="<?= $socialIcons[$key] ?? 'fas fa-link' ?>"></i>
                                <?= ucfirst($key) ?>: <?= $isActive ? '✓ Set' : '— Not set' ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
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
        <div class="card-header"><i class="fas fa-bolt"></i><h2>Quick Admin Actions</h2></div>
        <div class="card-body">
            <p style="color:#666; font-size:13px; margin-bottom:16px;">Manage the platform from these entry points:</p>
            <div class="quick-actions">
                <a href="user-management.php" class="action-link">
                    <i class="fas fa-users"></i>
                    <div><strong>Manage Users</strong><small>Roles, status, suspensions</small></div>
                </a>
                <a href="agents.php" class="action-link">
                    <i class="fas fa-user-tie"></i>
                    <div><strong>Manage Agents</strong><small>Approve, suspend, verify</small></div>
                </a>
                <a href="listings.php" class="action-link">
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
