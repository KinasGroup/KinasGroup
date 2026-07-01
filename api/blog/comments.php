<?php
/**
 * Blog: comments.
 * - GET  ?post_id=NN      → list approved comments for a post
 * - POST post_id,name,email,body,[parent_id] → submit a comment (goes to moderation queue)
 */
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) { http_response_code(422); echo json_encode(['error' => 'post_id required']); exit; }

    $stmt = $db->prepare("
        SELECT id, parent_id, name, body, created_at
        FROM blog_comments
        WHERE post_id = ? AND approved = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([$postId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'comments' => $rows]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $isJson = stripos($contentType, 'application/json') !== false;
    $data = $isJson ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST;

    // CSRF for form posts
    $token = $data['csrf_token'] ?? '';
    if (!$isJson && ($token === '' || !Security::verifyCSRFToken($token))) {
        if (!$isJson) { $_SESSION['flash_error'] = 'Please refresh the page and try again.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/blog/')); exit; }
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $postId   = (int)($data['post_id'] ?? 0);
    $name     = trim((string)($data['name'] ?? ''));
    $email    = trim((string)($data['email'] ?? ''));
    $body     = trim((string)($data['body'] ?? ''));
    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;

    // If logged in, pre-fill name/email
    $userId = null;
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $u = $db->prepare("SELECT name, email FROM users WHERE id = ?");
        $u->execute([$userId]);
        $uRow = $u->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            if ($name === '')   $name  = $uRow['name'];
            if ($email === '')  $email = $uRow['email'];
        }
    }

    $errors = [];
    if (!$postId)                                       $errors[] = 'Missing post reference.';
    if ($name === '' || strlen($name) > 100)            $errors[] = 'Name is required (max 100 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'A valid email is required.';
    if (strlen($body) < 3)                              $errors[] = 'Comment is too short.';
    if (strlen($body) > 2000)                           $errors[] = 'Comment is too long (max 2000 characters).';
    if ($errors) {
        if (!$isJson) { $_SESSION['flash_error'] = implode(' ', $errors); header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/blog/')); exit; }
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        exit;
    }

    // Verify post exists
    $p = $db->prepare("SELECT id FROM blog_posts WHERE id = ? AND published = 1");
    $p->execute([$postId]);
    if (!$p->fetch()) {
        if (!$isJson) { $_SESSION['flash_error'] = 'Post not found.'; header('Location: /blog/'); exit; }
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    // Sanitize
    $name  = Security::sanitizeInput($name);
    $email = Security::sanitizeInput($email);
    $body  = Security::sanitizeInput($body);

    try {
        $db->prepare("
            INSERT INTO blog_comments (post_id, parent_id, user_id, name, email, body, approved, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ")->execute([$postId, $parentId, $userId, $name, $email, $body]);

        if (!empty($userId)) {
            Security::logActivity($userId, 'comment_submitted', "Comment on blog post $postId");
        }

        if (!$isJson) {
            $_SESSION['flash_success'] = 'Thanks! Your comment has been submitted and is awaiting moderation.';
            $redirect = $data['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/blog/');
            if (!preg_match('#^/[a-zA-Z0-9_\-/]*(\.php)?(\?.*)?$#', $redirect)) $redirect = '/blog/';
            header('Location: ' . $redirect);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Comment submitted for review.']);
    } catch (Exception $e) {
        error_log('comment post error: ' . $e->getMessage());
        if (!$isJson) { $_SESSION['flash_error'] = 'Failed to post comment.'; header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/blog/')); exit; }
        http_response_code(500);
        echo json_encode(['error' => 'Failed to post comment']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
