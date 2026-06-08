<?php
/**
 * KINAS GROUP - Service Test Script
 *
 * HOW TO USE:
 * 1. Download this file
 * 2. Upload to your Railway app root folder
 * 3. Access via: https://your-app.railway.app/test-services.php
 * 4. Or call via: https://your-app.railway.app/test-services.php?service=resend
 *
 * NO INSTALLATION NEEDED - Just upload and run!
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get service from URL or POST
$service = $_GET['service'] ?? ($_POST['service'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body for POST
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
}

// Helper function
function jsonResponse($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Route
switch ($service) {
    case 'resend':
        testResend($input);
        break;
    case 'termii':
        testTermii($input);
        break;
    case 'recaptcha':
        testRecaptcha($input);
        break;
    case 'r2':
        testR2($input);
        break;
    case 'all':
        testAll();
        break;
    default:
        showHelp();
}

/**
 * Show help and all services status
 */
function showHelp() {
    echo json_encode([
        'status' => 'ok',
        'message' => 'KINAS GROUP Service Test Script',
        'usage' => [
            'GET /test-services.php?service=resend' => 'Test Resend email API',
            'GET /test-services.php?service=termii' => 'Test Termii SMS API',
            'GET /test-services.php?service=recaptcha' => 'Test reCAPTCHA',
            'GET /test-services.php?service=r2' => 'Test Cloudflare R2',
            'GET /test-services.php?service=all' => 'Test all services',
        ],
        'or_post' => [
            'POST /test-services.php with JSON body' => [
                'service' => 'resend|termii|recaptcha|r2|all',
                'apiKey' => 'your-api-key',
                'to' => 'test@example.com'
            ]
        ],
        'required_env_vars' => [
            'resend' => ['RESEND_API_KEY'],
            'termii' => ['TERMII_API_KEY', 'TERMII_SENDER_ID', 'TERMII_CHANNEL'],
            'recaptcha' => ['CAPTCHA_SECRET_KEY'],
            'r2' => ['R2_ACCOUNT_ID', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET']
        ]
    ], JSON_PRETTY_PRINT);
}

/**
 * Test ALL services
 */
function testAll() {
    $results = [];

    ob_start();

    // Test Resend
    $results['resend'] = testResendQuiet();

    // Test Termii
    $results['termii'] = testTermiiQuiet();

    // Test reCAPTCHA
    $results['recaptcha'] = testRecaptchaQuiet();

    // Test R2
    $results['r2'] = testR2Quiet();

    ob_end_clean();

    echo json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'services' => $results
    ], JSON_PRETTY_PRINT);
}

// Quiet versions that return arrays
function testResendQuiet() {
    $apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: ($_POST['apiKey'] ?? '');
    $to = $_POST['to'] ?? 'test@example.com';
    $from = $_ENV['RESEND_FROM'] ?? 'noreply@kinasgroup.com';

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'RESEND_API_KEY not set'];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => $from,
            'to' => $to,
            'subject' => 'KINAS GROUP - API Test',
            'html' => '<p>Test email from Railway Service Test</p>'
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'httpCode' => $httpCode,
        'response' => $data,
        'error' => $error ?: null
    ];
}

function testTermiiQuiet() {
    $apiKey    = $_ENV['TERMII_API_KEY']    ?? getenv('TERMII_API_KEY')    ?: '';
    $senderId  = $_ENV['TERMII_SENDER_ID']  ?? getenv('TERMII_SENDER_ID')  ?: 'KINAS';
    $channel   = $_ENV['TERMII_CHANNEL']    ?? getenv('TERMII_CHANNEL')    ?: 'generic';
    $toPhone   = $_POST['to'] ?? '2348012345678';

    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'TERMII_API_KEY not set'];
    }

    $payload = json_encode([
        'to'      => $toPhone,
        'from'    => $senderId,
        'sms'     => 'KINAS GROUP - API Test',
        'type'    => 'plain',
        'channel' => $channel,
        'api_key' => $apiKey,
    ]);

    $ch = curl_init('https://api.ng.termii.com/api/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $code = $data['code'] ?? '';

    return [
        'success'  => $httpCode === 200 && strtolower($code) === 'ok',
        'httpCode' => $httpCode,
        'response' => $data,
        'error'    => $error ?: null,
    ];
}

function testRecaptchaQuiet() {
    $secretKey = $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?: '';

    if (empty($secretKey)) {
        return ['success' => false, 'error' => 'CAPTCHA_SECRET_KEY not set'];
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => 'test_dummy_token',
            'remoteip' => '127.0.0.1'
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    // missing-input-response with dummy token means key is valid
    $isValid = isset($data['success']) && $data['success'] === false
               && in_array('missing-input-response', $data['error-codes'] ?? []);

    return [
        'success' => $isValid || $data['success'] === true,
        'secretValid' => $isValid || $data['success'] === true,
        'httpCode' => $httpCode,
        'response' => $data,
        'error' => $error ?: null
    ];
}

function testR2Quiet() {
    $accountId = $_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID') ?: '';
    $bucket = $_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET') ?: '';

    if (empty($accountId) || empty($bucket)) {
        return ['success' => false, 'error' => 'R2 credentials not set'];
    }

    $host = $accountId . '.r2.cloudflarestorage.com';
    $url = 'https://' . $host . '/' . $bucket;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => in_array($httpCode, [200, 403, 404]),
        'httpCode' => $httpCode,
        'host' => $host,
        'bucket' => $bucket,
        'error' => $error ?: null
    ];
}

// Individual test functions with output
function testResend($input) {
    $apiKey = $input['apiKey'] ?? $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: '';
    $to = $input['to'] ?? 'test@example.com';
    $from = $input['from'] ?? 'noreply@kinasgroup.com';

    if (empty($apiKey)) {
        jsonResponse(['error' => 'RESEND_API_KEY is required']);
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => $from,
            'to' => $to,
            'subject' => 'KINAS GROUP - API Test',
            'html' => '<p>Test email from Railway Service Test</p>'
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    jsonResponse([
        'service' => 'resend',
        'success' => $httpCode >= 200 && $httpCode < 300,
        'httpCode' => $httpCode,
        'sentTo' => $to,
        'response' => $data,
        'error' => $error ?: null
    ]);
}

function testTermii($input) {
    $apiKey   = $input['apiKey']   ?? $_ENV['TERMII_API_KEY']   ?? getenv('TERMII_API_KEY')   ?: '';
    $senderId = $input['senderId'] ?? $_ENV['TERMII_SENDER_ID'] ?? getenv('TERMII_SENDER_ID') ?: 'KINAS';
    $channel  = $input['channel']  ?? $_ENV['TERMII_CHANNEL']   ?? getenv('TERMII_CHANNEL')   ?: 'generic';
    $toPhone  = $input['to'] ?? '2348012345678';

    if (empty($apiKey)) {
        jsonResponse([
            'error' => 'Termii credentials required',
            'help'  => [
                'apiKey'   => 'TERMII_API_KEY',
                'senderId' => 'TERMII_SENDER_ID',
                'channel'  => 'TERMII_CHANNEL (dnd|generic|whatsapp)',
            ],
        ]);
    }

    $payload = json_encode([
        'to'      => $toPhone,
        'from'    => $senderId,
        'sms'     => 'KINAS GROUP - API Test',
        'type'    => 'plain',
        'channel' => $channel,
        'api_key' => $apiKey,
    ]);

    $ch = curl_init('https://api.ng.termii.com/api/sms/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $code = $data['code'] ?? '';

    jsonResponse([
        'service'  => 'termii',
        'success'  => $httpCode === 200 && strtolower($code) === 'ok',
        'httpCode' => $httpCode,
        'sentTo'   => $toPhone,
        'response' => $data,
        'error'    => $error ?: null,
    ]);
}

function testRecaptcha($input) {
    $secretKey = $input['secretKey'] ?? $_ENV['CAPTCHA_SECRET_KEY'] ?? getenv('CAPTCHA_SECRET_KEY') ?: '';

    if (empty($secretKey)) {
        jsonResponse(['error' => 'CAPTCHA_SECRET_KEY is required']);
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => 'test_dummy_token',
            'remoteip' => '127.0.0.1'
        ]),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    // missing-input-response with dummy token means key is valid
    $isValid = isset($data['success']) && $data['success'] === false
               && in_array('missing-input-response', $data['error-codes'] ?? []);

    jsonResponse([
        'service' => 'recaptcha',
        'secretKeyValid' => $isValid || $data['success'] === true,
        'validationNote' => $isValid ? 'Secret key is VALID (missing-input-response expected for dummy token)' : null,
        'httpCode' => $httpCode,
        'response' => $data,
        'error' => $error ?: null
    ]);
}

function testR2($input) {
    $accountId = $input['accountId'] ?? $_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID') ?: '';
    $bucket = $input['bucket'] ?? $_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET') ?: '';

    if (empty($accountId) || empty($bucket)) {
        jsonResponse(['error' => 'R2 credentials required']);
    }

    $host = $accountId . '.r2.cloudflarestorage.com';
    $url = 'https://' . $host . '/' . $bucket;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    jsonResponse([
        'service' => 'r2',
        'success' => in_array($httpCode, [200, 403, 404]),
        'httpCode' => $httpCode,
        'host' => $host,
        'bucket' => $bucket,
        'error' => $error ?: null
    ]);
}
