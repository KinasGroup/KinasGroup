<?php
/**
 * KINAS GROUP — Agent Profile
 * Loads the agent's data from `users` and `agent_profiles`,
 * pre-fills forms, and posts to /api/agent/update-profile.php.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db     = Database::getInstance()->getConnection();
$userId = (int)$_SESSION['user_id'];

// Load user row
$userStmt = $db->prepare("SELECT id, name, email, phone, role, status, verified, division, avatar, created_at FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_error'] = 'Account not found.';
    header('Location: /auth/logout.php');
    exit;
}

// Load profile row (may not exist yet)
$profStmt = $db->prepare("SELECT * FROM agent_profiles WHERE user_id = ?");
$profStmt->execute([$userId]);
$profile = $profStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Stats: how many listings the agent owns
$stats = [
    'listings' => (int)$db->query("SELECT
        (SELECT COUNT(*) FROM car_listings WHERE agent_id = $userId) +
        (SELECT COUNT(*) FROM property_listings WHERE agent_id = $userId) +
        (SELECT COUNT(*) FROM solar_listings WHERE agent_id = $userId) +
        (SELECT COUNT(*) FROM marketplace_listings WHERE agent_id = $userId)")->fetchColumn(),
    'inquiries'=> (int)$db->query("SELECT COUNT(*) FROM inquiries WHERE agent_id = $userId")->fetchColumn(),
    'unread'   => (int)$db->query("SELECT COUNT(*) FROM messages WHERE receiver_id = $userId AND is_read = 0")->fetchColumn(),
];

// Decode name into first/last
$nameParts = explode(' ', $user['name'] ?? '', 2);
$firstName = $nameParts[0] ?? '';
$lastName  = $nameParts[1] ?? '';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Security::generateCSRFToken();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1200px; margin: 0 auto; padding: 30px; }
.agent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.profile-card { background: white; border-radius: 20px; border: 1px solid #E0E0E0; padding: 28px; }
.profile-card h3 { font-size: 16px; font-weight: 600; color: #0A0A0A; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.profile-card h3 i { color: #C6A43F; margin-right: 8px; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 14px; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; }
.form-group input[readonly] { background: #F8F8F8; color: #888; cursor: not-allowed; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.btn-save { background: #C6A43F; border: none; color: #0A0A0A; padding: 10px 22px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.btn-save:hover { background: #A8882E; }
.btn-secondary { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 10px 18px; border-radius: 10px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
.btn-danger { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; padding: 10px 18px; border-radius: 10px; cursor: pointer; font-size: 13px; }
.checkbox-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.checkbox-label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; }
.checkbox-label input { accent-color: #C6A43F; }
.profile-photo-section { display: flex; align-items: center; gap: 20px; }
.profile-avatar-large { width: 96px; height: 96px; border-radius: 50%; background: #C6A43F; color: #0A0A0A; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; flex-shrink: 0; overflow: hidden; }
.profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
.btn-upload { background: #F5F5F5; color: #333; border: 1px solid #E0E0E0; padding: 9px 18px; border-radius: 10px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
.btn-upload:hover { background: #E8E8E8; }
.photo-note { font-size: 11px; color: #999; margin-top: 6px; }
.account-meta { font-size: 12px; color: #888; margin-top: 4px; }
.status-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-left: 8px; vertical-align: middle; }
.status-active    { background: #E8F5E9; color: #2E7D32; }
.status-suspended { background: #FEF2F2; color: #DC2626; }
.status-pending   { background: #FFF3E0; color: #F57C00; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
.stat-mini { background: white; border-radius: 16px; border: 1px solid #E0E0E0; padding: 18px 22px; display: flex; align-items: center; gap: 14px; }
.stat-mini .icon { font-size: 24px; color: #C6A43F; }
.stat-mini .info strong { display:block; font-size: 20px; color: #0A0A0A; font-family: 'Prata', serif; }
.stat-mini .info small { font-size: 11px; color: #666; }
.full-width { grid-column: 1 / -1; }
@media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } .stats-row { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="agent-header">
        <h1>
            <i class="fas fa-user-circle"></i> Agent Profile
            <span class="status-pill status-<?= htmlspecialchars($user['status'] ?? 'pending') ?>"><?= htmlspecialchars(ucfirst($user['status'] ?? 'pending')) ?></span>
            <?php if (!empty($user['verified'])): ?>
                <span class="status-pill status-active"><i class="fas fa-check-circle"></i> Verified</span>
            <?php endif; ?>
        </h1>
        <div class="account-meta">
            Member since <?= htmlspecialchars(date('M Y', strtotime($user['created_at']))) ?>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-list-ul"></i></div>
            <div class="info"><strong><?= number_format($stats['listings']) ?></strong><small>Total listings</small></div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-envelope"></i></div>
            <div class="info"><strong><?= number_format($stats['inquiries']) ?></strong><small>Inquiries received</small></div>
        </div>
        <div class="stat-mini">
            <div class="icon"><i class="fas fa-comment-dots"></i></div>
            <div class="info"><strong><?= number_format($stats['unread']) ?></strong><small>Unread messages</small></div>
        </div>
    </div>

    <form class="profile-grid" method="POST" action="/api/agent/update-profile.php" enctype="multipart/form-data" id="profileForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="redirect" value="/agent/profile.php">

        <div class="profile-card">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="name_first" value="<?= htmlspecialchars($firstName) ?>" maxlength="100" placeholder="Your first name">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="name_last" value="<?= htmlspecialchars($lastName) ?>" maxlength="100" placeholder="Your last name">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly disabled>
                <div class="photo-note">Email is tied to your account and cannot be changed here. Contact support to update it.</div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+234 800 000 0000">
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Bio / Professional Summary</label>
                <textarea name="bio" rows="4" placeholder="Tell buyers about your expertise and what you specialize in…"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="profile-card">
            <h3><i class="fas fa-building"></i> Business Information</h3>
            <div class="form-group">
                <label>Agency / Company Name</label>
                <input type="text" name="company_name" value="<?= htmlspecialchars($profile['company_name'] ?? '') ?>" placeholder="e.g., Smith Luxury Motors">
            </div>
            <div class="form-group">
                <label>License Number</label>
                <input type="text" name="license_number" value="<?= htmlspecialchars($profile['license_number'] ?? '') ?>" placeholder="e.g., RL-2024-00123">
            </div>
            <div class="form-group">
                <label>Years in Business</label>
                <select name="years_in_business">
                    <option value="">Select…</option>
                    <?php
                        $yib = $profile['years_in_business'] ?? '';
                        $opts = ['lt_1' => 'Less than 1 year', '1_3' => '1–3 years', '3_5' => '3–5 years', '5_plus' => '5+ years'];
                        foreach ($opts as $val => $label):
                    ?>
                        <option value="<?= $val ?>" <?= $yib === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-link"></i> Website</label>
                <input type="url" name="website" value="<?= htmlspecialchars($profile['website'] ?? '') ?>" placeholder="https://yourcompany.com">
            </div>
            <div class="form-group">
                <label>Professional Affiliations</label>
                <input type="text" name="professional_affiliations" value="<?= htmlspecialchars($profile['professional_affiliations'] ?? '') ?>" placeholder="e.g., NIESV, NIPB, NAR">
            </div>
        </div>

        <div class="profile-card">
            <h3><i class="fas fa-camera"></i> Profile Photo</h3>
            <div class="profile-photo-section">
                <div class="profile-avatar-large">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <input type="file" name="avatar" id="avatarUpload" accept="image/*" style="display: none;">
                    <button type="button" class="btn-upload" onclick="document.getElementById('avatarUpload').click()"><i class="fas fa-camera"></i> Choose Photo</button>
                    <p class="photo-note">Recommended: Square image, at least 300×300px. Max 5MB.</p>
                </div>
            </div>

            <h3 style="margin-top:24px;"><i class="fas fa-share-alt"></i> Social Links</h3>
            <div class="form-group">
                <label><i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook</label>
                <input type="url" name="facebook" value="<?= htmlspecialchars($profile['facebook'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
            </div>
            <div class="form-group">
                <label><i class="fab fa-twitter"></i> Twitter / X</label>
                <input type="url" name="twitter" value="<?= htmlspecialchars($profile['twitter'] ?? '') ?>" placeholder="https://twitter.com/yourhandle">
            </div>
            <div class="form-group">
                <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram</label>
                <input type="url" name="instagram" value="<?= htmlspecialchars($profile['instagram'] ?? '') ?>" placeholder="https://instagram.com/yourprofile">
            </div>
            <div class="form-group">
                <label><i class="fab fa-linkedin" style="color: #0A66C2;"></i> LinkedIn</label>
                <input type="url" name="linkedin" value="<?= htmlspecialchars($profile['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/yourprofile">
            </div>
            <div class="form-group">
                <label><i class="fab fa-youtube" style="color: #FF0000;"></i> YouTube</label>
                <input type="url" name="youtube" value="<?= htmlspecialchars($profile['youtube'] ?? '') ?>" placeholder="https://youtube.com/@yourchannel">
            </div>
        </div>

        <div class="profile-card">
            <h3><i class="fas fa-lock"></i> Account Settings</h3>

            <div class="form-group">
                <label>Current Password</label>
                <div class="je-password-wrap">
                    <input type="password" name="current_password" placeholder="Enter current password to make changes" autocomplete="current-password">
                    <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="photo-note">Leave blank to keep your current password. Required to set a new one.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="je-password-wrap">
                        <input type="password" name="new_password" placeholder="Min. 8 characters" minlength="8" autocomplete="new-password">
                        <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="je-password-wrap">
                        <input type="password" name="confirm_password" placeholder="Repeat the new password" minlength="8" autocomplete="new-password">
                        <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save All Changes</button>
                <a href="/agent/dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <h3 style="margin-top:32px; color:#DC2626; border-bottom-color:#FECACA;"><i class="fas fa-exclamation-triangle" style="color:#DC2626;"></i> Danger Zone</h3>
            <p style="font-size:13px; color:#666; margin-bottom:12px;">Deactivating your account hides all your listings but preserves your data. To permanently delete, contact support.</p>
            <form method="POST" action="/api/agent/deactivate.php" onsubmit="return confirm('WARNING: Deactivating will hide all your listings from public view. Continue?');" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="btn-danger"><i class="fas fa-user-slash"></i> Deactivate Account</button>
            </form>
        </div>
    </form>
</div>

<script>
document.getElementById('avatarUpload')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.querySelector('.profile-avatar-large');
            img.innerHTML = '<img src="' + e.target.result + '" alt="">';
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Combine first + last name into a single "name" field on submit
document.getElementById('profileForm')?.addEventListener('submit', function() {
    var first = this.querySelector('[name="name_first"]')?.value.trim() || '';
    var last  = this.querySelector('[name="name_last"]')?.value.trim()  || '';
    if (first || last) {
        var combined = (first + ' ' + last).trim();
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'name';
        hidden.value = combined;
        this.appendChild(hidden);
    }
});
</script>

</main>
</div>

<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
