<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') as post_count FROM blog_categories c ORDER BY c.name");
    
    echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch categories']);
}