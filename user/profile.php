<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — User Profile
 * Redesigned: JamesEdition buyer/profile layout
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';

        if (empty($errors)) {
            if (!empty($_POST['new_password'])) {
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $hash = $stmt->fetchColumn();
                if (!password_verify($_POST['current_password'] ?? '', $hash)) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("UPDATE users SET name=?, phone=?, address=?, password=? WHERE id=?")
                       ->execute([$name, $phone, $address, $newHash, $user_id]);
                    $success = 'Profile and password updated successfully.';
                }
            } else {
                $db->prepare("UPDATE users SET name=?, phone=?, address=? WHERE id=?")
                   ->execute([$name, $phone, $address, $user_id]);
                $success = 'Profile updated successfully.';
            }
            if ($success) $_SESSION['user_name'] = $name;
        }
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header('Location: /auth/login.php'); exit; }

// Stats
$savedCount = $db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$savedCount->execute([$user_id]);
$saved = $savedCount->fetchColumn();

$inquiryCount = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$inquiryCount->execute([$user_id]);
$inquiries = $inquiryCount->fetchColumn();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>

<style>
/* ============================================================
   KINAS GROUP — BUYER PROFILE PAGE
   Inspired by JamesEdition buyer/profile
   ============================================================ */

.profile-page {
    padding-top: 66px;
    min-height: 100vh;
    background: #f7f7f7;
}

/* ── PROFILE HERO / BANNER ── */
.profile-banner {
    background: #0A0A0A;
    padding: 40px;
    position: relative;
    overflow: hidden;
}
.profile-banner::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(198,164,63,0.08) 0%, transparent 60%);
    pointer-events: none;
}
.profile-banner-inner {
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 28px;
    position: relative;
}

.profile-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}
.profile-avatar-circle {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C6A43F, #A8882E);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Prata', serif;
    font-size: 32px;
    color: #0A0A0A;
    font-weight: 400;
    border: 3px solid rgba(198,164,63,0.4);
    overflow: hidden;
}
.profile-avatar-circle img { width: 100%; height: 100%; object-fit: cover; }

.profile-banner-info { flex: 1; }
.profile-banner-name {
    font-family: 'Prata', serif;
    font-size: 26px;
    font-weight: 400;
    color: #fff;
    margin-bottom: 4px;
}
.profile-banner-email {
    font-size: 13px;
    color: rgba(255,255,255,0.5);
    margin-bottom: 10px;
}
.profile-banner-badges { display: flex; flex-wrap: wrap; gap: 8px; }
.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.badge-buyer   { background: rgba(198,164,63,0.15); color: #C6A43F; border: 1px solid rgba(198,164,63,0.3); }
.badge-member  { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.1); }
.badge-verified { background: rgba(46,125,50,0.2); color: #66BB6A; border: 1px solid rgba(46,125,50,0.3); }

/* ── STATS ROW ── */
.profile-stats-row {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
}
.profile-stats-inner {
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
}
.stat-block {
    flex: 1;
    padding: 20px 28px;
    border-right: 1px solid #e8e8e8;
    text-align: center;
}
.stat-block:last-child { border-right: none; }
.stat-num {
    font-family: 'Prata', serif;
    font-size: 28px;
    color: #0A0A0A;
    font-weight: 400;
}
.stat-lbl { font-size: 11px; color: #999; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 2px; }

/* ── MAIN CONTENT ── */
.profile-content {
    max-width: 1100px;
    margin: 0 auto;
    padding: 36px 40px 60px;
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 32px;
    align-items: start;
}

/* ── NAV SIDEBAR ── */
.profile-sidenav {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    overflow: hidden;
    position: sticky;
    top: 86px;
}
.sidenav-section-title {
    padding: 14px 18px 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #bbb;
    border-bottom: 1px solid #f0f0f0;
}
.sidenav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 18px;
    font-size: 13px;
    color: #555;
    text-decoration: none;
    transition: all 0.15s;
    border-bottom: 1px solid #f7f7f7;
}
.sidenav-link:hover  { background: #f9f9f9; color: #0A0A0A; }
.sidenav-link.active { background: rgba(198,164,63,0.06); color: #C6A43F; font-weight: 600; border-left: 3px solid #C6A43F; padding-left: 15px; }
.sidenav-link svg   { flex-shrink: 0; opacity: 0.6; }
.sidenav-link.active svg { opacity: 1; }

/* ── FORM CARDS ── */
.profile-main { display: flex; flex-direction: column; gap: 24px; }

.profile-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    overflow: hidden;
}
.profile-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.profile-card-title {
    font-family: 'Prata', serif;
    font-size: 17px;
    font-weight: 400;
    color: #0A0A0A;
}
.profile-card-subtitle { font-size: 12px; color: #999; margin-top: 1px; }
.profile-card-body { padding: 24px; }

/* Flash messages */
.flash-msg {
    padding: 12px 16px;
    border-radius: 3px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.flash-success { background: #E8F5E9; color: #2E7D32; border-left: 3px solid #2E7D32; }
.flash-error   { background: #FEF2F2; color: #DC2626; border-left: 3px solid #DC2626; }

/* Form elements */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid.single { grid-template-columns: 1fr; }

.je-form-group { margin-bottom: 0; }
.je-form-group label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #999;
    margin-bottom: 6px;
}
.je-form-group input,
.je-form-group textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    color: #0A0A0A;
    background: #fff;
    transition: border-color 0.2s;
}
.je-form-group input:focus,
.je-form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.08); }
.je-form-group input[readonly],
.je-form-group input[disabled] { background: #f7f7f7; color: #999; cursor: not-allowed; }

.je-form-hint { font-size: 11px; color: #bbb; margin-top: 4px; }

/* Save button */
.je-save-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 26px;
    background: #0A0A0A;
    color: #fff;
    border: none;
    border-radius: 3px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.3px;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 4px;
}
.je-save-btn:hover { background: #333; transform: translateY(-1px); }

/* Read-only display */
.readonly-field {
    padding: 11px 14px;
    background: #f7f7f7;
    border: 1px solid #e8e8e8;
    border-radius: 3px;
    font-size: 14px;
    color: #666;
}

/* Divider */
.form-divider { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }

/* Danger zone */
.danger-zone-text { font-size: 13px; color: #888; margin-bottom: 16px; }
.je-danger-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: transparent;
    color: #DC2626;
    border: 1.5px solid #DC2626;
    border-radius: 3px;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.je-danger-btn:hover { background: #FEF2F2; }

/* Responsive */
@media (max-width: 960px) {
    .profile-content { grid-template-columns: 1fr; padding: 24px 20px 40px; }
    .profile-sidenav { position: static; display: flex; overflow-x: auto; border-radius: 4px; }
    .sidenav-section-title { display: none; }
    .sidenav-link { border-bottom: none; border-right: 1px solid #f0f0f0; white-space: nowrap; flex-direction: column; gap: 4px; text-align: center; padding: 12px 16px; font-size: 11px; }
    .sidenav-link.active { border-left: none; border-bottom: 3px solid #C6A43F; padding-left: 16px; }
}
@media (max-width: 600px) {
    .profile-banner { padding: 24px 20px; }
    .profile-banner-inner { flex-direction: column; align-items: flex-start; gap: 16px; }
    .profile-stats-inner { flex-wrap: wrap; }
    .stat-block { min-width: 50%; }
    .form-grid { grid-template-columns: 1fr; }
}

/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    .profile-page { background: #f7f7f7 !important; }
    .profile-banner { background: #0A0A0A !important; }
    .profile-banner::before { background: linear-gradient(135deg, rgba(198,164,63,0.08) 0%, transparent 60%) !important; }
    .profile-avatar-circle { background: linear-gradient(135deg, #C6A43F, #A8882E) !important; color: #0A0A0A !important; }
    .profile-banner-name { color: #fff !important; }
    .profile-banner-email { color: rgba(255,255,255,0.5) !important; }
    .badge-buyer { background: rgba(198,164,63,0.15) !important; color: #C6A43F !important; }
    .badge-member { background: rgba(255,255,255,0.07) !important; color: rgba(255,255,255,0.5) !important; }
    .badge-verified { background: rgba(46,125,50,0.2) !important; color: #66BB6A !important; }
    .profile-stats-row { background: #fff !important; }
    .stat-num { color: #0A0A0A !important; }
    .stat-lbl { color: #999 !important; }
    .profile-sidenav { background: #fff !important; }
    .sidenav-section-title { color: #bbb !important; }
    .sidenav-link { color: #555 !important; }
    .sidenav-link:hover { background: #f9f9f9 !important; color: #0A0A0A !important; }
    .sidenav-link.active { background: rgba(198,164,63,0.06) !important; color: #C6A43F !important; }
    .profile-card { background: #fff !important; }
    .profile-card-title { color: #0A0A0A !important; }
    .profile-card-subtitle { color: #999 !important; }
    .flash-success { background: #E8F5E9 !important; color: #2E7D32 !important; }
    .flash-error { background: #FEF2F2 !important; color: #DC2626 !important; }
    .je-form-group label { color: #999 !important; }
    .je-form-group input,
.je-form-group textarea { color: #0A0A0A !important; background: #fff !important; }
    .je-form-group input:focus,
.je-form-group textarea:focus { border-color: #C6A43F !important; }
    .je-form-group input[readonly],
.je-form-group input[disabled] { background: #f7f7f7 !important; color: #999 !important; }
    .je-form-hint { color: #bbb !important; }
    .je-save-btn { background: #0A0A0A !important; color: #fff !important; }
    .je-save-btn:hover { background: #333 !important; }
    .readonly-field { background: #f7f7f7 !important; color: #666 !important; }
    .danger-zone-text { color: #888 !important; }
    .je-danger-btn { color: #DC2626 !important; }
    .je-danger-btn:hover { background: #FEF2F2 !important; }
}
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/user-sidebar.php"; ?>
<main class="je-dash-main">

<div class="profile-page">

    <!-- ── BANNER ── -->
    <div class="profile-banner">
        <div class="profile-banner-inner">
            <div class="profile-avatar-wrap">
                <div class="profile-avatar-circle">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
            </div>
            <div class="profile-banner-info">
                <div class="profile-banner-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="profile-banner-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="profile-banner-badges">
                    <span class="profile-badge badge-buyer">Buyer Account</span>
                    <span class="profile-badge badge-member">Member since <?php echo date('Y', strtotime($user['created_at'])); ?></span>
                    <?php if (!empty($user['email_verified_at'])): ?>
                    <span class="profile-badge badge-verified">✓ Email Verified</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── STATS ── -->
    <div class="profile-stats-row">
        <div class="profile-stats-inner">
            <div class="stat-block">
                <div class="stat-num"><?php echo number_format($saved); ?></div>
                <div class="stat-lbl">Saved Listings</div>
            </div>
            <div class="stat-block">
                <div class="stat-num"><?php echo number_format($inquiries); ?></div>
                <div class="stat-lbl">Enquiries Sent</div>
            </div>
            <div class="stat-block">
                <div class="stat-num"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                <div class="stat-lbl">Member Since</div>
            </div>
            <div class="stat-block">
                <div class="stat-num"><?php echo ucfirst($user['role'] ?? 'Buyer'); ?></div>
                <div class="stat-lbl">Account Type</div>
            </div>
        </div>
    </div>

    <!-- ── CONTENT AREA ── -->
    <div class="profile-content">

        <!-- Sidebar nav -->
        <nav class="profile-sidenav">
            <div class="sidenav-section-title">Account</div>
            <a href="/user/profile.php" class="sidenav-link active">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                My Profile
            </a>
            <a href="/user/saved-listings.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Saved Listings
            </a>
            <a href="/user/my-inquiries.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                My Enquiries
            </a>
            <a href="/user/messages.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Messages
            </a>
            <a href="/user/settings.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
            <div class="sidenav-section-title" style="border-top:1px solid #f0f0f0;margin-top:4px;">Browse</div>
            <a href="/divisions/kinas-automobile/search.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2zM3 9h18M9 3v4m6-4v4"/></svg>
                Browse Cars
            </a>
            <a href="/divisions/williams-connect-home/search.php" class="sidenav-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Properties
            </a>
        </nav>

        <!-- Main content -->
        <div class="profile-main">

            <?php foreach ($errors as $e): ?>
            <div class="flash-msg flash-error">⚠ <?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
            <?php if ($success): ?>
            <div class="flash-msg flash-success">✓ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" id="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <!-- Personal Information -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <div class="profile-card-title">Personal Information</div>
                            <div class="profile-card-subtitle">Update your name, phone, and address</div>
                        </div>
                    </div>
                    <div class="profile-card-body">
                        <div class="form-grid" style="margin-bottom:16px;">
                            <div class="je-form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name"
                                       value="<?php echo htmlspecialchars($user['name']); ?>"
                                       required placeholder="Your full name">
                            </div>
                            <div class="je-form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="+234 000 000 0000">
                            </div>
                        </div>
                        <div class="form-grid single" style="margin-bottom:16px;">
                            <div class="je-form-group">
                                <label>Address</label>
                                <textarea name="address" rows="3"
                                    placeholder="Your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="je-save-btn" name="save_profile" value="1">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Save Changes
                        </button>
                    </div>
                </div>

                <!-- Account Information (read-only) -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <div class="profile-card-title">Account Details</div>
                            <div class="profile-card-subtitle">These fields cannot be changed</div>
                        </div>
                    </div>
                    <div class="profile-card-body">
                        <div class="form-grid">
                            <div class="je-form-group">
                                <label>Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <div class="je-form-hint">To change your email, contact support.</div>
                            </div>
                            <div class="je-form-group">
                                <label>Account Role</label>
                                <input type="text" value="<?php echo ucfirst($user['role'] ?? 'Buyer'); ?>" readonly>
                            </div>
                            <div class="je-form-group">
                                <label>Member Since</label>
                                <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                            </div>
                            <div class="je-form-group">
                                <label>Account Status</label>
                                <input type="text" value="Active" readonly style="color:#2E7D32;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <div class="profile-card-title">Change Password</div>
                            <div class="profile-card-subtitle">Leave blank to keep your current password</div>
                        </div>
                    </div>
                    <div class="profile-card-body">
                        <div class="form-grid" style="margin-bottom:4px;">
                            <div class="je-form-group">
                                <label>Current Password</label>
                                <div class="je-password-wrap">
                                    <input type="password" name="current_password"
                                           autocomplete="current-password"
                                           placeholder="Enter current password">
                                    <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="je-form-group">
                                <label>New Password</label>
                                <div class="je-password-wrap">
                                    <input type="password" name="new_password"
                                           minlength="8" autocomplete="new-password"
                                           placeholder="Minimum 8 characters"
                                           id="new-pw-input">
                                    <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="pw-strength" style="margin-bottom:16px;height:4px;border-radius:2px;background:#f0f0f0;overflow:hidden;">
                            <div id="pw-bar" style="height:100%;width:0;background:#C6A43F;transition:width 0.3s,background 0.3s;border-radius:2px;"></div>
                        </div>
                        <button type="submit" class="je-save-btn" name="save_password" value="1">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Update Password
                        </button>
                    </div>
                </div>

            </form>

            <!-- Danger Zone -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <div class="profile-card-title" style="color:#DC2626;">Danger Zone</div>
                        <div class="profile-card-subtitle">Irreversible account actions</div>
                    </div>
                </div>
                <div class="profile-card-body">
                    <p class="danger-zone-text">
                        Deleting your account will permanently remove all your data including saved listings,
                        enquiry history, and profile information. This action cannot be undone.
                    </p>
                    <button class="je-danger-btn" onclick="confirmDelete()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        Delete My Account
                    </button>
                </div>
            </div>

        </div><!-- /profile-main -->
    </div><!-- /profile-content -->
</div><!-- /profile-page -->

<script>
// Password strength meter
document.getElementById('new-pw-input')?.addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)  score += 25;
    if (v.length >= 12) score += 15;
    if (/[A-Z]/.test(v)) score += 20;
    if (/[0-9]/.test(v)) score += 20;
    if (/[^A-Za-z0-9]/.test(v)) score += 20;
    const bar = document.getElementById('pw-bar');
    bar.style.width = Math.min(score, 100) + '%';
    bar.style.background = score < 40 ? '#DC2626' : score < 70 ? '#F59E0B' : '#2E7D32';
});

function confirmDelete() {
    kinasConfirm(
        'Are you absolutely sure you want to delete your account? All your data will be permanently erased.',
        function() {
            kinasConfirm(
                'Last chance — this will permanently erase all your data and cannot be undone. Continue?',
                function() { window.location.href = '/user/delete-account.php'; },
                { title: 'Final Confirmation', confirm: 'Yes, Delete Everything', warning: 'There is no recovery after this step.' }
            );
        },
        { title: 'Delete Account', warning: 'This action cannot be undone.' }
    );
}
</script>

</main>
</div>

<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
