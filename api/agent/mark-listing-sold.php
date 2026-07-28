<?php
/**
 * KINAS GROUP — Agent marks a car or property listing as sold.
 * Records the sale in `transactions` with the correct division-specific
 * commission rate applied automatically (1.7% vehicles, 1% property).
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();
$agentId = SessionManager::getUserId();

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security token expired. Please refresh and try again.']);
    exit;
}

$listingId  = (int)($_POST['listing_id'] ?? 0);
$division   = $_POST['division'] ?? '';
$finalPrice = (float)($_POST['final_price'] ?? 0);

$tableMap = ['car' => 'car_listings', 'property' => 'property_listings'];
if (!$listingId || !isset($tableMap[$division])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing or division']);
    exit;
}
if ($finalPrice <= 0 || $finalPrice > 999999999) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid sale price']);
    exit;
}

$table = $tableMap[$division];
$commissionPct = $division === 'car' ? COMMISSION_RATE_VEHICLE : COMMISSION_RATE_PROPERTY;

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, title, status FROM $table WHERE id = ? AND agent_id = ?");
    $stmt->execute([$listingId, $agentId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found or not owned by you']);
        exit;
    }
    if ($listing['status'] === 'sold') {
        http_response_code(409);
        echo json_encode(['error' => 'This listing is already marked as sold']);
        exit;
    }

    $commission = round($finalPrice * $commissionPct / 100, 2);

    $db->beginTransaction();

    $db->prepare("UPDATE $table SET status = 'sold' WHERE id = ?")->execute([$listingId]);

    // status 'pending' — the sale itself happens off-platform between
    // agent and buyer (no on-platform payment/buyer account involved
    // here), so this records what's OWED to the platform, for admin to
    // process via the Company's normal settlement flow, not an
    // automatically-confirmed payment.
    $db->prepare("
        INSERT INTO transactions
            (agent_id, listing_id, listing_type, amount, commission_pct, commission, currency, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, 'NGN', 'pending', ?)
    ")->execute([
        $agentId, $listingId, $division, $finalPrice, $commissionPct, $commission,
        'Sale recorded by agent via Mark as Sold — awaiting settlement.',
    ]);

    $db->commit();

    Security::logActivity($agentId, 'listing_marked_sold', "{$division} listing #{$listingId} ({$listing['title']}) sold for ₦" . number_format($finalPrice) . ", commission ₦" . number_format($commission));

    echo json_encode(['success' => true, 'commission' => $commission, 'commission_pct' => $commissionPct]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('mark-listing-sold.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
