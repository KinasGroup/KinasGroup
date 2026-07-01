<?php
/**
 * Agent: Delete Listing
 * Permanently deletes a listing from the database
 */

require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];

// Get parameters
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$division = isset($_GET['division']) ? $_GET['division'] : '';
$csrf_token = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

// Validate CSRF token
if (!Security::verifyCSRFToken($csrf_token)) {
    $_SESSION['flash_error'] = 'Please refresh the page and try again.';
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
    // Verify the listing belongs to this agent
    $check = $db->prepare("SELECT id FROM $table WHERE id = ? AND agent_id = ?");
    $check->execute([$listingId, $agentId]);

    if (!$check->fetch()) {
        $_SESSION['flash_error'] = 'Listing not found or unauthorized.';
        header('Location: listings.php');
        exit;
    }

    // Delete the listing
    $delete = $db->prepare("DELETE FROM $table WHERE id = ? AND agent_id = ?");
    $delete->execute([$listingId, $agentId]);

    // Delete associated images
    try {
        $imageDelete = $db->prepare("DELETE FROM listing_images WHERE listing_id = ? AND listing_type = ?");
        $imageDelete->execute([$listingId, $division]);
    } catch (Exception $e) {
        // Table might not exist, continue
    }

    // 🔧 FIX: Clear featured flag for this listing
    try {
        // Reset featured flag in the listing table
        $resetFeatured = $db->prepare("UPDATE $table SET featured = 0 WHERE id = ?");
        $resetFeatured->execute([$listingId]);
        
        // Clear from featured cache table if it exists
        $clearCache = $db->prepare("DELETE FROM featured_cache WHERE listing_id = ?");
        $clearCache->execute([$listingId]);
    } catch (Exception $e) {
        // Tables might not exist, continue
    }

    Security::logActivity($agentId, 'listing_deleted', "Deleted $division listing #$listingId");

    $_SESSION['flash_success'] = 'Listing deleted successfully.';
    header('Location: listings.php');
    exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Failed to delete listing: ' . $e->getMessage();
    header('Location: listings.php');
    exit;
}
?>
