<?php
/**
 * Admin: flag or approve a listing.
 * Accepts POST form (csrf_token, listing_id, listing_type, action) for direct links.
 * Also accepts JSON POST.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Method not allowed']);
    } else {
        echo 'Method not allowed';
    }
    exit;
}

SessionManager::requireAdmin();

// Detect form vs JSON
$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

// CSRF for form posts
$token = $data['csrf_token'] ?? '';
if ($token !== '' && !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
    } else {
        $_SESSION['flash_error'] = 'Please refresh the page and try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/listing-management.php'));
    }
    exit;
}

$listingId   = (int)($data['listing_id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';
$action      = $data['action']      ?? 'approve';

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
$table = $tableMap[$listingType] ?? null;

if (!$listingId || !$table) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => 'Invalid listing reference']);
    } else {
        $_SESSION['flash_error'] = 'Invalid listing reference.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/listing-management.php'));
    }
    exit;
}

$newStatus = $action === 'approve' ? 'active' : 'flagged';

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/admin/listing-management.php';
// Whitelist redirect
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/admin/listing-management.php';
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE $table SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $listingId]);

    Security::logActivity($_SESSION['user_id'], 'listing_' . $action, "Listing #{$listingId} ({$listingType}) set to {$newStatus}");

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => "Listing {$action}d"]);
    } else {
        $_SESSION['flash_success'] = 'Listing ' . ($action === 'approve' ? 'approved and set to active.' : 'flagged for review.');
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('admin review-listing error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Review failed']);
    } else {
        $_SESSION['flash_error'] = 'Failed to update listing status.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
