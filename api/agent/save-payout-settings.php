<?php
/**
 * Agent: save payout settings (bank details, min threshold, payment method).
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); echo json_encode(['error' => 'Method not allowed']); }
    else echo 'Method not allowed';
    exit;
}

SessionManager::requireAgent();

$token = $_POST['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) { header('Content-Type: application/json'); http_response_code(403); echo json_encode(['error' => 'Invalid CSRF token']); }
    else { $_SESSION['flash_error'] = 'Please refresh the page and try again.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/agent/earnings.php')); }
    exit;
}

$userId = (int)$_SESSION['user_id'];

$allowedMethods = ['bank_transfer_ngn','paypal','stripe','flutterwave','paystack'];
$method = in_array($_POST['payment_method'] ?? '', $allowedMethods, true) ? $_POST['payment_method'] : 'bank_transfer_ngn';
$bankName    = trim($_POST['bank_name'] ?? '');
$bankAcctNm  = trim($_POST['bank_account_name'] ?? '');
$bankAcctNo  = trim($_POST['bank_account_number'] ?? '');
$paypalEmail = trim($_POST['paypal_email'] ?? '');
$stripeAcct  = trim($_POST['stripe_account_id'] ?? '');
$minPayout   = (float)($_POST['min_payout'] ?? 50000);
$autoPayout  = !empty($_POST['auto_payout']) ? 1 : 0;

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/agent/earnings.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/earnings.php';
}

try {
    $db = Database::getInstance()->getConnection();

    // Upsert
    $db->prepare("
        INSERT INTO payout_settings
            (agent_id, payment_method, bank_name, bank_account_name, bank_account_number,
             paypal_email, stripe_account_id, min_payout, auto_payout)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            payment_method = VALUES(payment_method),
            bank_name = VALUES(bank_name),
            bank_account_name = VALUES(bank_account_name),
            bank_account_number = VALUES(bank_account_number),
            paypal_email = VALUES(paypal_email),
            stripe_account_id = VALUES(stripe_account_id),
            min_payout = VALUES(min_payout),
            auto_payout = VALUES(auto_payout)
    ")->execute([
        $userId, $method, $bankName, $bankAcctNm, $bankAcctNo,
        $paypalEmail, $stripeAcct, $minPayout, $autoPayout,
    ]);

    Security::logActivity($userId, 'payout_settings_updated', 'Agent updated payout settings');

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Payout settings saved']);
    } else {
        $_SESSION['flash_success'] = 'Payout settings saved.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('save-payout-settings error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save payout settings']);
    } else {
        $_SESSION['flash_error'] = 'Failed to save payout settings.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
