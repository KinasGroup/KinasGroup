<?php
/**
 * KINAS GROUP — Request a paid inspection: creates the booking record
 * and initializes Paystack payment. The appointment is NOT confirmed
 * here — only once payment is verified (see includes/inspection-fulfillment.php).
 *
 * POST /api/inspection/request.php
 *   { listing_id, listing_type ('car'|'property'), preferred_date, preferred_time }
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/helpers.php';
require_once '../../includes/paystack.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please login to request an inspection']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

Security::rateLimitDB('inspection_request_' . Security::getClientIP(), 10, 600);

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$listingId     = (int)($data['listing_id'] ?? 0);
$listingType   = $data['listing_type'] ?? '';
$preferredDate = $data['preferred_date'] ?? '';
$preferredTime = trim((string)($data['preferred_time'] ?? ''));

$tableMap = ['car' => 'car_listings', 'property' => 'property_listings'];
if (!$listingId || !isset($tableMap[$listingType])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing']);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $preferredDate);
$today = new DateTime('today');
if (!$date || $date < $today) {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a valid, upcoming date']);
    exit;
}
if ($preferredTime === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please choose a preferred time']);
    exit;
}

$table = $tableMap[$listingType];

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, title, agent_id, inspection_fee, status FROM $table WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing || $listing['status'] !== 'active') {
        http_response_code(404);
        echo json_encode(['error' => 'This listing is not available']);
        exit;
    }

    $fee = (float)($listing['inspection_fee'] ?? 0);
    if ($fee <= 0) {
        http_response_code(422);
        echo json_encode(['error' => 'This listing does not require a paid inspection — use the free viewing request instead']);
        exit;
    }
    if ((int)$listing['agent_id'] === $userId) {
        http_response_code(422);
        echo json_encode(['error' => "You can't book an inspection on your own listing"]);
        exit;
    }

    $buyerStmt = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $buyerStmt->execute([$userId]);
    $buyer = $buyerStmt->fetch(PDO::FETCH_ASSOC);

    $commissionPct = INSPECTION_FEE_COMMISSION_RATE;
    $commission = round($fee * $commissionPct / 100, 2);
    $reference = 'INSPECT-' . strtoupper(bin2hex(random_bytes(8)));

    $db->prepare("
        INSERT INTO inspection_bookings
            (listing_id, listing_type, agent_id, buyer_id, buyer_name, buyer_email, buyer_phone,
             preferred_date, preferred_time, fee_amount, commission_pct, commission, reference, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment')
    ")->execute([
        $listingId, $listingType, $listing['agent_id'], $userId, $buyer['name'], $buyer['email'], $buyer['phone'] ?? null,
        $preferredDate, $preferredTime, $fee, $commissionPct, $commission, $reference,
    ]);

    $paystack = new PaystackService();
    $folderMap = ['car' => 'kinas-automobile', 'property' => 'williams-connect-home'];
    $callbackUrl = url('/divisions/' . $folderMap[$listingType] . '/detail.php?id=' . $listingId . '&inspection_ref=' . urlencode($reference));

    $init = $paystack->initializeTransaction(
        $buyer['email'],
        $fee,
        $reference,
        $callbackUrl,
        [
            'listing_id'   => $listingId,
            'listing_type' => $listingType,
            'purpose'      => 'inspection_fee',
        ]
    );

    if (!$init['success']) {
        $db->prepare("UPDATE inspection_bookings SET status = 'cancelled' WHERE reference = ?")->execute([$reference]);
        http_response_code(502);
        echo json_encode(['error' => $init['error'] ?? 'Unable to start payment. Please try again.']);
        exit;
    }

    Security::logActivity($userId, 'inspection_requested', "Inspection requested for {$listingType} #{$listingId}, fee ₦" . number_format($fee));

    echo json_encode([
        'success'     => true,
        'reference'   => $reference,
        'access_code' => $init['access_code'],
        'public_key'  => $paystack->getPublicKey(),
        'fee'         => $fee,
        'fee_label'   => '₦' . number_format($fee),
    ]);

} catch (Exception $e) {
    error_log('inspection/request.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
