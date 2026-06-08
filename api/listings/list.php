<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 12;
$offset = ($page - 1) * $limit;
$listingType = $_GET['type'] ?? 'car';
$status = $_GET['status'] ?? 'active';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $stmt = $db->prepare("SELECT l.*, u.name as agent_name, u.verified as agent_verified FROM $table l JOIN users u ON l.agent_id = u.id WHERE l.status = ? ORDER BY l.created_at DESC LIMIT $offset, $limit");
    $stmt->execute([$status]);
    $listings = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'listings' => $listings, 'page' => $page]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch listings']);
}