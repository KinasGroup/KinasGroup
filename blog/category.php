<?php
/**
 * KINAS GROUP — Blog: Category Listing
 * Real posts from the blog_posts table, filtered by category.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();

$category = $_GET['cat'] ?? 'automobile';
$allowedCategories = ['automobile','realestate','solar','marketplace','news','guides'];
if (!in_array($category, $allowedCategories, true)) $category = 'automobile';

$categoryTitles = [
    'automobile'  => 'Automobile',
    'realestate'  => 'Real Estate',
    'solar'       => 'Solar Energy',
    'marketplace' => 'Marketplace',
    'news'        => 'Company News',
    'guides'      => "Buyer's Guides",
];
$current_title = $categoryTitles[$category];

$sort   = $_GET['sort']   ?? 'latest';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

$orderBy = match($sort) {
    'popular' => 'views DESC',
    'oldest'  => 'published_at ASC',
    default   => 'published_at DESC',
};

$countStmt = $db->prepare("SELECT COUNT(*) FROM blog_posts WHERE published = 1 AND category = ?");
$countStmt->execute([$category]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $db->prepare("
    SELECT p.*, u.name AS author_name
    FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
    WHERE p.published = 1 AND p.category = ?
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$category]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.category-hero { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); padding: 60px 20px; text-align: center; }
.category-hero h1 { font-family: 'Prata', serif; font-size: 42px; color: #C6A43F; margin-bottom: 12px; }
.category-hero p { color: #B0B0B0; font-size: 16px; }
.container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
.category-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; flex-wrap: wrap; gap: 15px; }
.results-count { color: #666; font-size: 14px; }
.sort-form { display: flex; gap: 10px; align-items: center; }
.sort-select { padding: 8px 16px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; background: white; }
.posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 50px; }
.post-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; transition: all 0.3s; }
.post-card:hover { transform: translateY(-5px); border-color: #C6A43F; box-shadow: 0 12px 24px rgba(0,0,0,0.06); }
.post-image { position: relative; height: 220px; overflow: hidden; background: #F0F0F0; }
.post-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; display: block; }
.post-card:hover .post-image img { transform: scale(1.05); }
.category-tag { position: absolute; top: 15px; left: 15px; background: #C6A43F; color: #0A0A0A; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
.post-content { padding: 20px; }
.post-meta { display: flex; gap: 16px; font-size: 12px; color: #666; margin-bottom: 12px; flex-wrap: wrap; }
.post-meta i { color: #C6A43F; margin-right: 4px; }
.post-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #0A0A0A; line-height: 1.35; }
.post-content h3 a { color: inherit; text-decoration: none; }
.post-content h3 a:hover { color: #C6A43F; }
.post-content p { color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
.read-more { color: #C6A43F; text-decoration: none; font-weight: 600; }
.read-more:hover { text-decoration: underline; }
.pagination { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
.page-btn { padding: 10px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-decoration: none; color: #333; font-size: 13px; }
.page-btn.active, .page-btn:hover:not(.disabled) { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.page-btn.disabled { color: #CCC; cursor: not-allowed; }
.empty-state { padding: 80px 20px; text-align: center; color: #999; }
.empty-state i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 14px; }
@media (max-width: 768px) { .posts-grid { grid-template-columns: 1fr; } .category-hero h1 { font-size: 32px; } }
</style>

<div class="category-hero">
    <h1><?= htmlspecialchars($current_title) ?></h1>
    <p>Explore articles about <?= htmlspecialchars(strtolower($current_title)) ?></p>
</div>

<div class="container">
    <div class="category-header">
        <div class="results-count">
            <?= $total === 0 ? 'No' : number_format($total) ?>
            article<?= $total === 1 ? '' : 's' ?>
            <?= $total > 0 ? 'in this category' : 'yet' ?>
        </div>
        <form class="sort-form" method="GET">
            <input type="hidden" name="cat" value="<?= htmlspecialchars($category) ?>">
            <select name="sort" class="sort-select" onchange="this.form.submit()">
                <option value="latest"  <?= $sort === 'latest'  ? 'selected' : '' ?>>Latest</option>
                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                <option value="oldest"  <?= $sort === 'oldest'  ? 'selected' : '' ?>>Oldest</option>
            </select>
        </form>
    </div>

    <div class="posts-grid">
        <?php if (empty($posts)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-folder-open"></i>
                <p>No articles in this category yet.</p>
                <p style="margin-top:6px; color:#bbb; font-size:12px;">Check back soon — or browse all categories.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $p): ?>
                <div class="post-card">
                    <div class="post-image">
                        <?php if (!empty($p['featured_image'])): ?>
                            <img src="<?= htmlspecialchars($p['featured_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&q=80" alt="">
                        <?php endif; ?>
                        <span class="category-tag"><?= htmlspecialchars($current_title) ?></span>
                    </div>
                    <div class="post-content">
                        <div class="post-meta">
                            <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('M j, Y', strtotime($p['published_at'] ?: $p['created_at']))) ?></span>
                            <span><i class="fas fa-eye"></i> <?= number_format((int)($p['views'] ?? 0)) ?> views</span>
                        </div>
                        <h3><a href="/blog/post.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></a></h3>
                        <p><?= htmlspecialchars(mb_strimwidth((string)($p['excerpt'] ?: $p['body']), 0, 140, '…')) ?></p>
                        <a href="/blog/post.php?id=<?= (int)$p['id'] ?>" class="read-more">Read More →</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1):
        $baseQuery = ['cat' => $category, 'sort' => $sort];
        $pageUrl = fn($p) => '?' . http_build_query($baseQuery + ['page' => $p]);
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="page-btn" href="<?= $pageUrl($page-1) ?>">← Prev</a>
        <?php else: ?>
            <span class="page-btn disabled">← Prev</span>
        <?php endif; ?>

        <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i === $page): ?>
                <span class="page-btn active"><?= $i ?></span>
            <?php else: ?>
                <a class="page-btn" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a class="page-btn" href="<?= $pageUrl($page+1) ?>">Next →</a>
        <?php else: ?>
            <span class="page-btn disabled">Next →</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
