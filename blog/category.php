<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../templates/header.php';

$category = $_GET['cat'] ?? 'automobile';
$category_titles = ['automobile' => 'Automobile', 'realestate' => 'Real Estate', 'solar' => 'Solar Energy', 'marketplace' => 'Marketplace', 'news' => 'Company News', 'guides' => "Buyer's Guides"];
$current_title = $category_titles[$category] ?? 'Blog';
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
.sort-select { padding: 8px 16px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; }
.posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 50px; }
.post-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; transition: all 0.3s; }
.post-card:hover { transform: translateY(-5px); border-color: #C6A43F; }
.post-image { position: relative; height: 220px; overflow: hidden; }
.post-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.post-card:hover .post-image img { transform: scale(1.05); }
.category-tag { position: absolute; top: 15px; left: 15px; background: #C6A43F; color: #0A0A0A; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.post-content { padding: 20px; }
.post-meta { display: flex; gap: 16px; font-size: 12px; color: #666; margin-bottom: 12px; }
.post-meta i { color: #C6A43F; margin-right: 4px; }
.post-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #0A0A0A; }
.post-content p { color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
.read-more { color: #C6A43F; text-decoration: none; font-weight: 600; }
.pagination { display: flex; justify-content: center; gap: 8px; }
.page-btn { padding: 10px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
.page-btn.active, .page-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
@media (max-width: 768px) { .posts-grid { grid-template-columns: 1fr; } .category-hero h1 { font-size: 32px; } }
</style>

<div class="category-hero"><h1><?php echo $current_title; ?></h1><p>Explore articles about <?php echo strtolower($current_title); ?></p></div>

<div class="container">
    <div class="category-header"><div class="results-count">Showing 12 articles</div><select id="sortSelect" class="sort-select"><option>Latest</option><option>Most Popular</option><option>Oldest</option></select></div>
    <div class="posts-grid">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&q=80" alt="Post"><span class="category-tag"><?php echo $current_title; ?></span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> May <?php echo 10 - $i; ?>, 2024</span><span><i class="fas fa-eye"></i> <?php echo rand(500, 3000); ?> views</span></div><h3>Sample Article Title <?php echo $i; ?></h3><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore...</p><a href="/blog/post.php?id=<?php echo $i; ?>" class="read-more">Read More →</a></div></div>
        <?php endfor; ?>
    </div>
    <div class="pagination"><button class="page-btn active">1</button><button class="page-btn">2</button><button class="page-btn">3</button></div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
BLOG_CATEGORY_EOF

echo "✅ Updated: blog/category.php"
