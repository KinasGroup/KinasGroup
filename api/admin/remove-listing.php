<?php
/**
 * Admin: soft-delete a listing by setting its status to 'removed'.
 * Accepts both form POST (with csrf_token) and JSON POST.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Method not allowed']);
    } else {
        echo 'Method not allowed';
    }
    exit;
}

SessionManager::requireAdmin();

$contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $data = $_POST;
}

$token = $data['csrf_token'] ?? '';
if ($token !== '' && !Security::verifyCSRFToken($token)) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
    } else {
        $_SESSION['flash_error'] = 'Invalid security token.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/flagged-listings.php'));
    }
    exit;
}

$listingId   = (int)($data['listing_id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
$table = $tableMap[$listingType] ?? null;

$redirectAfter = $_SERVER['HTTP_REFERER'] ?? '/admin/flagged-listings.php';
if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirectAfter)) {
    $redirectAfter = '/admin/flagged-listings.php';
}

if (!$listingId || !$table) {
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['error' => 'Invalid listing reference']);
    } else {
        $_SESSION['flash_error'] = 'Invalid listing reference.';
        header('Location: ' . $redirectAfter);
    }
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE $table SET status = 'removed' WHERE id = ?");
    $stmt->execute([$listingId]);

    Security::logActivity($_SESSION['user_id'], 'listing_removed', "Listing #{$listingId} ({$listingType}) soft-removed");

    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Listing removed']);
    } else {
        $_SESSION['flash_success'] = 'Listing has been removed from public view.';
        header('Location: ' . $redirectAfter);
        exit;
    }
} catch (Exception $e) {
    error_log('admin remove-listing error: ' . $e->getMessage());
    $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Removal failed']);
    } else {
        $_SESSION['flash_error'] = 'Failed to remove listing.';
        header('Location: ' . $redirectAfter);
        exit;
    }
}
