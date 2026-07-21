<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();
$csrf = Security::generateCSRFToken();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        http_response_code(404);
        include __DIR__ . '/../pages/404.php';
        exit;
    }
}

$categories = ['automobile', 'realestate', 'solar', 'marketplace', 'news', 'guides'];

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.bl-wrap { max-width: 800px; }
.bl-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 24px; }
.bl-card h1 { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; margin: 0 0 20px; }
.bl-field { margin-bottom: 18px; }
.bl-field label { display: block; font-weight: 600; font-size: 13px; color: #333; margin-bottom: 6px; }
.bl-field input[type="text"], .bl-field input[type="url"], .bl-field textarea, .bl-field select {
    width: 100%; padding: 12px 14px; border: 1px solid #DDD; border-radius: 8px;
    font-family: inherit; font-size: 14px; box-sizing: border-box;
}
.bl-field textarea { min-height: 260px; resize: vertical; line-height: 1.5; }
.bl-hint { font-size: 12px; color: #999; margin-top: 4px; }
.bl-row { display: flex; gap: 16px; }
.bl-row > .bl-field { flex: 1; }
.bl-checkbox { display: flex; align-items: center; gap: 8px; }
.bl-checkbox input { width: auto; }
.bl-actions { display: flex; gap: 12px; align-items: center; margin-top: 24px; }
.bl-btn { padding: 12px 24px; border-radius: 40px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; text-decoration: none; display: inline-block; }
.bl-btn-primary { background: #C6A43F; color: #0A0A0A; }
.bl-btn-primary:hover { background: #A8882E; }
.bl-btn-outline { background: transparent; color: #0A0A0A; border: 1.5px solid #0A0A0A; }
.bl-btn-outline:hover { background: #0A0A0A; color: #fff; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="bl-wrap">
        <div class="bl-card">
            <h1><?= $post ? 'Edit Post' : 'New Post' ?></h1>

            <form method="POST" action="/api/admin/blog-save.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <?php if ($post): ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php endif; ?>

                <div class="bl-field">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required maxlength="255"
                           value="<?= htmlspecialchars($post['title'] ?? '') ?>">
                </div>

                <div class="bl-field">
                    <label for="slug">URL Slug</label>
                    <input type="text" id="slug" name="slug" maxlength="200"
                           value="<?= htmlspecialchars($post['slug'] ?? '') ?>"
                           placeholder="leave blank to auto-generate from title">
                    <div class="bl-hint">Used in the post's URL. Letters, numbers, and hyphens only.</div>
                </div>

                <div class="bl-row">
                    <div class="bl-field">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>" <?= ($post['category'] ?? 'news') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bl-field">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" maxlength="500"
                               value="<?= htmlspecialchars($post['tags'] ?? '') ?>"
                               placeholder="comma, separated, tags">
                    </div>
                </div>

                <div class="bl-field">
                    <label for="featured_image">Featured Image URL</label>
                    <input type="url" id="featured_image" name="featured_image" maxlength="500"
                           value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>"
                           placeholder="https://...">
                </div>

                <div class="bl-field">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" style="min-height:80px;" maxlength="500"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                    <div class="bl-hint">Short summary shown on the blog listing page. Leave blank to auto-use the start of the body.</div>
                </div>

                <div class="bl-field">
                    <label for="body">Body</label>
                    <textarea id="body" name="body" required><?= htmlspecialchars($post['body'] ?? '') ?></textarea>
                    <div class="bl-hint">Plain text. Separate paragraphs with a blank line. (Starting a line with "## " renders it as a heading.)</div>
                </div>

                <div class="bl-field bl-checkbox">
                    <input type="checkbox" id="published" name="published" value="1" <?= !empty($post['published']) ? 'checked' : '' ?>>
                    <label for="published" style="margin:0;">Published (visible on the public blog)</label>
                </div>

                <div class="bl-actions">
                    <button type="submit" class="bl-btn bl-btn-primary">
                        <i class="fas fa-save"></i> <?= $post ? 'Save Changes' : 'Create Post' ?>
                    </button>
                    <a href="blog.php" class="bl-btn bl-btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
