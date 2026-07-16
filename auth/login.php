<?php
require_once '../includes/dotenv.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/recaptcha.php';

$recaptcha = getRecaptchaKeys();

$pageTitle = 'Sign In - KINAS GROUP';
include '../templates/header.php';
?>

<!-- Your existing HTML ... -->

<form id="loginForm" method="POST" action="../api/auth/login.php" novalidate>
    <!-- ... fields ... -->

    <div class="je-form-group" id="captcha-group">
        <div id="login-captcha-container"></div>
        <input type="hidden" id="login-captcha-token" name="g-recaptcha-response">
    </div>

    <button type="submit" id="submitBtn" class="je-btn je-btn-gold je-btn-block je-btn-lg">
        Sign In
    </button>
</form>

<script>
const captchaSiteKey = '<?= htmlspecialchars($recaptcha['site']) ?>';
const isCaptchaConfigured = captchaSiteKey && captchaSiteKey.length > 30;

if (isCaptchaConfigured) {
    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?onload=onLoginCaptchaLoad&render=explicit';
    s.async = true; s.defer = true;
    document.head.appendChild(s);
} else {
    document.getElementById('captcha-group').style.display = 'none';
}

function onLoginCaptchaLoad() {
    if (!isCaptchaConfigured) return;
    grecaptcha.render('login-captcha-container', {
        sitekey: captchaSiteKey,
        callback: r => document.getElementById('login-captcha-token').value = r
    });
}

// Your existing form submit code...
</script>

<?php require_once '../includes/kinas-ui.php'; ?>
<?php require_once '../includes/password-toggle.php'; ?>
</body>
</html>
