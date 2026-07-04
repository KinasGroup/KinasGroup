<?php
/**
 * Agent: verify a bank account number (resolves the account holder's
 * name via Paystack) before it's saved as a payout destination.
 * POST /api/agent/paystack-verify-account.php   { bank_code, account_number, csrf_token }
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/paystack.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();

$token = $_POST['csrf_token'] ?? '';
if ($token === '' || !Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Please refresh the page and try again']);
    exit;
}

$bankCode = trim($_POST['bank_code'] ?? '');
$acctNo   = trim($_POST['account_number'] ?? '');

if ($bankCode === '' || $acctNo === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select a bank and enter an account number']);
    exit;
}

$paystack = new PaystackService();
if (!$paystack->isEnabled()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Paystack is not configured']);
    exit;
}

$result = $paystack->resolveAccountNumber($acctNo, $bankCode);
echo json_encode($result);
