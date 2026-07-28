<?php
/**
 * KINAS GROUP — Called by the frontend immediately after the Paystack
 * Popup's onSuccess callback fires for an inspection fee payment.
 *
 * This is a CONVENIENCE fast-path only — it calls the exact same
 * finalizeInspectionBooking() the webhook uses, which re-verifies
 * against Paystack before confirming anything. The webhook
 * (api/webhooks/paystack.php) is the authoritative path.
 *
 * POST /api/inspection/verify.php   { reference }
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/inspection-fulfillment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to verify your payment']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$reference = trim((string)($data['reference'] ?? ''));

if ($reference === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Missing payment reference']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $ownStmt = $db->prepare("SELECT id FROM inspection_bookings WHERE reference = ? AND buyer_id = ?");
    $ownStmt->execute([$reference, $userId]);
    if (!$ownStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        exit;
    }

    $result = finalizeInspectionBooking($db, $reference);

    if (!$result['success']) {
        http_response_code(402);
        echo json_encode(['error' => $result['error'] ?? 'Payment could not be verified']);
        exit;
    }

    echo json_encode(['success' => true, 'reference' => $reference]);

} catch (Exception $e) {
    error_log('inspection/verify.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong verifying your payment.']);
}
