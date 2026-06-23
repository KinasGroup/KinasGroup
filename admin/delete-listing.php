<?php
/**
 * Admin: Delete Listing
 * Permanently deletes a listing from the database
 */

require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Get parameters
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$division = isset($_GET['division']) ? $_GET['division'] : '';
$csrf_token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

// Validate CSRF token
if (!Security::verifyCSRFToken($csrf_token)) {
    $_SESSION['flash_error'] = 'Invalid security token.';
    header('Location: listings.php');
    exit;
}

if (!$listingId || !$division) {
    $_SESSION['flash_error'] = 'Invalid listing ID or division.';
    header('Location: listings.php');
    exit;
}

// Map division to table
$tableMap = [
    'solar' => 'solar_listings',
    'car' => 'car_listings',
    'property' => 'property_listings',
    'marketplace' => 'marketplace_listings'
];

if (!isset($tableMap[$division])) {
    $_SESSION['flash_error'] = 'Invalid division.';
    header('Location: listings.php');
    exit;
}

$table = $tableMap[$division];

try {
    // Delete the listing
    $delete = $db->prepare("DELETE FROM $table WHERE id = ?");
    $delete->execute([$listingId]);

    // Delete associated images
    try {
        $imageDelete = $db->prepare("DELETE FROM listing_images WHERE listing_id = ? AND listing_type = ?");
        $imageDelete->execute([$listingId, $division]);
    } catch (Exception $e) {
        // Table might not exist, continue
    }

    Security::logActivity($_SESSION['user_id'], 'listing_deleted', "Deleted $division listing #$listingId");

    $_SESSION['flash_success'] = 'Listing deleted successfully.';
    header('Location: listings.php');
    exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Failed to delete listing: ' . $e->getMessage();
    header('Location: listings.php');
    exit;
}
?>
