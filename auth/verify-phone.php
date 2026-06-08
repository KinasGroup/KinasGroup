<?php
/**
 * KINAS GROUP — Phone verification page (post-registration or login)
 *
 * Two-step:
 *  1. Enter phone (or use the one on file) → request OTP
 *  2. Enter 6-digit code → confirm
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireLogin();
$userId = (int)$_SESSION['user_id'];
$csrf = Security::generateCSRFToken();

$phone = '';
$success = SessionManager::getFlash('success');
$error = SessionManager::getFlash('error');

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT name, email, phone, phone_verified_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: /auth/login.php'); exit; }

if (!empty($user['phone_verified_at'])) {
    // Already verified, bounce to dashboard
    $role = $_SESSION['user_role'] ?? 'user';
    $dash = $role === 'admin' ? '/admin/dashboard.php' : ($role === 'agent' ? '/agent/dashboard.php' : '/user/dashboard.php');
    header('Location: ' . $dash); exit;
}

$phone = $user['phone'] ?? '';
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Phone - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .je-otp-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; max-width: 360px; }
        .je-otp-grid input { text-align: center; font-size: 22px; font-weight: 600; padding: 14px 0; border: 1px solid var(--je-line); border-radius: 4px; font-family: 'Inter', sans-serif; }
        .je-otp-grid input:focus { outline: none; border-color: var(--je-gold); box-shadow: 0 0 0 3px rgba(198,164,63,0.12); }
    </style>
</head>
<body>

<div class="je-auth-shell">
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span>KINAS GROUP</span>
        </a>
        <div>
            <h1 class="je-auth-headline">One quick step.</h1>
            <p class="je-auth-sub">Confirming your phone number protects your account and lets us reach you about KYC decisions, listing updates, and buyer messages.</p>
        </div>
        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px; border: 1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#C6A43F; margin-bottom:8px;">What happens next</div>
            <ul style="list-style:none; padding:0; margin:0; color:rgba(255,255,255,0.7); font-size:13px; line-height:1.8;">
                <li><i class="fas fa-shield-alt" style="color:#C6A43F; width:18px;"></i> MetaMap verifies your identity</li>
                <li><i class="fas fa-building" style="color:#C6A43F; width:18px;"></i> Admin reviews your CAC document</li>
                <li><i class="fas fa-check-circle" style="color:#C6A43F; width:18px;"></i> You're a verified agent — list freely</li>
            </ul>
        </div>
    </aside>

    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width:480px;">
            <h2>Verify your phone</h2>
            <p class="je-auth-sub-form">We'll send a 6-digit code via SMS. Standard rates apply.</p>

            <?php if ($success): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form id="phoneForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="je-form-group" id="phoneStep">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+234 800 000 0000" value="<?= htmlspecialchars($phone) ?>" required>
                    <p style="font-size:11px; color:#888; margin-top:4px;">Format: +234 800 000 0000 or local 080...</p>
                </div>
                <button type="button" id="sendOtpBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>

            <form id="otpForm" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="phone" id="otpPhone">

                <div class="je-form-group">
                    <label>Enter the 6-digit code sent to <span id="otpPhoneDisplay" style="color:#0A0A0A; font-weight:600;"></span></label>
                    <div class="je-otp-grid" id="otpGrid">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="0" autofocus>
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="1">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="2">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="3">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="4">
                        <input type="text" inputmode="numeric" maxlength="1" class="otp-cell" data-idx="5">
                    </div>
                    <p style="font-size:11px; color:#888; margin-top:10px;">Code expires in 10 minutes. <a href="#" id="resendLink" style="color:#C6A43F; font-weight:600;">Resend code</a></p>
                </div>
                <button type="submit" id="verifyBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    Verify Phone
                </button>
            </form>

            <?php if ($appEnv === 'development'): ?>
                <p style="font-size:11px; color:#888; margin-top:16px; text-align:center;">Dev mode: Termii is mocked. Use code <strong>000000</strong>.</p>
            <?php endif; ?>

            <div class="je-auth-switch">
                <a href="../auth/logout.php">Sign out</a>
            </div>
        </div>
    </main>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';
const phoneForm = document.getElementById('phoneForm');
const otpForm = document.getElementById('otpForm');
const sendBtn = document.getElementById('sendOtpBtn');
const verifyBtn = document.getElementById('verifyBtn');
const phoneInput = document.getElementById('phone');
const otpPhone = document.getElementById('otpPhone');
const otpPhoneDisplay = document.getElementById('otpPhoneDisplay');
const otpCells = document.querySelectorAll('.otp-cell');

function showToast(msg, type) {
    let t = document.getElementById('__toast');
    if (!t) { t = document.createElement('div'); t.id='__toast'; t.className='toast'; document.body.appendChild(t); }
    t.textContent = msg; t.className = 'toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 4500);
}

sendBtn.addEventListener('click', async () => {
    const phone = phoneInput.value.trim();
    if (!phone) { showToast('Please enter your phone number', 'error'); return; }
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    try {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('phone', phone);
        fd.append('purpose', 'register');
        const res = await fetch('/api/auth/send-otp.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (res.ok && data.success) {
            phoneForm.style.display = 'none';
            otpForm.style.display = 'block';
            otpPhone.value = phone;
            otpPhoneDisplay.textContent = phone;
            otpCells[0].focus();
            if (data._dev_code) showToast('Dev code: ' + data._dev_code, 'success');
        } else {
            showToast(data.error || 'Could not send code', 'error');
        }
    } catch (e) { showToast('Network error', 'error'); }
    finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
    }
});

otpCells.forEach((cell, idx) => {
    cell.addEventListener('input', (e) => {
        const v = e.target.value.replace(/\D/g, '');
        e.target.value = v.slice(0, 1);
        if (v && idx < 5) otpCells[idx + 1].focus();
    });
    cell.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !cell.value && idx > 0) otpCells[idx - 1].focus();
        if (e.key === 'ArrowLeft' && idx > 0) otpCells[idx - 1].focus();
        if (e.key === 'ArrowRight' && idx < 5) otpCells[idx + 1].focus();
    });
    cell.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
        text.split('').forEach((ch, i) => { if (otpCells[i]) otpCells[i].value = ch; });
        otpCells[Math.min(text.length, 5)].focus();
    });
});

otpForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = Array.from(otpCells).map(c => c.value).join('');
    if (code.length !== 6) { showToast('Please enter all 6 digits', 'error'); return; }
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…';
    try {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('phone', otpPhone.value);
        fd.append('code', code);
        fd.append('purpose', 'register');
        const res = await fetch('/api/auth/verify-otp.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (res.ok && data.success) {
            showToast('Phone verified!', 'success');
            setTimeout(() => { window.location.href = '/agent/verification.php'; }, 800);
        } else {
            showToast(data.error || 'Verification failed', 'error');
        }
    } catch (err) { showToast('Network error', 'error'); }
    finally {
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = 'Verify Phone';
    }
});

document.getElementById('resendLink').addEventListener('click', (e) => {
    e.preventDefault();
    phoneForm.style.display = 'block';
    otpForm.style.display = 'none';
    sendBtn.click();
});
</script>
</body>
</html>
