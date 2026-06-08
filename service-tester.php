<?php
/**
 * KINAS GROUP - Service Verification Tool
 * Tests all external service integrations (RESEND, TERMII SMS, CLOUDFLARE R2, reCAPTCHA)
 *
 * Usage: Access this file via browser or run via command line
 * Access: https://kinasgroup.com/service-tester.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment
require_once __DIR__ . '/includes/dotenv.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/sms.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Verification - KINAS GROUP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            min-height: 100vh;
            color: #fff;
            padding: 40px 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 {
            font-family: 'Prata', serif;
            text-align: center;
            color: #C6A43F;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        .subtitle {
            text-align: center;
            color: #717171;
            margin-bottom: 40px;
        }
        .service-card {
            background: #1A1A1A;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #2a2a2a;
        }
        .service-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .service-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .service-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .icon-resend { background: linear-gradient(135deg, #6b21a8, #9333ea); }
        .icon-termii { background: linear-gradient(135deg, #0f4c75, #1b6ca8); }
        .icon-r2 { background: linear-gradient(135deg, #f97316, #ea580c); }
        .icon-captcha { background: linear-gradient(135deg, #10b981, #059669); }
        h2 { font-size: 1.25rem; color: #fff; }
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background: #064e3b; color: #10b981; }
        .status-inactive { background: #7f1d1d; color: #ef4444; }
        .status-warning { background: #78350f; color: #f59e0b; }
        .status-checking { background: #1e3a5f; color: #3b82f6; }
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .config-item {
            background: #0A0A0A;
            padding: 12px 15px;
            border-radius: 8px;
        }
        .config-label {
            font-size: 11px;
            color: #717171;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .config-value {
            font-size: 14px;
            color: #fff;
            font-family: monospace;
            word-break: break-all;
        }
        .config-value.masked {
            color: #717171;
        }
        .test-btn {
            background: #C6A43F;
            color: #0A0A0A;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .test-btn:hover { background: #A8882E; transform: translateY(-2px); }
        .test-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .test-btn.running { background: #717171; }
        .result-area {
            margin-top: 20px;
            padding: 15px;
            background: #0A0A0A;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
        .result-area.show { display: block; }
        .result-success { color: #10b981; }
        .result-error { color: #ef4444; }
        .result-info { color: #3b82f6; }
        .refresh-link {
            text-align: center;
            margin-top: 30px;
        }
        .refresh-link a {
            color: #C6A43F;
            text-decoration: none;
            font-size: 14px;
        }
        .refresh-link a:hover { text-decoration: underline; }
        .summary-bar {
            background: #1A1A1A;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-count {
            font-size: 2rem;
            font-weight: 700;
            color: #C6A43F;
        }
        .summary-label {
            font-size: 12px;
            color: #717171;
            text-transform: uppercase;
        }
        .test-form {
            background: #0A0A0A;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .test-form label {
            display: block;
            margin-bottom: 8px;
            color: #717171;
            font-size: 12px;
        }
        .test-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            border-radius: 6px;
            background: #1A1A1A;
            color: #fff;
            font-size: 14px;
        }
        .test-form input:focus {
            outline: none;
            border-color: #C6A43F;
        }
        .instructions {
            background: #1e3a5f;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        .instructions code {
            background: #0A0A0A;
            padding: 2px 6px;
            border-radius: 4px;
            color: #C6A43F;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Service Verification</h1>
        <p class="subtitle">KINAS GROUP - External Service Integration Test</p>

        <?php
        // Determine test mode (CLI or HTTP)
        $isCli = php_sapi_name() === 'cli';

        if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
            handlePostRequest();
        }

        // Display all service tests
        displayServiceTests();
        ?>

        <div class="refresh-link">
            <a href="?refresh=<?php echo time(); ?>">Refresh Status</a>
        </div>
    </div>

<?php

function getEnvValue($key, $mask = false) {
    $value = $_ENV[$key] ?? getenv($key) ?: '';
    if ($mask && strlen($value) > 10) {
        return substr($value, 0, 6) . '...' . substr($value, -4);
    }
    return $value ?: '<span style="color:#ef4444">NOT SET</span>';
}

function isConfigured($value, $placeholder = 'YOUR_') {
    return !empty($value) && strpos($value, $placeholder) !== 0;
}

function displayServiceTests() {
    global $isCli;

    // Collect all configuration data
    $resendKey = getEnvValue('RESEND_API_KEY');
    $resendConfigured = isConfigured($resendKey);

    $termiiKey      = getEnvValue('TERMII_API_KEY');
    $termiiSenderId = getEnvValue('TERMII_SENDER_ID');
    $termiiChannel  = getEnvValue('TERMII_CHANNEL');
    $termiiConfigured = !empty($termiiKey) && strpos($termiiKey, 'YOUR_') !== 0;

    $r2AccountId = getEnvValue('R2_ACCOUNT_ID');
    $r2Bucket = getEnvValue('R2_BUCKET');
    $r2Configured = !empty($r2AccountId) && !empty($r2Bucket);

    $captchaSiteKey = getEnvValue('CAPTCHA_SITE_KEY');
    $captchaSecretKey = getEnvValue('CAPTCHA_SECRET_KEY');
    $captchaConfigured = !empty($captchaSiteKey) && !empty($captchaSecretKey) && strlen($captchaSiteKey) > 30;

    // Count configured services
    $configuredCount = ($resendConfigured ? 1 : 0) + ($termiiConfigured ? 1 : 0) + ($r2Configured ? 1 : 0) + ($captchaConfigured ? 1 : 0);
    ?>

    <div class="summary-bar">
        <div class="summary-item">
            <div class="summary-count"><?php echo $configuredCount; ?>/4</div>
            <div class="summary-label">Services Configured</div>
        </div>
        <div class="summary-item">
            <div class="summary-count"><?php echo $resendConfigured ? '✓' : '✗'; ?></div>
            <div class="summary-label">Resend Email</div>
        </div>
        <div class="summary-item">
            <div class="summary-count"><?php echo $termiiConfigured ? '✓' : '✗'; ?></div>
            <div class="summary-label">Termii SMS</div>
        </div>
        <div class="summary-item">
            <div class="summary-count"><?php echo $r2Configured ? '✓' : '✗'; ?></div>
            <div class="summary-label">Cloudflare R2</div>
        </div>
        <div class="summary-item">
            <div class="summary-count"><?php echo $captchaConfigured ? '✓' : '✗'; ?></div>
            <div class="summary-label">reCAPTCHA</div>
        </div>
    </div>

    <?php if (!$isCli): ?>
    <div class="instructions">
        <strong>Quick Test:</strong> Use the buttons below to verify each service.
        For email/SMS tests, you'll need to provide a recipient address/number.
        Check server error logs for detailed API responses.
    </div>
    <?php endif; ?>

    <!-- RESEND EMAIL SERVICE -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon icon-resend">✉</div>
                <h2>Resend Email Service</h2>
            </div>
            <span class="status-badge <?php echo $resendConfigured ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $resendConfigured ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">API Key</div>
                <div class="config-value masked"><?php echo getEnvValue('RESEND_API_KEY', true); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">From Address</div>
                <div class="config-value">noreply@kinasgroup.com</div>
            </div>
            <div class="config-item">
                <div class="config-label">Status</div>
                <div class="config-value"><?php echo $resendConfigured ? '<span class="result-success">✓ Configured</span>' : '<span class="result-error">✗ Not Configured</span>'; ?></div>
            </div>
        </div>

        <button class="test-btn" onclick="testService('resend')" <?php echo !$resendConfigured ? 'disabled' : ''; ?>>
            Test Resend API
        </button>

        <div id="resend-result" class="result-area"></div>

        <?php if (!$isCli && $resendConfigured): ?>
        <div class="test-form">
            <label>Test Email Address:</label>
            <input type="email" id="resend-email" placeholder="your@email.com" value="">
            <button class="test-btn" style="margin-top:10px;" onclick="sendTestEmail()">Send Test Email</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- TERMII SMS SERVICE -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon icon-termii">📱</div>
                <h2>Termii SMS Service</h2>
            </div>
            <span class="status-badge <?php echo $termiiConfigured ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $termiiConfigured ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">API Key</div>
                <div class="config-value masked"><?php echo getEnvValue('TERMII_API_KEY', true); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Sender ID</div>
                <div class="config-value"><?php echo getEnvValue('TERMII_SENDER_ID'); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Channel</div>
                <div class="config-value"><?php echo getEnvValue('TERMII_CHANNEL'); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Status</div>
                <div class="config-value"><?php echo $termiiConfigured ? '<span class="result-success">✓ Configured</span>' : '<span class="result-error">✗ Not Configured</span>'; ?></div>
            </div>
        </div>

        <button class="test-btn" onclick="testService('termii')" <?php echo !$termiiConfigured ? 'disabled' : ''; ?>>
            Test Termii API
        </button>

        <div id="termii-result" class="result-area"></div>

        <?php if (!$isCli && $termiiConfigured): ?>
        <div class="test-form">
            <label>Test Phone Number (Nigerian format e.g. 2348012345678):</label>
            <input type="tel" id="termii-phone" placeholder="2348012345678" value="">
            <button class="test-btn" style="margin-top:10px;" onclick="sendTestSMS()">Send Test SMS</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- CLOUDFLARE R2 SERVICE -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon icon-r2">☁</div>
                <h2>Cloudflare R2 Storage</h2>
            </div>
            <span class="status-badge <?php echo $r2Configured ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $r2Configured ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">Account ID</div>
                <div class="config-value masked"><?php echo getEnvValue('R2_ACCOUNT_ID', true); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Bucket Name</div>
                <div class="config-value"><?php echo getEnvValue('R2_BUCKET'); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Public URL</div>
                <div class="config-value"><?php echo getEnvValue('R2_PUBLIC_URL'); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Status</div>
                <div class="config-value"><?php echo $r2Configured ? '<span class="result-success">✓ Configured</span>' : '<span class="result-error">✗ Not Configured</span>'; ?></div>
            </div>
        </div>

        <button class="test-btn" onclick="testService('r2')" <?php echo !$r2Configured ? 'disabled' : ''; ?>>
            Test R2 Connection
        </button>

        <div id="r2-result" class="result-area"></div>

        <?php if (!$isCli && $r2Configured): ?>
        <div class="test-form">
            <label>Quick connectivity check - verifies R2 endpoint:</label>
            <button class="test-btn" style="margin-top:10px;" onclick="testR2Connectivity()">Test R2 Endpoint</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- GOOGLE reCAPTCHA SERVICE -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon icon-captcha">🛡</div>
                <h2>Google reCAPTCHA</h2>
            </div>
            <span class="status-badge <?php echo $captchaConfigured ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $captchaConfigured ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">Site Key</div>
                <div class="config-value masked"><?php echo getEnvValue('CAPTCHA_SITE_KEY', true); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Secret Key</div>
                <div class="config-value masked"><?php echo getEnvValue('CAPTCHA_SECRET_KEY', true); ?></div>
            </div>
            <div class="config-item">
                <div class="config-label">Key Length</div>
                <div class="config-value"><?php echo strlen($captchaSiteKey); ?> chars</div>
            </div>
            <div class="config-item">
                <div class="config-label">Status</div>
                <div class="config-value"><?php echo $captchaConfigured ? '<span class="result-success">✓ Configured</span>' : '<span class="result-error">✗ Not Configured</span>'; ?></div>
            </div>
        </div>

        <button class="test-btn" onclick="testService('captcha')" <?php echo !$captchaConfigured ? 'disabled' : ''; ?>>
            Test reCAPTCHA API
        </button>

        <div id="captcha-result" class="result-area"></div>

        <?php if (!$isCli && $captchaConfigured): ?>
        <div class="test-form">
            <label>Test token verification (enter a valid reCAPTCHA token):</label>
            <input type="text" id="captcha-token" placeholder="Test token from frontend" value="">
            <button class="test-btn" style="margin-top:10px;" onclick="verifyCaptchaToken()">Verify Token</button>
        </div>
        <?php endif; ?>
    </div>

    <?php
}

function handlePostRequest() {
    $action = $_POST['action'] ?? '';
    $results = [];

    switch ($action) {
        case 'test_resend':
            $results = testResendAPI();
            break;
        case 'send_email':
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $results = sendTestEmail($email);
            break;
        case 'test_termii':
            $results = testTermiiAPI();
            break;
        case 'send_sms':
            $phone = trim($_POST['phone'] ?? '');
            $results = sendTestSMS($phone);
            break;
        case 'test_r2':
            $results = testR2Connection();
            break;
        case 'test_captcha':
            $results = testCaptchaAPI();
            break;
        case 'verify_captcha':
            $token = trim($_POST['token'] ?? '');
            $results = verifyCaptchaToken($token);
            break;
    }

    if (!empty($results)) {
        echo json_encode($results);
        exit;
    }
}

// ===========================================
// RESEND EMAIL TESTS
// ===========================================

function testResendAPI() {
    $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: '';

    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'RESEND_API_KEY not found in environment'];
    }

    // Test API connectivity by making a request
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'from' => 'Test <test@kinasgroup.com>', // Use test domain
        'to' => 'test@example.com',
        'subject' => 'KINAS GROUP - API Test',
        'html' => '<p>API connectivity test</p>'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = ['http_code' => $httpCode, 'response' => $response, 'curl_error' => $curlError];

    if ($httpCode === 200 || $httpCode === 201) {
        return ['success' => true, 'message' => "Resend API is active (HTTP $httpCode)", 'details' => $result];
    } elseif ($httpCode === 401) {
        return ['success' => false, 'message' => 'Invalid API key', 'details' => $result];
    } elseif ($httpCode === 403) {
        return ['success' => false, 'message' => 'Domain not verified. Add kinasgroup.com to Resend dashboard.', 'details' => $result];
    } else {
        return ['success' => false, 'message' => "API returned HTTP $httpCode", 'details' => $result];
    }
}

function sendTestEmail($email) {
    if (empty($email)) {
        return ['success' => false, 'message' => 'Valid email address required'];
    }

    $emailService = new EmailService();
    $result = $emailService->sendVerificationEmail($email, 'Service Test', bin2hex(random_bytes(8)));

    return [
        'success' => $result,
        'message' => $result ? "Test email sent to $email" : "Failed to send email. Check error logs."
    ];
}

// ===========================================
// TERMII SMS TESTS
// ===========================================

function testTermiiAPI() {
    $apiKey   = $_ENV['TERMII_API_KEY'] ?? getenv('TERMII_API_KEY') ?: '';
    $senderId = $_ENV['TERMII_SENDER_ID'] ?? getenv('TERMII_SENDER_ID') ?: 'KINAS';
    $channel  = $_ENV['TERMII_CHANNEL']  ?? getenv('TERMII_CHANNEL')  ?: 'generic';

    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'TERMII_API_KEY not found'];
    }

    // Probe the Termii balance endpoint — lightweight connectivity check
    $url = 'https://api.ng.termii.com/api/get-balance?api_key=' . urlencode($apiKey);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $data   = json_decode($response, true);
    $result = ['http_code' => $httpCode, 'response' => $data, 'curl_error' => $curlError];

    if ($httpCode === 200 && isset($data['balance'])) {
        return ['success' => true, 'message' => "Termii API is active (HTTP $httpCode). Balance: {$data['balance']} {$data['currency']}", 'details' => $result];
    } elseif ($httpCode === 401 || (isset($data['code']) && $data['code'] !== 'ok')) {
        return ['success' => false, 'message' => 'Invalid Termii API key', 'details' => $result];
    } else {
        return ['success' => false, 'message' => "API returned HTTP $httpCode", 'details' => $result];
    }
}

function sendTestSMS($phone) {
    if (empty($phone)) {
        return ['success' => false, 'message' => 'Phone number required'];
    }

    // Validate phone format (Nigerian or international digits, no + needed for Termii)
    if (!preg_match('/^\+?[1-9]\d{7,14}$/', $phone)) {
        return ['success' => false, 'message' => 'Invalid phone format. Use digits with country code (e.g., 2348012345678)'];
    }

    $smsService = new SMSService();

    // sendOTP is the public entry point; it normalises the phone, stores an OTP and dispatches via Termii
    $result = $smsService->sendOTP($phone);

    return [
        'success' => $result['success'] ?? false,
        'message' => ($result['success'] ?? false)
            ? "Test OTP SMS sent to $phone"
            : ("Failed to send SMS. " . ($result['error'] ?? 'Check error logs.')),
    ];
}

// ===========================================
// CLOUDFLARE R2 TESTS
// ===========================================

function testR2Connection() {
    $accountId = $_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID') ?: '';
    $bucket = $_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET') ?: '';

    if (empty($accountId) || empty($bucket)) {
        return ['success' => false, 'message' => 'R2 credentials not found'];
    }

    // Test endpoint connectivity
    $host = "$accountId.r2.cloudflarestorage.com";
    $url = "https://$host/$bucket";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = ['http_code' => $httpCode, 'host' => $host, 'curl_error' => $curlError];

    // R2 returns 403 for buckets without public access - that's expected
    if ($httpCode === 403 || $httpCode === 404) {
        return ['success' => true, 'message' => "R2 endpoint is reachable (HTTP $httpCode - bucket may require auth)", 'details' => $result];
    } elseif ($httpCode === 200) {
        return ['success' => true, 'message' => "R2 bucket is publicly accessible (HTTP $httpCode)", 'details' => $result];
    } elseif ($httpCode === 301 || $httpCode === 302) {
        return ['success' => true, 'message' => "R2 endpoint responds (HTTP $httpCode - redirect)", 'details' => $result];
    } else {
        return ['success' => false, 'message' => "Unexpected HTTP $httpCode", 'details' => $result];
    }
}

// ===========================================
// GOOGLE reCAPTCHA TESTS
// ===========================================

function testCaptchaAPI() {
    $secretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?: '';

    if (empty($secretKey)) {
        return ['success' => false, 'message' => 'CAPTCHA_SECRET_KEY not found'];
    }

    // Test API endpoint connectivity
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => 'test_response_token',
        'remoteip' => '127.0.0.1'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    // Google returns success=false for invalid tokens, but the API itself worked
    if ($httpCode === 200 && isset($result['success']) === false) {
        return ['success' => true, 'message' => 'reCAPTCHA API is active (API responds correctly)', 'details' => $result];
    } elseif ($httpCode === 200 && $result['success'] === true) {
        return ['success' => true, 'message' => 'reCAPTCHA API is active and token verified', 'details' => $result];
    } else {
        return ['success' => false, 'message' => "reCAPTCHA API error", 'details' => $result];
    }
}

function verifyCaptchaToken($token) {
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token required'];
    }

    $secretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?: '';

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => $token
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    return [
        'success' => $result['success'] ?? false,
        'message' => ($result['success'] ?? false) ? 'Token is valid' : 'Token verification failed',
        'details' => $result
    ];
}

?>

<script>
<?php if (!$isCli): ?>
// AJAX handlers for service tests
async function testService(service) {
    const btn = event.target;
    const resultDiv = document.getElementById(service + '-result');

    btn.disabled = true;
    btn.classList.add('running');
    btn.textContent = 'Testing...';
    resultDiv.classList.add('show');
    resultDiv.innerHTML = '<span class="result-info">Testing ' + service.toUpperCase() + '...</span>';

    try {
        const response = await fetch('service-tester.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=test_' + service
        });

        const data = await response.json();

        let resultHtml = '';
        if (data.success) {
            resultHtml = '<span class="result-success">✓ ' + data.message + '</span>\n';
        } else {
            resultHtml = '<span class="result-error">✗ ' + data.message + '</span>\n';
        }

        if (data.details) {
            resultHtml += '\n' + JSON.stringify(data.details, null, 2);
        }

        resultDiv.innerHTML = resultHtml;
    } catch (error) {
        resultDiv.innerHTML = '<span class="result-error">Request failed: ' + error.message + '</span>';
    }

    btn.disabled = false;
    btn.classList.remove('running');
    btn.textContent = 'Test ' + service.toUpperCase() + ' API';
}

async function sendTestEmail() {
    const email = document.getElementById('resend-email').value;
    if (!email) {
        alert('Please enter an email address');
        return;
    }

    const resultDiv = document.getElementById('resend-result');
    resultDiv.classList.add('show');
    resultDiv.innerHTML = '<span class="result-info">Sending test email...</span>';

    try {
        const response = await fetch('service-tester.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send_email&email=' + encodeURIComponent(email)
        });

        const data = await response.json();
        resultDiv.innerHTML = data.success
            ? '<span class="result-success">✓ ' + data.message + '</span>\n\nCheck inbox and spam folder.'
            : '<span class="result-error">✗ ' + data.message + '</span>';
    } catch (error) {
        resultDiv.innerHTML = '<span class="result-error">Request failed: ' + error.message + '</span>';
    }
}

async function sendTestSMS() {
    const phone = document.getElementById('termii-phone').value;
    if (!phone) {
        alert('Please enter a phone number');
        return;
    }

    const resultDiv = document.getElementById('termii-result');
    resultDiv.classList.add('show');
    resultDiv.innerHTML = '<span class="result-info">Sending test SMS...</span>';

    try {
        const response = await fetch('service-tester.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send_sms&phone=' + encodeURIComponent(phone)
        });

        const data = await response.json();
        resultDiv.innerHTML = data.success
            ? '<span class="result-success">✓ ' + data.message + '</span>'
            : '<span class="result-error">✗ ' + data.message + '</span>\n\nCheck server error logs for Termii response details.';
    } catch (error) {
        resultDiv.innerHTML = '<span class="result-error">Request failed: ' + error.message + '</span>';
    }
}

async function testR2Connectivity() {
    testService('r2');
}

async function verifyCaptchaToken() {
    const token = document.getElementById('captcha-token').value;
    const resultDiv = document.getElementById('captcha-result');

    resultDiv.classList.add('show');
    resultDiv.innerHTML = '<span class="result-info">Verifying token...</span>';

    try {
        const response = await fetch('service-tester.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=verify_captcha&token=' + encodeURIComponent(token)
        });

        const data = await response.json();
        resultDiv.innerHTML = data.success
            ? '<span class="result-success">✓ ' + data.message + '</span>\n\n' + JSON.stringify(data.details, null, 2)
            : '<span class="result-error">✗ ' + data.message + '</span>\n\n' + JSON.stringify(data.details, null, 2);
    } catch (error) {
        resultDiv.innerHTML = '<span class="result-error">Request failed: ' + error.message + '</span>';
    }
}
<?php else: ?>
// CLI mode - run tests directly
console.log("KINAS GROUP - Service Verification (CLI Mode)\n");
console.log("Run via web browser for interactive testing.\n");
<?php endif; ?>
</script>

</body>
</html>
