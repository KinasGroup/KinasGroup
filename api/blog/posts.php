<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 10;
$offset = ($page - 1) * $limit;
$categoryId = $_GET['category_id'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT p.*, c.name as category_name, u.name as author_name FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id WHERE p.status = 'published'";
    $params = [];
    
    if ($categoryId) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
    
    $sql .= " ORDER BY p.published_at DESC LIMIT $offset, $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode(['success' => true, 'posts' => $stmt->fetchAll()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch posts']);
}