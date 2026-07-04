<?php
/**
 * KINAS GROUP — Blog: Home
 * Lists featured + recent posts from the blog_posts table.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();

// Featured = most-viewed published post
$featuredStmt = $db->query("
    SELECT p.*, u.name AS author_name,
           (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND approved = 1) AS comment_count
    FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
    WHERE p.published = 1
    ORDER BY p.views DESC, p.published_at DESC
    LIMIT 1
");
$featured = $featuredStmt->fetch(PDO::FETCH_ASSOC);

// Categories with post counts
$catStmt = $db->query("
    SELECT category, COUNT(*) AS cnt
    FROM blog_posts WHERE published = 1
    GROUP BY category
");
$catCounts = [];
foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $catCounts[$row['category']] = (int)$row['cnt'];
}

// Recent posts (exclude the featured one)
$recentStmt = $db->prepare("
    SELECT p.*, u.name AS author_name
    FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
    WHERE p.published = 1" . ($featured ? " AND p.id != " . (int)$featured['id'] : "") . "
    ORDER BY p.published_at DESC
    LIMIT 6
");
$recentStmt->execute();
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [
    'automobile'  => ['Automobile',         'fa-car'],
    'realestate'  => ['Real Estate',        'fa-home'],
    'solar'       => ['Solar Energy',       'fa-solar-panel'],
    'marketplace' => ['Marketplace',        'fa-store'],
    'news'        => ['Company News',       'fa-newspaper'],
    'guides'      => ["Buyer's Guides",     'fa-book-open'],
];

$totalPosts = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE published = 1")->fetchColumn();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.blog-hero { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); padding: 80px 20px; text-align: center; }
.blog-hero h1 { font-family: 'Prata', serif; font-size: 48px; color: white; margin-bottom: 16px; }
.blog-hero p { color: #B0B0B0; font-size: 18px; margin-bottom: 30px; }
.blog-search { max-width: 500px; margin: 0 auto; display: flex; gap: 12px; }
.blog-search input { flex: 1; padding: 14px 20px; border: 1px solid #333; background: #1A1A1A; border-radius: 40px; color: white; font-family: 'Inter', sans-serif; }
.blog-search input::placeholder { color: rgba(255,255,255,0.4); }
.blog-search button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.blog-search button:hover { background: #A8882E; transform: translateY(-2px); }
.container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
.featured-section { background: white; border-radius: 24px; overflow: hidden; margin-bottom: 60px; border: 1px solid #E0E0E0; }
.featured-section.empty { padding: 60px 30px; text-align: center; color: #999; }
.featured-section.empty i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 12px; }
.featured-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.featured-image { position: relative; height: 400px; overflow: hidden; background: #F0F0F0; }
.featured-image img { width: 100%; height: 100%; object-fit: cover; }
.category-badge { position: absolute; top: 20px; left: 20px; background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
.featured-content { padding: 40px; display: flex; flex-direction: column; justify-content: center; }
.post-meta { display: flex; gap: 20px; font-size: 13px; color: #666; margin-bottom: 16px; flex-wrap: wrap; }
.post-meta i { color: #C6A43F; margin-right: 6px; }
.featured-content h2 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 16px; line-height: 1.3; }
.featured-content h2 a { color: inherit; text-decoration: none; }
.featured-content h2 a:hover { color: #C6A43F; }
.featured-content p { color: #666; line-height: 1.6; margin-bottom: 24px; }
.read-more { color: #C6A43F; text-decoration: none; font-weight: 600; transition: color 0.3s; }
.read-more:hover { color: #A8882E; text-decoration: underline; }
.categories-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 60px; }
.category-card { background: white; border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; border: 1px solid #E0E0E0; transition: all 0.3s; }
.category-card:hover { transform: translateY(-5px); border-color: #C6A43F; }
.category-card i { font-size: 36px; color: #C6A43F; margin-bottom: 12px; display: block; }
.category-card span { color: #0A0A0A; font-weight: 600; }
.category-card .count { display: block; color: #999; font-size: 11px; margin-top: 4px; font-weight: 400; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
.section-header h2 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 50px; }
.post-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; transition: all 0.3s; }
.post-card:hover { transform: translateY(-5px); border-color: #C6A43F; }
.post-image { position: relative; height: 220px; overflow: hidden; background: #F0F0F0; }
.post-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.post-card:hover .post-image img { transform: scale(1.05); }
.post-content { padding: 20px; }
.post-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #0A0A0A; line-height: 1.35; }
.post-content h3 a { color: inherit; text-decoration: none; }
.post-content h3 a:hover { color: #C6A43F; }
.post-content p { color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
.empty-state { padding: 60px 20px; text-align: center; color: #999; }
.empty-state p { font-size: 14px; }
.newsletter-section { background: #0A0A0A; padding: 60px 20px; text-align: center; }
.newsletter-card { max-width: 500px; margin: 0 auto; }
.newsletter-card h3 { font-family: 'Prata', serif; font-size: 28px; color: white; margin-bottom: 12px; }
.newsletter-card p { color: #B0B0B0; margin-bottom: 24px; }
.newsletter-form { display: flex; gap: 12px; flex-wrap: wrap; }
.newsletter-form input { flex: 1; padding: 14px 20px; border: 1px solid #333; background: #1A1A1A; border-radius: 40px; color: white; }
.newsletter-form input::placeholder { color: rgba(255,255,255,0.4); }
.newsletter-form button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
@media (max-width: 768px) { .featured-grid { grid-template-columns: 1fr; } .featured-image { height: 250px; } .featured-content { padding: 25px; } .featured-content h2 { font-size: 22px; } .posts-grid { grid-template-columns: 1fr; } .blog-hero h1 { font-size: 32px; } }
</style>

<div class="blog-hero">
    <h1>Kinas Group Blog</h1>
    <p>Insights, updates, and stories from the world of luxury</p>
    <form class="blog-search" onsubmit="event.preventDefault(); var q = this.querySelector('input').value.trim(); if (q) window.location.href = '/blog/search.php?q=' + encodeURIComponent(q);">
        <input type="text" name="q" placeholder="Search articles...">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
</div>

<div class="container">
    <?php if ($featured): ?>
    <div class="featured-section">
        <div class="featured-grid">
            <div class="featured-image">
                <?php if (!empty($featured['featured_image'])): ?>
                    <img src="<?= htmlspecialchars($featured['featured_image']) ?>" alt="<?= htmlspecialchars($featured['title']) ?>">
                <?php else: ?>
                    <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80" alt="">
                <?php endif; ?>
                <span class="category-badge"><?= htmlspecialchars($categoryLabels[$featured['category']][0] ?? ucfirst($featured['category'])) ?></span>
            </div>
            <div class="featured-content">
                <div class="post-meta">
                    <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('F j, Y', strtotime($featured['published_at'] ?: $featured['created_at']))) ?></span>
                    <span><i class="fas fa-user"></i> By <?= htmlspecialchars($featured['author_name'] ?: 'Admin') ?></span>
                    <span><i class="fas fa-comment"></i> <?= (int)$featured['comment_count'] ?> Comment<?= (int)$featured['comment_count'] === 1 ? '' : 's' ?></span>
                </div>
                <h2><a href="/blog/post.php?id=<?= (int)$featured['id'] ?>"><?= htmlspecialchars($featured['title']) ?></a></h2>
                <p><?= htmlspecialchars(mb_strimwidth((string)($featured['excerpt'] ?: $featured['body']), 0, 220, '…')) ?></p>
                <a href="/blog/post.php?id=<?= (int)$featured['id'] ?>" class="read-more">Read More →</a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="featured-section empty">
        <i class="fas fa-newspaper"></i>
        <p>No featured article yet — check back soon.</p>
        <p style="margin-top:6px; color:#bbb; font-size:12px;">Articles published in the blog will appear here once they go live.</p>
    </div>
    <?php endif; ?>

    <div class="categories-grid">
        <?php foreach ($categoryLabels as $catKey => $info): ?>
            <a href="/blog/category.php?cat=<?= htmlspecialchars($catKey) ?>" class="category-card">
                <i class="fas <?= htmlspecialchars($info[1]) ?>"></i>
                <span><?= htmlspecialchars($info[0]) ?></span>
                <span class="count"><?= (int)($catCounts[$catKey] ?? 0) ?> article<?= ($catCounts[$catKey] ?? 0) === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="section-header">
        <h2>Recent Articles</h2>
        <?php if ($totalPosts > 6): ?>
            <a href="/blog/search.php" class="read-more">View all <?= $totalPosts ?> articles →</a>
        <?php endif; ?>
    </div>

    <div class="posts-grid">
        <?php if (empty($recent)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <p>No articles yet. <?= $totalPosts === 0 ? 'Check back soon — fresh content is on the way.' : 'Try browsing the categories above.' ?></p>
            </div>
        <?php else: foreach ($recent as $p): ?>
            <div class="post-card">
                <div class="post-image">
                    <?php if (!empty($p['featured_image'])): ?>
                        <img src="<?= htmlspecialchars($p['featured_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                    <?php else: ?>
                        <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&q=80" alt="">
                    <?php endif; ?>
                    <span class="category-badge"><?= htmlspecialchars($categoryLabels[$p['category']][0] ?? ucfirst($p['category'])) ?></span>
                </div>
                <div class="post-content">
                    <div class="post-meta">
                        <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('M j, Y', strtotime($p['published_at'] ?: $p['created_at']))) ?></span>
                        <span><i class="fas fa-eye"></i> <?= number_format((int)($p['views'] ?? 0)) ?> views</span>
                    </div>
                    <h3><a href="/blog/post.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h3>
                    <p><?= htmlspecialchars(mb_strimwidth((string)($p['excerpt'] ?: $p['body']), 0, 130, '…')) ?></p>
                    <a href="/blog/post.php?id=<?= (int)$p['id'] ?>" class="read-more">Read More →</a>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="newsletter-section">
    <div class="newsletter-card">
        <h3>Subscribe to Our Newsletter</h3>
        <p>Get the latest luxury insights and exclusive offers delivered to your inbox</p>
        <form class="newsletter-form kinas-newsletter-form" data-source="blog_index">
            <input type="email" name="email" placeholder="Your email address" required>
            <button type="submit">Subscribe</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
