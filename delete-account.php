<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * KINAS GROUP — Delete Account (Self-Service Soft Delete)
 *
 * Allows a logged-in user/agent to permanently deactivate their own
 * account. The account is SOFT-DELETED (status = 'deleted'), not
 * hard-deleted, so data integrity is preserved.
 *
 * After deletion the user is logged out. If they later log in with
 * the same credentials, they are shown the reactivation page
 * (auth/reactivate-account.php) instead of the dashboard.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';

SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$csrf    = Security::generateCSRFToken();

// Load user info for display
$stmt = $db->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Gather account data summary so the user knows what they're deleting
$savedCount = 0;
$messageCount = 0;
$listingCount = 0;

try {
    $sc = $db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $sc->execute([$user_id]);
    $savedCount = (int)$sc->fetchColumn();
} catch (Throwable $e) {}

try {
    $mc = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $mc->execute([$user_id, $user_id]);
    $messageCount = (int)$mc->fetchColumn();
} catch (Throwable $e) {}

if ($user['role'] === 'agent') {
    try {
        $lc = $db->query("SELECT
            (SELECT COUNT(*) FROM car_listings WHERE agent_id = $user_id) +
            (SELECT COUNT(*) FROM property_listings WHERE agent_id = $user_id) +
            (SELECT COUNT(*) FROM solar_listings WHERE agent_id = $user_id) +
            (SELECT COUNT(*) FROM marketplace_listings WHERE agent_id = $user_id)
        ");
        $listingCount = (int)$lc->fetchColumn();
    } catch (Throwable $e) {}
}

$pageTitle = 'Delete Account - KINAS GROUP';
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#F5F7FA}

.da-wrap{max-width:680px;margin:0 auto;padding:40px 20px 80px}

.da-back{display:inline-flex;align-items:center;gap:8px;color:#666;font-size:13px;text-decoration:none;margin-bottom:24px;transition:color .2s}
.da-back:hover{color:#0A0A0A}

.da-card{background:#fff;border:2px solid #FECACA;border-radius:20px;overflow:hidden}

.da-card-header{background:#FEF2F2;padding:32px 36px;border-bottom:1px solid #FECACA}
.da-card-header h1{font-family:'Prata',serif;font-size:26px;color:#DC2626;margin-bottom:8px;display:flex;align-items:center;gap:12px}
.da-card-header h1 i{font-size:28px}
.da-card-header p{color:#666;font-size:14px;line-height:1.6}

.da-card-body{padding:36px}

.da-impact{margin-bottom:28px}
.da-impact h3{font-size:15px;font-weight:700;color:#0A0A0A;margin-bottom:16px}
.da-impact-item{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #F5F5F5}
.da-impact-item:last-child{border-bottom:none}
.da-impact-item i{color:#DC2626;font-size:16px;margin-top:2px;flex-shrink:0}
.da-impact-item .label{font-size:14px;font-weight:600;color:#0A0A0A}
.da-impact-item .desc{font-size:12px;color:#888;margin-top:2px}
.da-impact-item .count{margin-left:auto;background:#FEF2F2;color:#DC2626;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap}

.da-note{background:#FFF8E1;border:1px solid #FFE082;border-radius:12px;padding:16px 18px;margin-bottom:28px}
.da-note p{font-size:13px;color:#7A5B00;line-height:1.6;margin:0}
.da-note strong{display:block;margin-bottom:4px}

.da-form-group{margin-bottom:20px}
.da-form-group label{display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px}
.da-form-group label i{color:#DC2626;margin-right:6px}
.da-form-group input{width:100%;padding:12px 14px;border:1px solid #E0E0E0;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;box-sizing:border-box}
.da-form-group input:focus{outline:none;border-color:#DC2626;box-shadow:0 0 0 3px rgba(220,38,38,.08)}

.da-confirm-check{display:flex;align-items:flex-start;gap:10px;margin-bottom:24px;cursor:pointer}
.da-confirm-check input{margin-top:3px;accent-color:#DC2626;width:16px;height:16px;flex-shrink:0}
.da-confirm-check span{font-size:13px;color:#555;line-height:1.5}

.da-actions{display:flex;gap:12px;flex-wrap:wrap}

.da-btn-cancel{flex:1;padding:14px 20px;background:#F5F5F5;color:#333;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;text-align:center;transition:background .2s}
.da-btn-cancel:hover{background:#E8E8E8}

.da-btn-delete{flex:1;padding:14px 20px;background:#DC2626;color:#fff;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px}
.da-btn-delete:hover:not(:disabled){background:#B91C1C;transform:translateY(-1px)}
.da-btn-delete:disabled{opacity:.5;cursor:not-allowed}

.da-error{display:none;margin-top:16px;padding:14px 16px;background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;border-radius:10px;font-size:13px}

.da-alternative{margin-top:24px;padding-top:20px;border-top:1px solid #F0F0F0;text-align:center}
.da-alternative p{font-size:13px;color:#888;margin-bottom:12px}
.da-alternative a{color:#C6A43F;text-decoration:none;font-weight:600;font-size:13px}
.da-alternative a:hover{text-decoration:underline}

@media(max-width:600px){
    .da-wrap{padding:20px 16px 60px}
    .da-card-header{padding:24px 20px}
    .da-card-body{padding:24px 20px}
    .da-actions{flex-direction:column}
}
</style>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/' . ($user['role'] === 'agent' ? 'agent' : 'user') . '-sidebar.php'; ?>

    <main class="je-dash-main">
        <div class="da-wrap">
            <a href="<?= $user['role'] === 'agent' ? '/agent/profile.php' : '/user/profile.php' ?>" class="da-back">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>

            <div class="da-card">
                <div class="da-card-header">
                    <h1><i class="fas fa-exclamation-triangle"></i> Delete Your Account</h1>
                    <p>
                        You are about to delete your KINAS GROUP account
                        (<strong><?= htmlspecialchars($user['email']) ?></strong>).
                        This action will deactivate your account and remove your access to the platform.
                    </p>
                </div>

                <div class="da-card-body">
                    <!-- What will be affected -->
                    <div class="da-impact">
                        <h3>What will happen when you delete your account:</h3>

                        <div class="da-impact-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <div>
                                <div class="label">You will be signed out immediately</div>
                                <div class="desc">You will lose access to your dashboard and all platform features.</div>
                            </div>
                        </div>

                        <?php if ($savedCount > 0): ?>
                        <div class="da-impact-item">
                            <i class="fas fa-heart"></i>
                            <div>
                                <div class="label">Saved listings will be hidden</div>
                                <div class="desc">Your saved/favourited listings will no longer be accessible.</div>
                            </div>
                            <span class="count"><?= $savedCount ?> saved</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($messageCount > 0): ?>
                        <div class="da-impact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <div class="label">Messages will be hidden</div>
                                <div class="desc">Your conversation history will no longer be visible to you.</div>
                            </div>
                            <span class="count"><?= $messageCount ?> messages</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['role'] === 'agent' && $listingCount > 0): ?>
                        <div class="da-impact-item">
                            <i class="fas fa-list-ul"></i>
                            <div>
                                <div class="label">All your listings will be removed from public view</div>
                                <div class="desc">Your active listings across all divisions will be hidden from buyers.</div>
                            </div>
                            <span class="count"><?= $listingCount ?> listings</span>
                        </div>
                        <?php endif; ?>

                        <div class="da-impact-item">
                            <i class="fas fa-user-slash"></i>
                            <div>
                                <div class="label">Your profile will be deactivated</div>
                                <div class="desc">Other users will no longer be able to see or contact you.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Reactivation note -->
                    <div class="da-note">
                        <p>
                            <strong><i class="fas fa-info-circle"></i> You can reactivate later</strong>
                            Your account data is preserved. If you change your mind, you can log in
                            with your email and password to reactivate your account. However, if an
                            administrator permanently deletes your account, reactivation will not be possible.
                        </p>
                    </div>

                    <!-- Deletion form -->
                    <form id="deleteAccountForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                        <div class="da-form-group">
                            <label><i class="fas fa-lock"></i> Confirm Your Password</label>
                            <input type="password" name="password" id="daPassword"
                                   placeholder="Enter your password to confirm" required
                                   autocomplete="current-password">
                        </div>

                        <div class="da-form-group">
                            <label><i class="fas fa-envelope"></i> Type Your Email to Confirm</label>
                            <input type="email" name="confirm_email" id="daConfirmEmail"
                                   placeholder="<?= htmlspecialchars($user['email']) ?>" required
                                   autocomplete="email">
                        </div>

                        <label class="da-confirm-check">
                            <input type="checkbox" id="daConfirmCheck" required>
                            <span>
                                I understand that deleting my account will sign me out, hide my listings
                                and messages, and deactivate my profile. I understand I can reactivate
                                by logging in again.
                            </span>
                        </label>

                        <div class="da-actions">
                            <a href="<?= $user['role'] === 'agent' ? '/agent/profile.php' : '/user/profile.php' ?>" class="da-btn-cancel">
                                Cancel — Keep My Account
                            </a>
                            <button type="submit" class="da-btn-delete" id="daSubmitBtn" disabled>
                                <i class="fas fa-trash-alt"></i> Delete My Account
                            </button>
                        </div>

                        <div class="da-error" id="daError"></div>
                    </form>

                    <!-- Alternative: contact support -->
                    <div class="da-alternative">
                        <p>Having second thoughts or need help? Contact our support team instead.</p>
                        <a href="/pages/contact.php"><i class="fas fa-headset"></i> Contact Support</a>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var form = document.getElementById('deleteAccountForm');
            var btn = document.getElementById('daSubmitBtn');
            var check = document.getElementById('daConfirmCheck');
            var emailInput = document.getElementById('daConfirmEmail');
            var errorDiv = document.getElementById('daError');
            var expectedEmail = <?= json_encode(strtolower($user['email'])) ?>;

            // Enable/disable submit based on checkbox + email match
            function updateBtnState() {
                var emailMatch = emailInput.value.trim().toLowerCase() === expectedEmail;
                btn.disabled = !(check.checked && emailMatch);
            }

            check.addEventListener('change', updateBtnState);
            emailInput.addEventListener('input', updateBtnState);

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var password = document.getElementById('daPassword').value;
                var confirmEmail = emailInput.value.trim().toLowerCase();

                if (!password) {
                    showError('Please enter your password.');
                    return;
                }

                if (confirmEmail !== expectedEmail) {
                    showError('The email you typed does not match your account email.');
                    return;
                }

                if (!check.checked) {
                    showError('Please check the confirmation box.');
                    return;
                }

                // Disable button and show loading
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting Account…';
                errorDiv.style.display = 'none';

                fetch('/api/user/delete-account.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: form.csrf_token.value,
                        password: password,
                        confirm_email: confirmEmail
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        // Redirect to login with a message
                        window.location.href = '/auth/login.php?deleted=1';
                    } else {
                        showError(data.error || 'Failed to delete account. Please try again.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
                    }
                })
                .catch(function() {
                    showError('Network error. Please check your connection and try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete My Account';
                });
            });

            function showError(msg) {
                errorDiv.textContent = msg;
                errorDiv.style.display = 'block';
            }
        })();
        </script>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
