<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$listingType = $data['type'] ?? 'car';
$minPrice = $data['min_price'] ?? 0;
$maxPrice = $data['max_price'] ?? 999999999;
$division = $data['division'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
    $table = $tableMap[$listingType] ?? 'car_listings';
    
    $sql = "SELECT * FROM $table WHERE status = 'active' AND price BETWEEN ? AND ?";
    $params = [$minPrice, $maxPrice];
    
    if ($division) {
        $sql .= " AND division = ?";
        $params[] = $division;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'listings' => $listings]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Filter failed']);
}