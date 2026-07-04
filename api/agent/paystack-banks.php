<?php
/**
 * Agent: list Nigerian banks (for the Paystack payout bank dropdown).
 * GET /api/agent/paystack-banks.php
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/paystack.php';

SessionManager::requireAgent();

$paystack = new PaystackService();
if (!$paystack->isEnabled()) {
    echo json_encode(['success' => false, 'banks' => [], 'error' => 'Paystack is not configured']);
    exit;
}

$result = $paystack->listBanks();
echo json_encode($result);
