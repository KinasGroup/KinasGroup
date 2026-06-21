<?php
/**
 * Permanently Delete a Listing
 * This completely removes the listing from the database
 */

require_once '../includes/session.php';
require_once '../api/config/database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$division = isset($_GET['division']) ? $_GET['division'] : '';

if (!$listingId || !$division) {
    header('Location: listings.php?error=Invalid request');
    exit;
}

// Determine which table to delete from
$tableMap = [
    'solar' => 'solar_listings',
    'car' => 'car_listings',
    'property' => 'property_listings',
    'marketplace' => 'marketplace_listings'
];

if (!isset($tableMap[$division])) {
    header('Location: listings.php?error=Invalid division');
    exit;
}

$table = $tableMap[$division];

// Verify the listing belongs to this agent
$check = $db->prepare("SELECT id FROM $table WHERE id = ? AND agent_id = ?");
$check->execute([$listingId, $agentId]);

if (!$check->fetch()) {
    header('Location: listings.php?error=Listing not found or unauthorized');
    exit;
}

// Permanently delete the listing
$delete = $db->prepare("DELETE FROM $table WHERE id = ? AND agent_id = ?");
$delete->execute([$listingId, $agentId]);

// Also delete associated images (if any)
$imageDelete = $db->prepare("DELETE FROM listing_images WHERE listing_id = ? AND listing_type = ?");
$imageDelete->execute([$listingId, $division]);

header('Location: listings.php?success=Listing permanently deleted');
exit;
