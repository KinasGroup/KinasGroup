<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 12;
$offset = ($page - 1) * $limit;
$listingType = $_GET['type'] ?? 'car';
$status = $_GET['status'] ?? 'active';

// Check if random ordering is requested
$randomize = isset($_GET['random']) ? filter_var($_GET['random'], FILTER_VALIDATE_BOOLEAN) : false;

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    // If 'all' type is requested, get from all tables with random rotation
    if ($listingType === 'all') {
        $allListings = [];
        foreach ($tableMap as $key => $tbl) {
            $orderBy = $randomize ? 'ORDER BY RAND()' : 'ORDER BY l.created_at DESC';
            $stmt = $db->prepare("SELECT l.*, u.name as agent_name, u.verified as agent_verified, ? as listing_type FROM $tbl l JOIN users u ON l.agent_id = u.id WHERE l.status = ? $orderBy LIMIT ? OFFSET ?");
            $stmt->execute([$key, $status, (int)$limit, (int)$offset]);
            $listings = $stmt->fetchAll();
            $allListings = array_merge($allListings, $listings);
        }
        
        // Randomize combined results if requested
        if ($randomize) {
            shuffle($allListings);
            $allListings = array_slice($allListings, 0, $limit);
        }
        
        echo json_encode(['success' => true, 'listings' => $allListings, 'page' => $page, 'randomized' => $randomize]);
        exit;
    }
    
    // Single table query with optional random ordering
    $orderBy = $randomize ? 'ORDER BY RAND()' : 'ORDER BY l.created_at DESC';
    $stmt = $db->prepare("SELECT l.*, u.name as agent_name, u.verified as agent_verified FROM $table l JOIN users u ON l.agent_id = u.id WHERE l.status = ? $orderBy LIMIT ? OFFSET ?");
    $stmt->execute([$status, (int)$limit, (int)$offset]);
    $listings = $stmt->fetchAll();
    
    // Add listing_type to each result for consistency
    foreach ($listings as &$listing) {
        $listing['listing_type'] = $listingType;
    }
    
    echo json_encode(['success' => true, 'listings' => $listings, 'page' => $page, 'randomized' => $randomize]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch listings', 'message' => $e->getMessage()]);
}