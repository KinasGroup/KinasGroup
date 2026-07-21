<?php
/**
 * Admin: create or update a blog post.
 * Accepts form POST (csrf_token, id [optional], title, slug, category,
 * tags, featured_image, excerpt, body, published).
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

$db = Database::getInstance()->getConnection();

$id            = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title         = trim(Security::sanitizeInput($_POST['title'] ?? ''));
$slugInput     = trim(Security::sanitizeInput($_POST['slug'] ?? ''));
$category      = $_POST['category'] ?? 'news';
$tags          = trim(Security::sanitizeInput($_POST['tags'] ?? ''));
$featuredImage = trim(Security::sanitizeInput($_POST['featured_image'] ?? ''));
$excerpt       = trim(Security::sanitizeInput($_POST['excerpt'] ?? ''));
$body          = trim($_POST['body'] ?? ''); // stored as-is; blog/post.php escapes on output
$published     = isset($_POST['published']) ? 1 : 0;

$validCategories = ['automobile', 'realestate', 'solar', 'marketplace', 'news', 'guides'];
if (!in_array($category, $validCategories, true)) {
    $category = 'news';
}

if ($title === '' || $body === '') {
    $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Title and body are required.'];
    header('Location: /admin/blog-edit.php' . ($id ? "?id=$id" : ''));
    exit;
}

// Slugify: from the provided slug, or from the title if left blank.
function blog_slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

$slug = blog_slugify($slugInput !== '' ? $slugInput : $title);
if ($slug === '') {
    $slug = 'post-' . time();
}

try {
    // Ensure slug uniqueness — append a numeric suffix on collision with a
    // different post (not this one, when editing).
    $baseSlug = $slug;
    $suffix = 1;
    while (true) {
        $check = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $check->execute([$slug, $id]);
        if (!$check->fetch()) break;
        $slug = $baseSlug . '-' . (++$suffix);
    }

    $publishedAt = null;
    if ($published) {
        if ($id > 0) {
            // Keep the original published_at if it was already published;
            // otherwise this is the moment it first goes live.
            $existing = $db->prepare("SELECT published, published_at FROM blog_posts WHERE id = ?");
            $existing->execute([$id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            $publishedAt = ($row && $row['published'] && $row['published_at']) ? $row['published_at'] : date('Y-m-d H:i:s');
        } else {
            $publishedAt = date('Y-m-d H:i:s');
        }
    }

    if ($id > 0) {
        $stmt = $db->prepare(
            "UPDATE blog_posts SET
                title = ?, slug = ?, excerpt = ?, body = ?, featured_image = ?,
                category = ?, tags = ?, published = ?, published_at = ?
             WHERE id = ?"
        );
        $stmt->execute([$title, $slug, $excerpt, $body, $featuredImage, $category, $tags, $published, $publishedAt, $id]);
        $postId = $id;
        $flashMsg = 'Post updated.';
    } else {
        $stmt = $db->prepare(
            "INSERT INTO blog_posts (slug, title, excerpt, body, featured_image, author_id, category, tags, published, published_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$slug, $title, $excerpt, $body, $featuredImage, SessionManager::getUserId(), $category, $tags, $published, $publishedAt]);
        $postId = $db->lastInsertId();
        $flashMsg = 'Post created.';
    }

    Security::logActivity(SessionManager::getUserId(), 'blog_post_saved', "Post #$postId: $title");

    $_SESSION['blog_flash'] = ['type' => 'success', 'message' => $flashMsg];
    header('Location: /admin/blog.php');
    exit;

} catch (PDOException $e) {
    error_log('Blog save error: ' . $e->getMessage());
    $_SESSION['blog_flash'] = ['type' => 'error', 'message' => 'Failed to save post. Please try again.'];
    header('Location: /admin/blog-edit.php' . ($id ? "?id=$id" : ''));
    exit;
}
