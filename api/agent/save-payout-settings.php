<?php
/**
 * Agent: save payout settings (bank details, min threshold, payment method).
 *
 * When payment_method is 'paystack', this also creates/updates the
 * agent's Paystack SUBACCOUNT — the mechanism that lets a future sale
 * auto-settle straight to the agent's bank account (minus commission)
 * instead of sitting in the platform's balance for manual payout.
 * The account number is ALWAYS re-verified against Paystack's own
 * "resolve account" endpoint server-side before we save or use it —
 * Paystack does not accept liability for payouts to a wrong account,
 * so we don't either take that risk on faith from the form.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/paystack.php';
require_once '../../api/config/constants.php';

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
$paystackBankCode = trim($_POST['paystack_bank_code'] ?? '');

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/agent/earnings.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/agent/earnings.php';
}

$isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
$subaccountNotice = null; // best-effort message about the Paystack side, doesn't block the save

try {
    $db = Database::getInstance()->getConnection();

    // ── If switching to (or updating) Paystack, verify the account and
    //    create/update the subaccount BEFORE we save anything, so we
    //    never store an account number we haven't confirmed. ──
    $paystackFields = [
        'paystack_bank_code'             => null,
        'paystack_subaccount_code'       => null,
        'paystack_subaccount_id'         => null,
        'paystack_account_verified'      => 0,
        'paystack_verified_account_name' => null,
        'paystack_subaccount_synced_at'  => null,
    ];

    // Preserve any existing subaccount if the agent isn't touching Paystack right now.
    $existingStmt = $db->prepare("SELECT * FROM payout_settings WHERE agent_id = ?");
    $existingStmt->execute([$userId]);
    $existing = $existingStmt->fetch();

    if ($method === 'paystack') {
        if ($paystackBankCode === '' || $bankAcctNo === '') {
            throw new InvalidArgumentException('Select your bank and enter your account number to use Paystack payouts.');
        }

        $paystack = new PaystackService();
        if (!$paystack->isEnabled()) {
            $subaccountNotice = 'Card payments are temporarily unavailable, so your Paystack payout account could not be connected. Your other settings were saved.';
        } else {
            $verify = $paystack->resolveAccountNumber($bankAcctNo, $paystackBankCode);
            if (!$verify['success'] || empty($verify['account_name'])) {
                throw new InvalidArgumentException($verify['error'] ?? 'Could not verify that account number. Please double-check it and try again.');
            }

            $userStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            $companyStmt = $db->prepare("SELECT company_name FROM agent_profiles WHERE user_id = ?");
            $companyStmt->execute([$userId]);
            $businessName = $companyStmt->fetchColumn() ?: ($user['name'] ?? 'KINAS Agent');

            $existingCode = $existing['paystack_subaccount_code'] ?? null;

            if ($existingCode) {
                $update = $paystack->updateSubaccount($existingCode, $businessName, $paystackBankCode, $bankAcctNo, (float)COMMISSION_RATE);
                if (!$update['success']) {
                    throw new InvalidArgumentException($update['error'] ?? 'Could not update your Paystack payout account.');
                }
                $paystackFields = [
                    'paystack_bank_code'             => $paystackBankCode,
                    'paystack_subaccount_code'       => $existingCode,
                    'paystack_subaccount_id'         => $existing['paystack_subaccount_id'],
                    'paystack_account_verified'      => 1,
                    'paystack_verified_account_name' => $verify['account_name'],
                    'paystack_subaccount_synced_at'  => date('Y-m-d H:i:s'),
                ];
            } else {
                $create = $paystack->createSubaccount($businessName, $paystackBankCode, $bankAcctNo, (float)COMMISSION_RATE, $user['email'] ?? '', $user['phone'] ?? null);
                if (!$create['success']) {
                    throw new InvalidArgumentException($create['error'] ?? 'Could not connect your Paystack payout account.');
                }
                $paystackFields = [
                    'paystack_bank_code'             => $paystackBankCode,
                    'paystack_subaccount_code'       => $create['subaccount_code'],
                    'paystack_subaccount_id'         => $create['subaccount_id'],
                    'paystack_account_verified'      => 1,
                    'paystack_verified_account_name' => $verify['account_name'],
                    'paystack_subaccount_synced_at'  => date('Y-m-d H:i:s'),
                ];
            }

            // Reflect the verified name back into the plain bank_account_name
            // field too, so it's consistent everywhere it's displayed.
            if (empty($bankAcctNm)) $bankAcctNm = $paystackFields['paystack_verified_account_name'];
        }
    } elseif ($existing) {
        // Keep whatever subaccount already exists even if the agent is
        // currently viewing/saving a different payment method — it
        // shouldn't be wiped just because "Paystack" isn't selected today.
        $paystackFields = [
            'paystack_bank_code'             => $existing['paystack_bank_code'],
            'paystack_subaccount_code'       => $existing['paystack_subaccount_code'],
            'paystack_subaccount_id'         => $existing['paystack_subaccount_id'],
            'paystack_account_verified'      => $existing['paystack_account_verified'],
            'paystack_verified_account_name' => $existing['paystack_verified_account_name'],
            'paystack_subaccount_synced_at'  => $existing['paystack_subaccount_synced_at'],
        ];
    }

    // Upsert
    $db->prepare("
        INSERT INTO payout_settings
            (agent_id, payment_method, bank_name, bank_account_name, bank_account_number,
             paypal_email, stripe_account_id, min_payout, auto_payout,
             paystack_bank_code, paystack_subaccount_code, paystack_subaccount_id,
             paystack_account_verified, paystack_verified_account_name, paystack_subaccount_synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            payment_method = VALUES(payment_method),
            bank_name = VALUES(bank_name),
            bank_account_name = VALUES(bank_account_name),
            bank_account_number = VALUES(bank_account_number),
            paypal_email = VALUES(paypal_email),
            stripe_account_id = VALUES(stripe_account_id),
            min_payout = VALUES(min_payout),
            auto_payout = VALUES(auto_payout),
            paystack_bank_code = VALUES(paystack_bank_code),
            paystack_subaccount_code = VALUES(paystack_subaccount_code),
            paystack_subaccount_id = VALUES(paystack_subaccount_id),
            paystack_account_verified = VALUES(paystack_account_verified),
            paystack_verified_account_name = VALUES(paystack_verified_account_name),
            paystack_subaccount_synced_at = VALUES(paystack_subaccount_synced_at)
    ")->execute([
        $userId, $method, $bankName, $bankAcctNm, $bankAcctNo,
        $paypalEmail, $stripeAcct, $minPayout, $autoPayout,
        $paystackFields['paystack_bank_code'], $paystackFields['paystack_subaccount_code'], $paystackFields['paystack_subaccount_id'],
        $paystackFields['paystack_account_verified'], $paystackFields['paystack_verified_account_name'], $paystackFields['paystack_subaccount_synced_at'],
    ]);

    Security::logActivity($userId, 'payout_settings_updated', 'Agent updated payout settings');

    $message = 'Payout settings saved' . ($paystackFields['paystack_account_verified'] ? ' — Paystack payout account connected.' : '.');
    if ($subaccountNotice) $message = 'Payout settings saved. ' . $subaccountNotice;

    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $message, 'paystack_verified_name' => $paystackFields['paystack_verified_account_name']]);
    } else {
        $_SESSION['flash_success'] = $message;
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (InvalidArgumentException $e) {
    // Expected validation-style failure (bad account, Paystack rejected it) — show it plainly.
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('save-payout-settings error: ' . $e->getMessage());
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
