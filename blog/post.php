<?php
/**
 * KINAS GROUP — Blog: Single Post
 * Loads a published post by id/slug, comments, related posts.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();

$ref = $_GET['id'] ?? $_GET['slug'] ?? 1;

// Try by id first, then slug
if (is_numeric($ref)) {
    $stmt = $db->prepare("SELECT p.*, u.name AS author_name, u.avatar AS author_avatar
                         FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
                         WHERE p.id = ? AND p.published = 1 LIMIT 1");
    $stmt->execute([(int)$ref]);
} else {
    $stmt = $db->prepare("SELECT p.*, u.name AS author_name, u.avatar AS author_avatar
                         FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
                         WHERE p.slug = ? AND p.published = 1 LIMIT 1");
    $stmt->execute([$ref]);
}
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

// Increment view count (best effort)
$db->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);

// Comments (top-level + replies)
$cmStmt = $db->prepare("
    SELECT id, parent_id, name, body, created_at
    FROM blog_comments
    WHERE post_id = ? AND approved = 1
    ORDER BY created_at ASC
");
$cmStmt->execute([$post['id']]);
$allComments = $cmStmt->fetchAll(PDO::FETCH_ASSOC);
$topComments = array_values(array_filter($allComments, fn($c) => empty($c['parent_id'])));
$replies     = [];
foreach ($allComments as $c) {
    if (!empty($c['parent_id'])) $replies[$c['parent_id']][] = $c;
}
$commentCount = count($allComments);

// Categories with counts
$catStmt = $db->query("
    SELECT category, COUNT(*) AS cnt
    FROM blog_posts
    WHERE published = 1
    GROUP BY category
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent posts (other than current)
$recStmt = $db->prepare("SELECT id, slug, title, created_at FROM blog_posts
                         WHERE published = 1 AND id != ?
                         ORDER BY published_at DESC LIMIT 5");
$recStmt->execute([$post['id']]);
$recentPosts = $recStmt->fetchAll(PDO::FETCH_ASSOC);

// Share URLs (using current page)
$shareUrl   = urlencode('/blog/post.php?id=' . $post['id']);
$shareTitle = urlencode($post['title']);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Security::generateCSRFToken();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.post-container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; display: grid; grid-template-columns: 1fr 320px; gap: 50px; }
.post-header { margin-bottom: 40px; }
.post-category { display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 16px; text-transform: capitalize; text-decoration: none; }
.post-category:hover { background: #A8882E; }
.post-header h1 { font-family: 'Prata', serif; font-size: 36px; color: #0A0A0A; margin-bottom: 16px; line-height: 1.3; }
.post-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: #666; margin-bottom: 30px; }
.post-meta i { color: #C6A43F; margin-right: 6px; }
.post-featured-image { border-radius: 20px; overflow: hidden; margin-bottom: 30px; background: #F0F0F0; aspect-ratio: 16/9; }
.post-featured-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
.post-content { font-size: 16px; line-height: 1.8; color: #333; }
.post-content h2 { font-family: 'Prata', serif; font-size: 24px; color: #C6A43F; margin: 32px 0 16px; }
.post-content h3 { font-size: 20px; color: #0A0A0A; margin: 24px 0 12px; }
.post-content p { margin-bottom: 20px; }
.post-content blockquote { margin: 30px 0; padding: 20px 30px; background: #F8F8F8; border-left: 4px solid #C6A43F; font-style: italic; }
.post-content blockquote p { margin-bottom: 10px; }
.post-content ul, .post-content ol { margin-bottom: 20px; padding-left: 24px; }
.post-content img { max-width: 100%; border-radius: 12px; }
.post-content a { color: #C6A43F; }
.post-share { margin: 40px 0; padding: 20px 0; border-top: 1px solid #E0E0E0; border-bottom: 1px solid #E0E0E0; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.share-links { display: flex; gap: 12px; }
.share-links a { width: 36px; height: 36px; background: #F8F8F8; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #C6A43F; transition: all 0.3s; text-decoration: none; }
.share-links a:hover { background: #C6A43F; color: #0A0A0A; }
.author-bio { display: flex; gap: 20px; padding: 24px; background: #F8F8F8; border-radius: 20px; margin: 40px 0; align-items: center; }
.author-avatar { width: 80px; height: 80px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: #0A0A0A; flex-shrink: 0; overflow: hidden; }
.author-avatar img { width: 100%; height: 100%; object-fit: cover; }
.author-info h4 { font-size: 18px; margin-bottom: 8px; }
.author-info p { color: #666; font-size: 14px; margin-bottom: 12px; }
.comments-section { margin-top: 40px; }
.comments-section h3 { font-size: 22px; margin-bottom: 24px; }
.comment { display: flex; gap: 16px; margin-bottom: 24px; }
.comment.reply { margin-left: 56px; }
.comment-avatar { width: 48px; height: 48px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0A0A0A; flex-shrink: 0; }
.comment-content { flex: 1; background: #F8F8F8; padding: 16px; border-radius: 16px; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
.comment-header strong { color: #0A0A0A; }
.comment-header span { color: #999; font-size: 12px; }
.comment-body { color: #333; font-size: 14px; line-height: 1.6; }
.no-comments { padding: 30px 20px; text-align: center; color: #999; font-size: 14px; background: #F8F8F8; border-radius: 12px; }
.leave-comment { margin-top: 40px; }
.leave-comment h4 { font-size: 18px; margin-bottom: 20px; }
.comment-form { display: flex; flex-direction: column; gap: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.comment-form input, .comment-form textarea { padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; box-sizing: border-box; }
.comment-form input:focus, .comment-form textarea:focus { outline: none; border-color: #C6A43F; }
.btn-submit { padding: 12px 24px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; width: fit-content; }
.btn-submit:hover { background: #A8882E; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.sidebar-widget { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 30px; }
.sidebar-widget h4 { font-size: 18px; color: #C6A43F; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.sidebar-search { display: flex; gap: 10px; }
.sidebar-search input { flex: 1; padding: 10px 14px; border: 1px solid #E0E0E0; border-radius: 10px; }
.sidebar-search button { padding: 10px 16px; background: #C6A43F; border: none; border-radius: 10px; cursor: pointer; }
.category-list, .recent-posts { list-style: none; padding: 0; margin: 0; }
.category-list li, .recent-posts li { margin-bottom: 12px; }
.category-list a, .recent-posts a { color: #333; text-decoration: none; display: flex; justify-content: space-between; transition: color 0.3s; }
.category-list a:hover, .recent-posts a:hover { color: #C6A43F; }
.recent-posts li span { display: block; font-size: 11px; color: #999; margin-top: 4px; }
.newsletter-widget form { display: flex; flex-direction: column; gap: 12px; }
.newsletter-widget input { padding: 12px; border: 1px solid #E0E0E0; border-radius: 10px; }
.newsletter-widget button { padding: 12px; background: #C6A43F; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error   { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
@media (max-width: 968px) { .post-container { grid-template-columns: 1fr; gap: 40px; } .post-header h1 { font-size: 28px; } .form-row { grid-template-columns: 1fr; gap: 12px; } .comment.reply { margin-left: 20px; } .author-bio { flex-direction: column; text-align: center; } .author-avatar { margin: 0 auto; } }
</style>

<div class="post-container">
    <article class="post-article">
        <header class="post-header">
            <?php
                $categoryNames = [
                    'automobile'  => 'Automobile',
                    'realestate'  => 'Real Estate',
                    'solar'       => 'Solar Energy',
                    'marketplace' => 'Marketplace',
                    'news'        => 'Company News',
                    'guides'      => "Buyer's Guides",
                ];
                $catLabel = $categoryNames[$post['category']] ?? ucfirst($post['category']);
            ?>
            <a href="/blog/category.php?cat=<?= urlencode($post['category']) ?>" class="post-category"><?= htmlspecialchars($catLabel) ?></a>
            <h1><?= htmlspecialchars($post['title']) ?></h1>
            <div class="post-meta">
                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('F j, Y', strtotime($post['published_at'] ?: $post['created_at']))) ?></span>
                <span><i class="fas fa-user"></i> By <?= htmlspecialchars($post['author_name'] ?: 'Kinas Group') ?></span>
                <?php
                    $wordCount = str_word_count(strip_tags($post['body']));
                    $readMin = max(1, (int)ceil($wordCount / 200));
                ?>
                <span><i class="fas fa-clock"></i> <?= $readMin ?> min read</span>
                <span><i class="fas fa-eye"></i> <?= number_format((int)($post['views'] ?? 0)) ?> views</span>
            </div>
            <?php if (!empty($post['featured_image'])): ?>
            <div class="post-featured-image"><img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>"></div>
            <?php endif; ?>
        </header>

        <?php if ($flashSuccess): ?><div class="flash success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError):   ?><div class="flash error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

        <div class="post-content">
            <?php
                // Render body — supports basic Markdown-ish formatting if it looks like it,
                // otherwise preserves paragraphs from newlines.
                $body = (string)$post['body'];
                if (str_contains($body, '## ') || str_contains($body, '<h2>')) {
                    echo $body; // assume pre-formatted HTML
                } else {
                    $paragraphs = preg_split("/\n{2,}/", trim($body));
                    foreach ($paragraphs as $p) {
                        $p = trim($p);
                        if ($p === '') continue;
                        echo '<p>' . nl2br(htmlspecialchars($p)) . '</p>';
                    }
                }
            ?>
        </div>

        <div class="post-share">
            <span style="font-weight:600; color:#333;">Share this article:</span>
            <div class="share-links">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" title="Share on Twitter"><i class="fab fa-twitter"></i></a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" title="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <a href="https://api.whatsapp.com/send?text=<?= $shareTitle ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" title="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>

        <div class="author-bio">
            <div class="author-avatar">
                <?php if (!empty($post['author_avatar'])): ?>
                    <img src="<?= htmlspecialchars($post['author_avatar']) ?>" alt="">
                <?php else: ?>
                    <?= strtoupper(substr($post['author_name'] ?: 'K', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="author-info">
                <h4><?= htmlspecialchars($post['author_name'] ?: 'Kinas Group') ?></h4>
                <p>Contributor at Kinas Group. Sharing insights about luxury markets, sustainable living, and modern investment opportunities.</p>
            </div>
        </div>

        <div class="comments-section">
            <h3>Comments (<?= $commentCount ?>)</h3>
            <?php if (empty($topComments)): ?>
                <div class="no-comments">No comments yet. Be the first to share your thoughts!</div>
            <?php else: ?>
                <div class="comments-list">
                    <?php foreach ($topComments as $c):
                        $initials = strtoupper(substr($c['name'], 0, 1));
                    ?>
                    <div class="comment">
                        <div class="comment-avatar"><?= htmlspecialchars($initials) ?></div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                                <span><?= htmlspecialchars(date('F j, Y', strtotime($c['created_at']))) ?></span>
                            </div>
                            <div class="comment-body"><?= nl2br(htmlspecialchars($c['body'])) ?></div>
                        </div>
                    </div>
                    <?php if (!empty($replies[$c['id']])): ?>
                        <?php foreach ($replies[$c['id']] as $r): ?>
                            <div class="comment reply">
                                <div class="comment-avatar"><?= strtoupper(substr($r['name'], 0, 1)) ?></div>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <strong><?= htmlspecialchars($r['name']) ?></strong>
                                        <span><?= htmlspecialchars(date('F j, Y', strtotime($r['created_at']))) ?></span>
                                    </div>
                                    <div class="comment-body"><?= nl2br(htmlspecialchars($r['body'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="leave-comment">
                <h4>Leave a Comment</h4>
                <form class="comment-form" id="commentForm" method="POST" action="/api/blog/comments.php" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="redirect" value="/blog/post.php?id=<?= (int)$post['id'] ?>">
                    <div class="form-row">
                        <input type="text" name="name" placeholder="Your Name" required maxlength="100">
                        <input type="email" name="email" placeholder="Your Email" required maxlength="255">
                    </div>
                    <textarea name="body" rows="4" placeholder="Your comment…" required maxlength="2000"></textarea>
                    <button type="submit" class="btn-submit" id="commentSubmit"><i class="fas fa-paper-plane"></i> Post Comment</button>
                </form>
                <p style="font-size:12px; color:#888; margin-top:10px;"><i class="fas fa-info-circle"></i> Comments are reviewed before being published.</p>
            </div>
        </div>
    </article>

    <aside class="post-sidebar">
        <div class="sidebar-widget">
            <h4>Search</h4>
            <form class="sidebar-search" action="/blog/search.php" method="GET">
                <input type="text" name="q" placeholder="Search articles..." required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="sidebar-widget">
            <h4>Categories</h4>
            <ul class="category-list">
                <?php
                    $totalPosts = max(1, array_sum(array_column($categories, 'cnt')));
                    if (empty($categories)) {
                        echo '<li style="color:#999;">No posts yet.</li>';
                    } else {
                        foreach ($categories as $cat):
                            $catLink = '/blog/category.php?cat=' . urlencode($cat['category']);
                            $catName = $categoryNames[$cat['category']] ?? ucfirst($cat['category']);
                ?>
                <li><a href="<?= htmlspecialchars($catLink) ?>"><?= htmlspecialchars($catName) ?> <span>(<?= (int)$cat['cnt'] ?>)</span></a></li>
                <?php endforeach; } ?>
            </ul>
        </div>
        <div class="sidebar-widget">
            <h4>Recent Posts</h4>
            <ul class="recent-posts">
                <?php if (empty($recentPosts)): ?>
                    <li style="color:#999;">No recent posts yet.</li>
                <?php else: foreach ($recentPosts as $rp): ?>
                    <li>
                        <a href="/blog/post.php?id=<?= (int)$rp['id'] ?>"><?= htmlspecialchars($rp['title']) ?></a>
                        <span><?= htmlspecialchars(date('F j, Y', strtotime($rp['created_at']))) ?></span>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>
        <div class="sidebar-widget newsletter-widget">
            <h4>Newsletter</h4>
            <p style="margin-bottom: 12px; color:#666; font-size:13px;">Get the latest luxury insights delivered to your inbox.</p>
            <form onsubmit="event.preventDefault(); alert('Newsletter subscription coming soon.');">
                <input type="email" placeholder="Your email" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
    </aside>
</div>

<script>
document.getElementById('commentForm')?.addEventListener('submit', function() {
    var btn = document.getElementById('commentSubmit');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting…'; }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
