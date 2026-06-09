<?php
/**
 * KINAS GROUP — Blog: Search
 * Full-text search across blog_posts.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

$db = Database::getInstance()->getConnection();
$query = trim($_GET['q'] ?? '');

$results = [];
$total   = 0;
if ($query !== '') {
    $like = '%' . $query . '%';
    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM blog_posts
        WHERE published = 1 AND (title LIKE ? OR body LIKE ? OR excerpt LIKE ?)
    ");
    $countStmt->execute([$like, $like, $like]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.*, u.name AS author_name
        FROM blog_posts p LEFT JOIN users u ON p.author_id = u.id
        WHERE p.published = 1 AND (p.title LIKE ? OR p.body LIKE ? OR p.excerpt LIKE ?)
        ORDER BY
            CASE WHEN p.title LIKE ? THEN 0 ELSE 1 END,
            p.published_at DESC
        LIMIT 30
    ");
    $stmt->execute([$like, $like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$categoryLabels = [
    'automobile'  => 'Automobile',
    'realestate'  => 'Real Estate',
    'solar'       => 'Solar Energy',
    'marketplace' => 'Marketplace',
    'news'        => 'Company News',
    'guides'      => "Buyer's Guides",
];

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.search-hero { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); padding: 60px 20px; text-align: center; }
.search-hero h1 { font-family: 'Prata', serif; font-size: 42px; color: white; margin-bottom: 24px; }
.search-form { max-width: 500px; margin: 0 auto; display: flex; gap: 12px; }
.search-form input { flex: 1; padding: 14px 20px; border: 1px solid #333; background: #1A1A1A; border-radius: 40px; color: white; font-family: 'Inter', sans-serif; }
.search-form input::placeholder { color: rgba(255,255,255,0.4); }
.search-form button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.search-form button:hover { background: #A8882E; }
.container { max-width: 800px; margin: 0 auto; padding: 60px 20px; }
.results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 16px; border-bottom: 1px solid #E0E0E0; flex-wrap: wrap; gap: 8px; }
.results-header p { color: #666; }
.results-header strong { color: #C6A43F; }
.results-count { color: #C6A43F; font-size: 14px; }
.search-results { display: flex; flex-direction: column; gap: 20px; }
.result-item { background: white; border-radius: 16px; padding: 24px; border: 1px solid #E0E0E0; transition: all 0.3s; }
.result-item:hover { transform: translateX(5px); border-color: #C6A43F; }
.result-item a { text-decoration: none; display: block; }
.result-item h3 { font-size: 18px; font-weight: 700; color: #C6A43F; margin-bottom: 12px; }
.result-item h3:hover { text-decoration: underline; }
.result-item p { font-size: 14px; color: #666; margin-bottom: 12px; line-height: 1.5; }
.result-meta { font-size: 12px; color: #999; }
.result-category { display: inline-block; padding: 2px 8px; background: #FEFBF5; color: #C6A43F; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 6px; }
.no-results, .no-query { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 1px solid #E0E0E0; }
.no-results i, .no-query i { font-size: 48px; color: #C6A43F; margin-bottom: 20px; display: block; }
.no-results h3, .no-query h3 { font-size: 24px; margin-bottom: 12px; color: #0A0A0A; }
.no-results p, .no-query p { color: #666; }
.btn-browse { display: inline-block; padding: 12px 28px; background: #C6A43F; border-radius: 40px; color: #0A0A0A; text-decoration: none; font-weight: 600; margin-top: 20px; }
.btn-browse:hover { background: #A8882E; }
mark { background: #FEF3C7; color: #92400E; padding: 1px 3px; border-radius: 3px; }
@media (max-width: 768px) { .search-hero h1 { font-size: 32px; } .search-form { flex-direction: column; padding: 0 20px; } .results-header { flex-direction: column; text-align: center; } }
</style>

<div class="search-hero">
    <h1>Search Results</h1>
    <form class="search-form" method="GET" action="search.php">
        <input type="text" name="q" placeholder="Search articles..." value="<?= htmlspecialchars($query) ?>" autofocus>
        <button type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
</div>

<div class="container">
    <?php if ($query === ''): ?>
        <div class="no-query">
            <i class="fas fa-search"></i>
            <h3>Enter a search term</h3>
            <p>Search for articles, news, or guides by keyword or phrase.</p>
            <a href="/blog/" class="btn-browse">Browse all articles</a>
        </div>
    <?php else: ?>
        <div class="results-header">
            <p>Results for: <strong>"<?= htmlspecialchars($query) ?>"</strong></p>
            <span class="results-count"><?= $total ?> match<?= $total === 1 ? '' : 'es' ?></span>
        </div>
        <?php if (empty($results)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No articles match "<?= htmlspecialchars($query) ?>"</h3>
                <p>Try different keywords or browse our latest articles.</p>
                <a href="/blog/" class="btn-browse">Browse all articles</a>
            </div>
        <?php else: ?>
            <div class="search-results">
                <?php foreach ($results as $r):
                    $catLabel = $categoryLabels[$r['category']] ?? ucfirst($r['category']);
                    $excerpt = (string)($r['excerpt'] ?: $r['body']);
                    $excerpt = mb_strimwidth($excerpt, 0, 220, '…');
                ?>
                <div class="result-item">
                    <a href="/blog/post.php?id=<?= (int)$r['id'] ?>">
                        <h3><?= htmlspecialchars($r['title']) ?></h3>
                        <p><?= htmlspecialchars($excerpt) ?></p>
                        <span class="result-meta">
                            <i class="fas fa-calendar"></i> <?= htmlspecialchars(date('F j, Y', strtotime($r['published_at'] ?: $r['created_at']))) ?>
                            <span class="result-category"><?= htmlspecialchars($catLabel) ?></span>
                        </span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
