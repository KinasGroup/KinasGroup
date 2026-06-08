<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

$query = trim($_GET['q'] ?? '');
// FIX: cast and clamp $limit — never interpolate user input into SQL
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $searchTerm = '%' . $query . '%';
    $results = [];

    // FIX: LIMIT is now a validated integer, safe to interpolate
    $stmt = $db->prepare(
        "SELECT id, title, price, 'car' as type FROM car_listings
         WHERE status = 'active' AND title LIKE ? LIMIT $limit"
    );
    $stmt->execute([$searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    $stmt = $db->prepare(
        "SELECT id, title, price, 'property' as type FROM property_listings
         WHERE status = 'active' AND title LIKE ? LIMIT $limit"
    );
    $stmt->execute([$searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    $stmt = $db->prepare(
        "SELECT id, title, price, 'marketplace' as type FROM marketplace_listings
         WHERE status = 'active' AND title LIKE ? LIMIT $limit"
    );
    $stmt->execute([$searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
