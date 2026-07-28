<?php
/**
 * KINAS GROUP — Admin: adjust a listing's price.
 *
 * Only ever updates the live listing row. Historical order_items and
 * transactions store their own price snapshot at time of purchase (see
 * api/payments/checkout-init.php), so this can never retroactively
 * change what a past buyer was actually charged — exactly the
 * "without affecting existing transaction records" requirement.
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAdmin();

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Security token expired. Please refresh and try again.']);
    exit;
}

$listingId = (int)($_POST['listing_id'] ?? 0);
$division  = $_POST['division'] ?? '';
$newPrice  = (float)($_POST['price'] ?? 0);

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];

if (!$listingId || !isset($tableMap[$division])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing or division']);
    exit;
}
if ($newPrice <= 0 || $newPrice > 999999999) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid price']);
    exit;
}

$table = $tableMap[$division];

try {
    $db = Database::getInstance()->getConnection();

    $current = $db->prepare("SELECT title, price FROM $table WHERE id = ?");
    $current->execute([$listingId]);
    $row = $current->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
        exit;
    }

    $oldPrice = (float)$row['price'];
    if (abs($oldPrice - $newPrice) < 0.005) {
        echo json_encode(['success' => true, 'unchanged' => true]);
        exit;
    }

    $db->prepare("UPDATE $table SET price = ? WHERE id = ?")->execute([$newPrice, $listingId]);

    Security::logActivity(
        SessionManager::getUserId(),
        'admin_price_adjusted',
        "Listing #{$listingId} ({$row['title']}, {$division}): ₦" . number_format($oldPrice) . ' → ₦' . number_format($newPrice)
    );

    echo json_encode(['success' => true, 'old_price' => $oldPrice, 'new_price' => $newPrice]);

} catch (Exception $e) {
    error_log('admin/update-price.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again.']);
}
