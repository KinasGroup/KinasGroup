<?php
/**
 * KINAS GROUP — Forgot Password
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

$message = SessionManager::getFlash('success') ?? '';
$error   = SessionManager::getFlash('error') ?? '';
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ============================================================
         FORCE LIGHT MODE - PERMANENT FIX
         ============================================================ -->
    <meta name="color-scheme" content="only light">
    <meta name="theme-color" content="#ffffff">
    <style>
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
    </style>
    <!-- ============================================================ -->
    
    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>
    <title>Forgot Password - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ============================================================
         MOBILE RESPONSIVENESS FIXES
         ============================================================ -->
    <style>
        .je-auth-shell {
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        .forgot-icon-wrap {
            width: 96px; height: 96px; border-radius: 50%;
            margin: 0 auto 28px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #FFF8E1 0%, #FCE4B6 100%);
            box-shadow: 0 12px 32px rgba(198,164,63,0.18);
        }
        .forgot-icon-wrap i { font-size: 2.4rem; color: #B45309; }
        .security-list {
            background: rgba(255,255,255,0.05); border-radius: 12px;
            padding: 22px; border: 1px solid rgba(255,255,255,0.08);
        }
        .security-list .label {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: #C6A43F; margin-bottom: 12px; font-weight: 700;
        }
        .security-list ul { list-style: none; padding: 0; margin: 0; color: rgba(255,255,255,0.75); font-size: 14px; line-height: 2; }
        .security-list i { color: #C6A43F; width: 22px; text-align: center; margin-right: 8px; }
        
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
            .je-auth-aside .security-list {
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
            .je-form-group input {
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
            .forgot-icon-wrap {
                width: 72px;
                height: 72px;
            }
            .forgot-icon-wrap i {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<div class="je-auth-shell">
    <!-- ── Left aside: account security themed ── -->
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span>KINAS GROUP</span>
        </a>
        <div>
            <h1 class="je-auth-headline">Secure your account.</h1>
            <p class="je-auth-sub">Resetting your password takes a minute. We'll email you a one-time link — valid for 30 minutes — that lets you choose a new one.</p>
        </div>
        <div class="security-list">
            <div class="label">Your account is protected by</div>
            <ul>
                <li><i class="fas fa-shield-alt"></i> Bcrypt-hashed passwords (cost 12)</li>
                <li><i class="fas fa-lock"></i> Single-use, time-limited reset tokens</li>
                <li><i class="fas fa-history"></i> Full login &amp; activity audit trail</li>
                <li><i class="fas fa-user-shield"></i> Automatic lockout after 5 failed attempts</li>
            </ul>
        </div>
    </aside>

    <!-- ── Right form: email + reset trigger ── -->
    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width: 460px;">
            <div class="forgot-icon-wrap">
                <i class="fas fa-key" aria-hidden="true"></i>
            </div>
            <h2 style="text-align:center;">Forgot your password?</h2>
            <p class="je-auth-sub-form" style="text-align:center;">Enter your email and we'll send you a secure link to reset it.</p>

            <?php if ($message): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" action="/api/auth/forgot-password.php" id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="je-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required autocomplete="email">
                </div>
                <button type="submit" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                    <i class="fas fa-paper-plane"></i>&nbsp; Send Reset Link
                </button>
            </form>

            <div class="je-auth-switch" style="text-align:center;">
                <a href="login.php"><i class="fas fa-arrow-left"></i>&nbsp; Back to Sign In</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
