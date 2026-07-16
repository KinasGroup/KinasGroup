<?php
require_once '../includes/dotenv.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/recaptcha.php';

if (SessionManager::isLoggedIn()) {
    $role = SessionManager::getUserRole();
    header('Location: ' . ($role === 'admin' ? '../admin/dashboard.php' : ($role === 'agent' ? '../agent/dashboard.php' : '../user/dashboard.php')));
    exit;
}

$csrfToken = Security::generateCSRFToken();
$errorMessage = SessionManager::getFlash('error');
$successMessage = SessionManager::getFlash('success');

$divisions = [
    'automobile'    => 'KINAS Automobile',
    'real_estate'   => 'Williams Connect Home',
    'marketplace'   => 'KINAS Marketplace',
];

$recaptcha = getRecaptchaKeys();
?>
<!DOCTYPE html>
<html lang="en" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/favicon.php'; ?>
    <title>Agent Registration - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your existing styles ... -->
</head>
<body>

<div class="je-auth-shell">
    <!-- Your existing aside and form structure ... -->

    <form id="registerForm" method="POST" action="../api/auth/register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- Your existing fields ... -->

        <div class="je-form-group" id="captcha-group">
            <div id="captcha-container"></div>
            <input type="hidden" id="captcha-token" name="g-recaptcha-response">
        </div>

        <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
            Create Account &amp; Continue
        </button>
    </form>

    <!-- ... rest of your HTML ... -->
</div>

<script>
const captchaSiteKey = '<?= htmlspecialchars($recaptcha['site']) ?>';
const isCaptchaConfigured = captchaSiteKey && captchaSiteKey.length > 30;

if (isCaptchaConfigured) {
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=onCaptchaLoad&render=explicit';
    s.async = true; s.defer = true;
    document.head.appendChild(s);
} else {
    document.getElementById('captcha-group').style.display = 'none';
}

function onCaptchaLoad() {
    if (!isCaptchaConfigured) return;
    grecaptcha.render('captcha-container', {
        sitekey: captchaSiteKey,
        callback: function(token) {
            document.getElementById('captcha-token').value = token;
        }
    });
}

// Your existing form submit handler remains the same...
</script>

<?php require_once __DIR__ . '/../includes/kinas-ui.php'; ?>
<?php require_once __DIR__ . '/../includes/password-toggle.php'; ?>
</body>
</html>
