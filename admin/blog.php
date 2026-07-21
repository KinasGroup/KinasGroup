<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();
$csrf = Security::generateCSRFToken();

$flash = $_SESSION['blog_flash'] ?? null;
unset($_SESSION['blog_flash']);

$posts = $db->query(
    "SELECT p.*, u.name AS author_name
     FROM blog_posts p
     LEFT JOIN users u ON u.id = p.author_id
     ORDER BY p.created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.bl-wrap { max-width: 1100px; }
.bl-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 24px; }
.bl-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.bl-head h1 { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; margin: 0; }
.bl-btn { padding: 10px 20px; border-radius: 40px; font-weight: 600; font-size: 13px; cursor: pointer; border: none; text-decoration: none; display: inline-block; transition: all .2s; }
.bl-btn-primary { background: #C6A43F; color: #0A0A0A; }
.bl-btn-primary:hover { background: #A8882E; color: #0A0A0A; }
.bl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.bl-table th { text-align: left; padding: 10px 12px; background: #F8F8F8; color: #666; font-weight: 600; border-bottom: 1px solid #E0E0E0; }
.bl-table td { padding: 12px; border-bottom: 1px solid #F0F0F0; vertical-align: middle; }
.bl-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.bl-badge.published { background: #E8F5E9; color: #2E7D32; }
.bl-badge.draft { background: #EEE; color: #666; }
.bl-actions a, .bl-actions button { font-size: 12px; margin-right: 10px; color: #666; text-decoration: none; background: none; border: none; cursor: pointer; padding: 0; font-weight: 600; }
.bl-actions a:hover, .bl-actions button:hover { color: #C6A43F; }
.bl-actions .danger:hover { color: #C62828; }
.bl-flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
.bl-flash.success { background: #E8F5E9; color: #2E7D32; }
.bl-flash.error { background: #FFEBEE; color: #C62828; }
.bl-empty { text-align: center; padding: 40px; color: #999; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="bl-wrap">
        <?php if ($flash): ?>
            <div class="bl-flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="bl-card">
            <div class="bl-head">
                <h1>Blog Posts</h1>
                <a href="blog-edit.php" class="bl-btn bl-btn-primary"><i class="fas fa-plus"></i> New Post</a>
            </div>

            <?php if (empty($posts)): ?>
                <div class="bl-empty">No blog posts yet. Create your first one above.</div>
            <?php else: ?>
            <table class="bl-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                        <td><?= htmlspecialchars(ucfirst($p['category'])) ?></td>
                        <td><?= htmlspecialchars($p['author_name'] ?? '—') ?></td>
                        <td><span class="bl-badge <?= $p['published'] ? 'published' : 'draft' ?>"><?= $p['published'] ? 'Published' : 'Draft' ?></span></td>
                        <td><?= (int)$p['views'] ?></td>
                        <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        <td class="bl-actions">
                            <a href="blog-edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
                            <form method="POST" action="/api/admin/blog-delete.php" style="display:inline;" onsubmit="return confirm('Delete this post permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
