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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - KINAS GROUP</title>
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
            <h2>Forgot your password?</h2>
            <p class="je-auth-sub-form">Enter your email and we'll send you a secure link to reset it.</p>

            <?php if ($message): ?><div class="je-form-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="je-form-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" action="/api/auth/forgot-password.php" id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="je-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>
                <button type="submit" class="je-btn je-btn-gold je-btn-block je-btn-lg">Send Reset Link</button>
            </form>

            <div class="je-auth-switch">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Sign In</a>
            </div>
        </div>
    </main>
</div>

</body>
</html>
