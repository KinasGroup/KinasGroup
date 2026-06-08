<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$slug = $_GET['slug'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name, u.name as author_name FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id WHERE p.slug = ? AND p.status = 'published'");
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    
    if ($post) {
        $stmt = $db->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
        $stmt->execute([$post['id']]);
        echo json_encode(['success' => true, 'post' => $post]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch post']);
}