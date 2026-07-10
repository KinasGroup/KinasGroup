<?php
/**
 * KINAS GROUP — Reset Password
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: /auth/forgot-password.php');
    exit;
}
$csrf_token = Security::generateCSRFToken();
$error   = SessionManager::getFlash('error') ?? '';
$success = !empty($_GET['success']);
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
    <title>Reset Password - KINAS GROUP</title>
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
        .reset-icon-wrap {
            width: 96px; height: 96px; border-radius: 50%;
            margin: 0 auto 28px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }
        .reset-icon-wrap.success { background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%); }
        .reset-icon-wrap.success i { font-size: 2.6rem; color: #1B5E20; }
        .reset-icon-wrap.edit    { background: linear-gradient(135deg, #FFF8E1 0%, #FCE4B6 100%); }
        .reset-icon-wrap.edit    i { font-size: 2.4rem; color: #B45309; }
        .password-rules {
            background: rgba(255,255,255,0.05); border-radius: 12px;
            padding: 22px; border: 1px solid rgba(255,255,255,0.08);
        }
        .password-rules .label {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: #C6A43F; margin-bottom: 12px; font-weight: 700;
        }
        .password-rules ul { list-style: none; padding: 0; margin: 0; color: rgba(255,255,255,0.75); font-size: 14px; line-height: 2; }
        .password-rules i { color: #C6A43F; width: 22px; text-align: center; margin-right: 8px; }
        
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
            .je-auth-aside .password-rules {
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
            .je-form-row {
                grid-template-columns: 1fr !important;
                gap: 0 !important;
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
            .reset-icon-wrap {
                width: 72px;
                height: 72px;
            }
            .reset-icon-wrap i {
                font-size: 1.8rem;
            }
        }
        .je-password-wrap {
            display: flex;
            align-items: center;
            position: relative;
        }
        .je-password-wrap input {
            flex: 1;
            padding-right: 44px !important;
        }
        .je-password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            padding: 8px;
            font-size: 16px;
        }
        .je-password-toggle:hover {
            color: #666;
        }
    </style>
</head>
<body>

<div class="je-auth-shell">
    <!-- ── Left aside: new key / security themed ── -->
    <aside class="je-auth-aside">
        <a href="../index.php" class="je-auth-brand">
            <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'">
            <span>KINAS GROUP</span>
        </a>
        <div>
            <h1 class="je-auth-headline">A new key to your account.</h1>
            <p class="je-auth-sub">Pick something strong. Something you haven't used elsewhere. Your old password will stop working the moment you save the new one.</p>
        </div>
        <div class="password-rules">
            <div class="label">A great password has</div>
            <ul>
                <li><i class="fas fa-check"></i> At least 8 characters</li>
                <li><i class="fas fa-check"></i> Uppercase &amp; lowercase letters</li>
                <li><i class="fas fa-check"></i> At least one number</li>
                <li><i class="fas fa-check"></i> No reuse from your other accounts</li>
                <li><i class="fas fa-check"></i> A password manager helps you cheat (the good way)</li>
            </ul>
        </div>
    </aside>

    <!-- ── Right form: new password entry OR success state ── -->
    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width: 460px;">

            <?php if ($success): ?>
                <div style="text-align:center;">
                    <div class="reset-icon-wrap success">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                    </div>
                    <h2>Password reset successful</h2>
                    <p class="je-auth-sub-form">Your password has been updated. You can now sign in with your new password.</p>
                    <a href="login.php" class="je-btn je-btn-gold je-btn-block je-btn-lg" style="margin-top:12px;">
                        <i class="fas fa-sign-in-alt"></i>&nbsp; Sign In Now
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align:center;">
                    <div class="reset-icon-wrap edit">
                        <i class="fas fa-lock-open" aria-hidden="true"></i>
                    </div>
                    <h2>Create a new password</h2>
                    <p class="je-auth-sub-form">Choose a strong password you haven't used before.</p>
                </div>

                <?php if ($error): ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form method="POST" action="/api/auth/reset-password.php" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="je-form-group">
                        <label for="password">New Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8" autocomplete="new-password">
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p style="font-size:11px; color:#888; margin-top:4px;">At least 8 characters with uppercase, lowercase, and numbers.</p>
                    </div>
                    <div class="je-form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password" required autocomplete="new-password">
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="je-btn je-btn-gold je-btn-block je-btn-lg">
                        <i class="fas fa-check"></i>&nbsp; Update Password
                    </button>
                </form>

                <div class="je-auth-switch" style="text-align:center;">
                    <a href="login.php"><i class="fas fa-arrow-left"></i>&nbsp; Back to Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
