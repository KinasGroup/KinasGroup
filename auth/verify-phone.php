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
require_once __DIR__ . '/../api/config/database.php';

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
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ============================================================
         FORCE LIGHT MODE - PERMANENT FIX
         ============================================================ -->
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="#ffffff">
    <style>
        /* Force light mode immediately */
        html, body { 
            color-scheme: light !important; 
            background: #ffffff !important;
        }
        @media (prefers-color-scheme: dark) {
            html, body {
                color-scheme: light !important;
                background: #ffffff !important;
                color: #0A0A0A !important;
            }
            .je-auth-shell,
            .je-auth-main,
            .je-auth-form {
                background-color: #ffffff !important;
                color: #0A0A0A !important;
            }
            .je-auth-aside {
                background-color: #0A0A0A !important;
                color: rgba(255,255,255,0.7) !important;
            }
            .je-auth-aside * {
                color: rgba(255,255,255,0.7) !important;
            }
            .je-auth-aside h1,
            .je-auth-aside .je-auth-headline {
                color: #ffffff !important;
            }
        }
        
        /* ============================================================
           MOBILE RESPONSIVENESS FIXES
           ============================================================ */
        .je-auth-shell {
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        .je-otp-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            max-width: 340px;
            margin: 0 auto;
        }
        .je-otp-grid input {
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            padding: 12px 0;
            border: 1px solid var(--je-line);
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            width: 100%;
            box-sizing: border-box;
        }
        .je-otp-grid input:focus {
            outline: none;
            border-color: var(--je-gold);
            box-shadow: 0 0 0 3px rgba(198,164,63,0.12);
        }
        
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 14px 28px;
            border-radius: 8px;
            background: #0A0A0A;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            max-width: 90%;
            text-align: center;
        }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background: #1B5E20; }
        .toast.error { background: #B71C1C; }
        .toast.info { background: #0047AB; }
        
        .je-auth-switch { 
            margin-top: 20px; 
            text-align: center; 
        }
        .je-auth-switch a { 
            color: #888; 
            text-decoration: none; 
            font-size: 13px; 
        }
        .je-auth-switch a:hover { 
            color: #C6A43F; 
        }
        .hidden { 
            display: none !important; 
        }
        
        /* ============================================================
           RESPONSIVE BREAKPOINTS
           ============================================================ */
        @media (max-width: 992px) {
            .je-auth-shell {
                grid-template-columns: 1fr !important;
            }
            .je-auth-aside {
                padding: 30px 24px 36px !important;
                min-height: 200px !important;
            }
            .je-auth-aside .je-auth-headline {
                font-size: 24px !important;
            }
            .je-auth-aside div:last-child {
                display: none !important;
            }
            .je-auth-main {
                padding: 30px 20px !important;
            }
            .je-auth-form {
                max-width: 100% !important;
                padding: 0 10px !important;
            }
            .je-auth-form h2 {
                font-size: 24px !important;
            }
            .je-otp-grid {
                max-width: 280px;
                gap: 6px;
            }
            .je-otp-grid input {
                font-size: 18px;
                padding: 10px 0;
            }
        }
        
        @media (max-width: 480px) {
            .je-auth-aside {
                padding: 20px 16px 24px !important;
                min-height: 150px !important;
            }
            .je-auth-aside .je-auth-headline {
                font-size: 20px !important;
            }
            .je-auth-main {
                padding: 20px 14px !important;
            }
            .je-auth-form h2 {
                font-size: 20px !important;
            }
            .je-auth-form .je-auth-sub-form {
                font-size: 13px !important;
            }
            .je-form-group input,
            .je-form-group select {
                font-size: 14px !important;
                padding: 10px 12px !important;
            }
            .je-btn-lg {
                padding: 12px 20px !important;
                font-size: 13px !important;
            }
            .je-auth-switch {
                font-size: 12px !important;
            }
            .je-otp-grid {
                max-width: 220px;
                gap: 4px;
            }
            .je-otp-grid input {
                font-size: 16px;
                padding: 8px 0;
            }
            .otp-input-group {
                flex-direction: column !important;
                align-items: center !important;
            }
            .otp-input-group .btn-verify-otp {
                width: 100% !important;
                justify-content: center !important;
            }
        }
    </style>
    <!-- ============================================================ -->
    
    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>
    <title>Verify Your Phone - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

            <div id="messageContainer">
                <?php if ($success): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
                <?php if ($error):   ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            </div>

            <!-- Step 1: Phone Number -->
            <form id="phoneForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="je-form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="+234 800 000 0000" value="<?= htmlspecialchars($phone) ?>" required>
                    <p style="font-size:11px; color:#888; margin-top:4px;">Format: +234 800 000 0000 or local 080...</p>
                </div>
                <button type="button" id="sendOtpBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>

            <!-- Step 2: OTP -->
            <form id="otpForm" class="hidden">
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
                    <p style="font-size:11px; color:#888; margin-top:10px; text-align:center;">
                        Code expires in 10 minutes. <a href="#" id="resendLink" style="color:#C6A43F; font-weight:600;">Resend code</a>
                    </p>
                </div>
                <button type="submit" id="verifyBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    <i class="fas fa-check"></i> Verify Phone
                </button>
            </form>

            <?php if ($appEnv === 'development' || $appEnv === 'local'): ?>
                <div style="background: #f0f0f0; padding: 12px; border-radius: 4px; margin-top: 16px; text-align: center; font-size: 13px; color: #666;">
                    <strong>Dev Mode:</strong> Check browser console or server logs for the OTP code.
                </div>
            <?php endif; ?>

            <div class="je-auth-switch">
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
            </div>
        </div>
    </main>
</div>

<!-- Toast Container -->
<div id="toast" class="toast"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';
    const phoneForm = document.getElementById('phoneForm');
    const otpForm = document.getElementById('otpForm');
    const sendBtn = document.getElementById('sendOtpBtn');
    const verifyBtn = document.getElementById('verifyBtn');
    const phoneInput = document.getElementById('phone');
    const otpPhone = document.getElementById('otpPhone');
    const otpPhoneDisplay = document.getElementById('otpPhoneDisplay');
    const otpCells = document.querySelectorAll('.otp-cell');
    const toast = document.getElementById('toast');
    let toastTimeout = null;

    function showToast(msg, type = 'info') {
        if (toastTimeout) clearTimeout(toastTimeout);
        toast.textContent = msg;
        toast.className = 'toast ' + type + ' show';
        toastTimeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
    }

    function showError(msg) {
        showToast(msg, 'error');
    }

    function showSuccess(msg) {
        showToast(msg, 'success');
    }

    function setButtonLoading(btn, loading, text) {
        if (loading) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + text;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.getAttribute('data-original') || btn.innerHTML;
        }
    }

    // Store original button text
    sendBtn.setAttribute('data-original', sendBtn.innerHTML);
    verifyBtn.setAttribute('data-original', verifyBtn.innerHTML);

    // Send OTP
    sendBtn.addEventListener('click', async function() {
        const phone = phoneInput.value.trim();
        if (!phone) {
            showError('Please enter your phone number.');
            return;
        }

        // Basic Nigerian phone validation
        const phoneRegex = /^(0[789][01]\d{8}|234[789][01]\d{8}|\+234[789][01]\d{8})$/;
        if (!phoneRegex.test(phone)) {
            showError('Please enter a valid Nigerian phone number (e.g., 08012345678).');
            return;
        }

        setButtonLoading(sendBtn, true, 'Sending...');

        try {
            // Send as JSON (matches your API expectations)
            const response = await fetch('/api/auth/send-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    phone: phone,
                    purpose: 'register'
                }),
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Hide phone form, show OTP form
                phoneForm.classList.add('hidden');
                otpForm.classList.remove('hidden');
                otpPhone.value = phone;
                otpPhoneDisplay.textContent = phone;
                
                showSuccess(data.message || 'Verification code sent!');
                
                // Focus first OTP input
                setTimeout(() => otpCells[0].focus(), 300);
                
                // If dev code is returned, show it
                if (data._dev_code) {
                    console.log('Dev OTP Code:', data._dev_code);
                    showToast('Dev Code: ' + data._dev_code, 'info');
                }
            } else {
                showError(data.error || data.message || 'Failed to send verification code. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Network error. Please check your connection and try again.');
        } finally {
            setButtonLoading(sendBtn, false, 'Send Verification Code');
        }
    });

    // OTP input handling
    otpCells.forEach((cell, idx) => {
        cell.addEventListener('input', (e) => {
            const v = e.target.value.replace(/\D/g, '');
            e.target.value = v.slice(0, 1);
            if (v && idx < 5) {
                otpCells[idx + 1].focus();
            }
        });

        cell.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !cell.value && idx > 0) {
                otpCells[idx - 1].focus();
            }
            if (e.key === 'ArrowLeft' && idx > 0) {
                otpCells[idx - 1].focus();
            }
            if (e.key === 'ArrowRight' && idx < 5) {
                otpCells[idx + 1].focus();
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });

        cell.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            text.split('').forEach((ch, i) => {
                if (otpCells[i]) otpCells[i].value = ch;
            });
            const nextIdx = Math.min(text.length, 5);
            if (otpCells[nextIdx]) {
                otpCells[nextIdx].focus();
            }
            if (text.length === 6) {
                setTimeout(() => verifyBtn.click(), 100);
            }
        });
    });

    // Verify OTP
    otpForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const code = Array.from(otpCells).map(c => c.value).join('');
        if (code.length !== 6) {
            showError('Please enter all 6 digits.');
            return;
        }

        if (!/^\d{6}$/.test(code)) {
            showError('Please enter a valid 6-digit numeric code.');
            return;
        }

        setButtonLoading(verifyBtn, true, 'Verifying...');

        try {
            const response = await fetch('/api/auth/verify-otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    phone: otpPhone.value,
                    code: code,
                    purpose: 'register'
                }),
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showSuccess(data.message || 'Phone verified successfully!');
                setTimeout(() => {
                    window.location.href = '/agent/verification.php';
                }, 1000);
            } else {
                showError(data.error || data.message || 'Invalid verification code. Please try again.');
                // Clear OTP fields on error
                otpCells.forEach(cell => cell.value = '');
                otpCells[0].focus();
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Network error. Please check your connection and try again.');
        } finally {
            setButtonLoading(verifyBtn, false, 'Verify Phone');
        }
    });

    // Resend OTP
    document.getElementById('resendLink').addEventListener('click', async (e) => {
        e.preventDefault();
        
        // Show phone form again
        phoneForm.classList.remove('hidden');
        otpForm.classList.add('hidden');
        
        // Clear OTP fields
        otpCells.forEach(cell => cell.value = '');
        
        // Auto-click send button
        sendBtn.click();
    });
});
</script>
</body>
</html>
