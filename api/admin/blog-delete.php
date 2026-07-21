<?php
/**
 * Admin: delete a blog post.
 * Accepts form POST (csrf_token, id).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

SessionManager::requireAdmin();

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Security token expired. Please try again.'];
    header('Location: /admin/blog.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Invalid post.'];
    header('Location: /admin/blog.php');
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("SELECT title FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Post not found.'];
        header('Location: /admin/blog.php');
        exit;
    }

    // blog_comments.post_id has ON DELETE CASCADE, so comments are cleaned
    // up automatically — no manual delete needed here.
    $db->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);

    Security::logActivity(SessionManager::getUserId(), 'blog_post_deleted', "Post #$id: {$post['title']}");

    $_SESSION['blog_flash'] = ['type' => 'success', 'message' => 'Post deleted.'];
    header('Location: /admin/blog.php');
    exit;

} catch (PDOException $e) {
    error_log('Blog delete error: ' . $e->getMessage());
    $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Failed to delete post. Please try again.'];
    header('Location: /admin/blog.php');
    exit;
}
