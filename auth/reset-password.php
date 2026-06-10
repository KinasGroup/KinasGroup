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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="je-auth-shell" style="grid-template-columns: 1fr;">
    <main class="je-auth-main">
        <div class="je-auth-form" style="max-width:460px;">
            <a href="../index.php" class="je-auth-brand" style="margin-bottom:32px; justify-content:center; color:#0A0A0A;">
                <img src="../assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" onerror="this.style.display='none'" style="height:30px;">
                <span style="color:#0A0A0A; font-family:'Prata',serif;">KINAS GROUP</span>
            </a>

            <?php if ($success): ?>
                <h2>Password reset successful</h2>
                <p class="je-auth-sub-form">Your password has been updated. You can now sign in with your new password.</p>
                <a href="login.php" class="je-btn je-btn-gold je-btn-block je-btn-lg" style="margin-top:8px;">Sign In Now</a>
            <?php else: ?>
                <h2>Create a new password</h2>
                <p class="je-auth-sub-form">Choose a strong password you haven't used before.</p>

                <?php if ($error): ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form method="POST" action="/api/auth/reset-password.php" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="je-form-group">
                        <label for="password">New Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8">
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p style="font-size:11px; color:#888; margin-top:4px;">At least 8 characters with uppercase, lowercase, and numbers.</p>
                    </div>
                    <div class="je-form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <div class="je-password-wrap">
                            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm new password" required>
                            <button type="button" class="je-password-toggle" aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="je-btn je-btn-gold je-btn-block je-btn-lg">Update Password</button>
                </form>

                <div class="je-auth-switch">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
