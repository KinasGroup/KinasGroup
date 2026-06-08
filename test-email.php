<?php
/**
 * Email Service Test Script
 * Run this to verify Resend email sending works
 * Access: https://kinasgroup.com/test-email.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment
require_once __DIR__ . '/includes/dotenv.php';
require_once __DIR__ . '/includes/email.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Service Test - KINAS GROUP</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #0A0A0A; color: #fff; }
        .card { background: #1A1A1A; border-radius: 12px; padding: 30px; margin: 20px 0; }
        h1 { color: #C6A43F; }
        .status { padding: 15px; border-radius: 8px; margin: 10px 0; }
        .success { background: #064e3b; border: 1px solid #059669; }
        .error { background: #7f1d1d; border: 1px solid #dc2626; }
        .info { background: #1e3a5f; border: 1px solid #3b82f6; }
        .btn { background: #C6A43F; color: #0A0A0A; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #A8882E; }
        pre { background: #0A0A0A; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
        label { display: block; margin: 10px 0 5px; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0A0A0A; color: #fff; }
    </style>
</head>
<body>
    <h1>Email Service Test</h1>

    <div class="card">
        <h2>Environment Check</h2>
        <?php
        // Check Resend API Key
        $resendKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '');

        // Direct .env check
        if (empty($resendKey)) {
            $envFile = __DIR__ . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        if (trim($key) === 'RESEND_API_KEY') {
                            $resendKey = trim($value);
                            break;
                        }
                    }
                }
            }
        }

        if (!empty($resendKey) && strpos($resendKey, 're_') === 0): ?>
            <div class="status success">
                <strong>RESEND_API_KEY:</strong> Found (<?= substr($resendKey, 0, 15) ?>...)
            </div>
        <?php else: ?>
            <div class="status error">
                <strong>RESEND_API_KEY:</strong> Not found or invalid
            </div>
        <?php endif; ?>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])): ?>
    <div class="card">
        <h2>Sending Test Email</h2>
        <?php
        $testEmail = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$testEmail) {
            echo '<div class="status error">Please provide a valid email address</div>';
        } else {
            echo '<div class="status info">Sending to: ' . htmlspecialchars($testEmail) . '...</div>';

            $emailService = new EmailService();

            // Test with verification email
            $result = $emailService->sendVerificationEmail(
                $testEmail,
                'Test User',
                bin2hex(random_bytes(16))
            );

            if ($result) {
                echo '<div class="status success">SUCCESS! Verification email sent to ' . htmlspecialchars($testEmail) . '</div>';
                echo '<p>Check your inbox (and spam folder) for the verification email from KINAS GROUP.</p>';
            } else {
                echo '<div class="status error">FAILED! Email could not be sent.</div>';
                echo '<p>Check server error logs for details.</p>';
            }
        }
        ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Send Test Email</h2>
        <form method="POST">
            <label for="email">Recipient Email:</label>
            <input type="email" id="email" name="email" placeholder="your@email.com" required>
            <br><br>
            <button type="submit" name="test_email" class="btn">Send Test Email</button>
        </form>
    </div>

    <div class="card">
        <h2>Troubleshooting</h2>
        <pre>
Expected logs when email sends successfully:
[Resend API call - To: test@example.com, HTTP Code: 200, Response: {..."id": "..."...}]

If you see HTTP 401: API key is invalid
If you see HTTP 403: Domain not verified in Resend
If you see HTTP 429: Rate limit exceeded
        </pre>
    </div>
</body>
</html>