<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

$postId = (int)($_GET['post_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT c.id, c.content, c.created_at, u.name as user_name
             FROM blog_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.post_id = ? AND c.status = 'approved'
             ORDER BY c.created_at ASC"
        );
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll();

        // FIX: Escape all stored comment content on the way out
        foreach ($comments as &$comment) {
            $comment['content']   = Security::preventXSS($comment['content']);
            $comment['user_name'] = Security::preventXSS($comment['user_name'] ?? 'Anonymous');
        }
        unset($comment);

        echo json_encode(['success' => true, 'comments' => $comments]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch comments']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SessionManager::requireLogin();

    // Rate limit: 3 comments per 5 minutes per user
    Security::rateLimitDB('comment_' . SessionManager::getUserId(), 3, 300);

    $data    = json_decode(file_get_contents('php://input'), true);
    // FIX: sanitise before storing
    $content = Security::sanitizeInput($data['content'] ?? '');

    if (strlen($content) < 3) {
        http_response_code(422);
        echo json_encode(['error' => 'Comment is too short']);
        exit;
    }

    if (strlen($content) > 1000) {
        http_response_code(422);
        echo json_encode(['error' => 'Comment is too long (max 1000 characters)']);
        exit;
    }

    if (!$postId) {
        http_response_code(422);
        echo json_encode(['error' => 'Post ID is required']);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $db->prepare(
            "INSERT INTO blog_comments (post_id, user_id, content, status, created_at)
             VALUES (?, ?, ?, 'pending', NOW())"
        )->execute([$postId, SessionManager::getUserId(), $content]);

        echo json_encode(['success' => true, 'message' => 'Comment submitted for review']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to post comment']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
