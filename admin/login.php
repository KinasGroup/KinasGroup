<?php
// Admin-specific login page
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect if already logged in as admin
if (SessionManager::isLoggedIn() && SessionManager::getUserRole() === 'admin') {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';

// Generate CSRF token for form
$csrfToken = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captchaToken = $_POST['captcha_token'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (empty($submittedCsrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedCsrfToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } elseif ($email && $password) {
        // CAPTCHA verification (skip if not configured)
        $captchaSecretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?? '';
        $captchaEnabled = !empty($captchaSecretKey) && $captchaSecretKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX';
        
        if ($captchaEnabled && empty($captchaToken)) {
            $error = 'Please complete the CAPTCHA verification.';
        } else {
            if ($captchaEnabled && !empty($captchaToken)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $verifyResponse = @file_get_contents(
                    'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
                        'secret' => $captchaSecretKey,
                        'response' => $captchaToken,
                        'remoteip' => $ip
                    ])
                );
                if ($verifyResponse !== false) {
                    $verifyData = json_decode($verifyResponse, true);
                    if (!$verifyData || !$verifyData['success']) {
                        $error = 'CAPTCHA verification failed. Please try again.';
                    }
                }
            }
            
            if (empty($error)) {
                // Make API call to login endpoint
                $ch = curl_init('/api/auth/login.php');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'email' => $email,
                    'password' => $password,
                    'csrf_token' => $submittedCsrfToken
                ]));
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $data = json_decode($response, true);
                
                if ($httpCode === 200 && ($data['success'] ?? false)) {
                    $_SESSION['user_id'] = $data['user']['id'];
                    $_SESSION['user_name'] = $data['user']['name'];
                    $_SESSION['user_role'] = $data['user']['role'];
                    $_SESSION['user_email'] = $data['user']['email'];
                    
                    if ($data['user']['role'] === 'admin') {
                        header('Location: /admin/dashboard.php');
                        exit;
                    } else {
                        $error = 'This account does not have admin privileges. Use the regular login for agent/buyer access.';
                    }
                } else {
                    $error = $data['error'] ?? 'Invalid credentials. Admin access only.';
                }
            }
        }
    } else {
        $error = 'Please enter email and password';
    }
    
    // Rotate CSRF token on error
    if (!empty($error)) {
        unset($_SESSION['csrf_token']);
        $csrfToken = Security::generateCSRFToken();
    }
}

$pageTitle = 'Admin Login - KINAS GROUP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - KINAS GROUP</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .admin-login-container {
            max-width: 480px;
            width: 100%;
            background: #FFFFFF;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Gold Rounded Rectangle - ADMIN PORTAL */
        .admin-badge {
            width: 100%;
            max-width: 280px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #C6A43F 0%, #A8882E 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-badge span {
            font-family: 'Prata', serif;
            font-size: 24px;
            font-weight: 600;
            color: #0A0A0A;
            letter-spacing: 1px;
        }

        /* Title Section */
        .auth-title {
            font-family: 'Prata', serif;
            font-size: 32px;
            text-align: center;
            margin-bottom: 8px;
            color: #0A0A0A;
        }
        
        .auth-subtitle {
            text-align: center;
            color: #666666;
            margin-bottom: 35px;
            font-size: 14px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 13px;
            color: #333;
            letter-spacing: 0.3px;
        }
        .form-group label i {
            margin-right: 6px;
            color: #C6A43F;
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: #F8F8F8;
            border: 1px solid #E0E0E0;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #C6A43F;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(198, 164, 63, 0.1);
        }

        /* CAPTCHA Wrapper */
        .captcha-wrapper {
            transform: scale(0.95);
            transform-origin: left top;
            margin: 5px 0;
        }

        /* Button */
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: #C6A43F;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 15px;
            color: #0A0A0A;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: #A8882E;
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        /* Divider */
        .auth-divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0 25px;
        }
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E0E0E0;
        }
        .auth-divider span {
            color: #999999;
            font-size: 12px;
        }

        /* Back Link */
        .register-link {
            text-align: center;
            font-size: 14px;
            color: #666666;
        }
        .register-link a {
            color: #C6A43F;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 520px) {
            .admin-login-container {
                padding: 35px 25px;
            }
            .admin-badge {
                max-width: 240px;
                height: 70px;
            }
            .admin-badge span {
                font-size: 20px;
            }
            .auth-title {
                font-size: 28px;
            }
            .captcha-wrapper {
                transform: scale(0.85);
                transform-origin: left center;
            }
        }
    </style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main">
</head>
<body>
    <div class="admin-login-container">
        <!-- GOLD ROUNDED RECTANGLE - ADMIN PORTAL -->
        <div class="admin-badge">
            <span>ADMIN PORTAL</span>
        </div>

        <!-- Title Section -->
        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Secure access for platform administrators only</p>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="adminLoginForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Admin Email</label>
                <input type="email" name="email" placeholder="admin@kinasgroup.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <!-- CAPTCHA Section - Same as auth/login.php -->
            <div class="form-group">
                <div id="login-captcha-container" class="captcha-wrapper"></div>
                <input type="hidden" id="login-captcha-token" name="captcha_token">
            </div>

            <button type="submit" id="submitBtn" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Access Admin Dashboard
            </button>
        </form>

        <!-- Divider -->
        <div class="auth-divider">
            <span>or</span>
        </div>

        <!-- Back Link -->
        <div class="register-link">
            <a href="/auth/login.php">
                <i class="fas fa-arrow-left"></i> Back to Agent/Buyer Login
            </a>
        </div>
    </div>

    <!-- CAPTCHA Scripts - Same as auth/login.php -->
    <script>
    // Only activate reCAPTCHA when a real site key is present
    const loginCaptchaSiteKey = '<?php echo htmlspecialchars($_ENV['CAPTCHA_SITE_KEY'] ?? getenv('CAPTCHA_SITE_KEY') ?? ''); ?>';
    const isLoginCaptchaConfigured = loginCaptchaSiteKey &&
                                      loginCaptchaSiteKey !== '6LeXXXXXXXXXXXXXXXXXXXXXXXX' &&
                                      loginCaptchaSiteKey.length > 30;

    if (isLoginCaptchaConfigured) {
        // Load reCAPTCHA script only when key is valid
        var captchaScript = document.createElement('script');
        captchaScript.src = 'https://www.google.com/recaptcha/api.js?onload=onLoginCaptchaLoad&render=explicit';
        captchaScript.async = true;
        captchaScript.defer = true;
        document.head.appendChild(captchaScript);
    } else {
        // Hide the captcha widget area — not needed
        const container = document.getElementById('login-captcha-container');
        if (container) {
            const wrapper = container.closest('.form-group');
            if (wrapper) wrapper.style.display = 'none';
        }
    }

    function onLoginCaptchaLoad() {
        if (!isLoginCaptchaConfigured) return;
        const container = document.getElementById('login-captcha-container');
        if (container) {
            grecaptcha.render('login-captcha-container', {
                'sitekey': loginCaptchaSiteKey,
                'callback': function(response) {
                    document.getElementById('login-captcha-token').value = response;
                },
                'expired-callback': function() {
                    document.getElementById('login-captcha-token').value = '';
                }
            });
        }
    }

    // Form validation and submission
    document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = document.getElementById('submitBtn');
        const email = form.email.value.trim();
        const password = form.password.value;

        // Basic validation
        if (!email || !password) {
            alert('Please enter both email and password');
            return;
        }

        // Get CAPTCHA token
        const captchaToken = document.getElementById('login-captcha-token')?.value || '';

        // Block submission if captcha is configured but the widget hasn't been solved yet
        if (isLoginCaptchaConfigured && !captchaToken) {
            alert('Please complete the CAPTCHA verification before signing in.');
            return;
        }

        // Disable button during submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';

        // Submit the form normally (not via AJAX for simplicity)
        // This will trigger the PHP POST handling above
        const hiddenCaptchaInput = document.createElement('input');
        hiddenCaptchaInput.type = 'hidden';
        hiddenCaptchaInput.name = 'captcha_token';
        hiddenCaptchaInput.value = captchaToken;
        form.appendChild(hiddenCaptchaInput);
        
        form.submit();
    });
    </script>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
