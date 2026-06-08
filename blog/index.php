<?php
require_once __DIR__ . '/../api/config/database.php';
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
.blog-search button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.blog-search button:hover { background: #A8882E; transform: translateY(-2px); }
.container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
.featured-section { background: white; border-radius: 24px; overflow: hidden; margin-bottom: 60px; border: 1px solid #E0E0E0; }
.featured-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.featured-image { position: relative; height: 400px; overflow: hidden; }
.featured-image img { width: 100%; height: 100%; object-fit: cover; }
.category-badge { position: absolute; top: 20px; left: 20px; background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.featured-content { padding: 40px; display: flex; flex-direction: column; justify-content: center; }
.post-meta { display: flex; gap: 20px; font-size: 13px; color: #666; margin-bottom: 16px; }
.post-meta i { color: #C6A43F; margin-right: 6px; }
.featured-content h2 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 16px; }
.featured-content p { color: #666; line-height: 1.6; margin-bottom: 24px; }
.read-more { color: #C6A43F; text-decoration: none; font-weight: 600; transition: color 0.3s; }
.read-more:hover { color: #A8882E; }
.categories-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 60px; }
.category-card { background: white; border-radius: 16px; padding: 24px; text-align: center; text-decoration: none; border: 1px solid #E0E0E0; transition: all 0.3s; }
.category-card:hover { transform: translateY(-5px); border-color: #C6A43F; }
.category-card i { font-size: 36px; color: #C6A43F; margin-bottom: 12px; display: block; }
.category-card span { color: #0A0A0A; font-weight: 600; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
.section-header h2 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.sort-select { padding: 8px 16px; border: 1px solid #E0E0E0; border-radius: 8px; font-family: 'Inter', sans-serif; }
.posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px; margin-bottom: 50px; }
.post-card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #E0E0E0; transition: all 0.3s; }
.post-card:hover { transform: translateY(-5px); border-color: #C6A43F; }
.post-image { position: relative; height: 220px; overflow: hidden; }
.post-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
.post-card:hover .post-image img { transform: scale(1.05); }
.post-content { padding: 20px; }
.post-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 12px; color: #0A0A0A; }
.post-content p { color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 40px; }
.page-btn { padding: 10px 16px; background: white; border: 1px solid #E0E0E0; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
.page-btn.active, .page-btn:hover { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.newsletter-section { background: #0A0A0A; padding: 60px 20px; text-align: center; }
.newsletter-card { max-width: 500px; margin: 0 auto; }
.newsletter-card h3 { font-family: 'Prata', serif; font-size: 28px; color: white; margin-bottom: 12px; }
.newsletter-card p { color: #B0B0B0; margin-bottom: 24px; }
.newsletter-form { display: flex; gap: 12px; flex-wrap: wrap; }
.newsletter-form input { flex: 1; padding: 14px 20px; border: 1px solid #333; background: #1A1A1A; border-radius: 40px; color: white; }
.newsletter-form button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
@media (max-width: 768px) { .featured-grid { grid-template-columns: 1fr; } .featured-image { height: 250px; } .featured-content { padding: 25px; } .featured-content h2 { font-size: 22px; } .posts-grid { grid-template-columns: 1fr; } .blog-hero h1 { font-size: 32px; } }
</style>

<div class="blog-hero">
    <h1>Kinas Group Blog</h1>
    <p>Insights, updates, and stories from the world of luxury</p>
    <div class="blog-search"><input type="text" id="blogSearch" placeholder="Search articles..."><button onclick="searchBlog()"><i class="fas fa-search"></i> Search</button></div>
</div>

<div class="container">
    <div class="featured-section"><div class="featured-grid"><div class="featured-image"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80" alt="Featured"><span class="category-badge">Automobile</span></div><div class="featured-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> May 15, 2024</span><span><i class="fas fa-user"></i> By Admin</span><span><i class="fas fa-comment"></i> 12 Comments</span></div><h2>The Future of Luxury: Trends Shaping 2024</h2><p>Explore the emerging trends in luxury automobiles, real estate, and sustainable energy that are redefining the industry landscape in 2024 and beyond.</p><a href="/blog/post.php?id=1" class="read-more">Read More →</a></div></div></div>

    <div class="categories-grid"><a href="/blog/category.php?cat=automobile" class="category-card"><i class="fas fa-car"></i><span>Automobile</span></a><a href="/blog/category.php?cat=realestate" class="category-card"><i class="fas fa-home"></i><span>Real Estate</span></a><a href="/blog/category.php?cat=solar" class="category-card"><i class="fas fa-solar-panel"></i><span>Solar Energy</span></a><a href="/blog/category.php?cat=marketplace" class="category-card"><i class="fas fa-store"></i><span>Marketplace</span></a><a href="/blog/category.php?cat=news" class="category-card"><i class="fas fa-newspaper"></i><span>Company News</span></a><a href="/blog/category.php?cat=guides" class="category-card"><i class="fas fa-book-open"></i><span>Buyer's Guides</span></a></div>

    <div class="section-header"><h2>Recent Articles</h2><select id="sortSelect" class="sort-select"><option>Latest</option><option>Most Popular</option><option>Trending</option></select></div>

    <div class="posts-grid">
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&q=80" alt="Post"><span class="category-badge">Automobile</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> May 10, 2024</span><span><i class="fas fa-eye"></i> 2.3k views</span></div><h3>Top 10 Luxury Cars of 2024</h3><p>Discover the most anticipated luxury vehicles hitting the market this year...</p><a href="/blog/post.php?id=2" class="read-more">Read More →</a></div></div>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=400&q=80" alt="Post"><span class="category-badge">Real Estate</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> May 5, 2024</span><span><i class="fas fa-eye"></i> 1.8k views</span></div><h3>Investing in Premium Properties: A Guide</h3><p>Key factors to consider when investing in high-end real estate...</p><a href="/blog/post.php?id=3" class="read-more">Read More →</a></div></div>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1509391366360-2e959784a276?w=400&q=80" alt="Post"><span class="category-badge">Solar Energy</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> April 28, 2024</span><span><i class="fas fa-eye"></i> 1.2k views</span></div><h3>How Solar Energy is Powering Luxury Homes</h3><p>The integration of sustainable energy in modern luxury architecture...</p><a href="/blog/post.php?id=4" class="read-more">Read More →</a></div></div>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400&q=80" alt="Post"><span class="category-badge">Marketplace</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> April 20, 2024</span><span><i class="fas fa-eye"></i> 950 views</span></div><h3>Authenticating Luxury Goods: What to Know</h3><p>Tips for verifying the authenticity of high-end products...</p><a href="/blog/post.php?id=5" class="read-more">Read More →</a></div></div>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1547996160-81dfa63595aa?w=400&q=80" alt="Post"><span class="category-badge">Company News</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> April 15, 2024</span><span><i class="fas fa-eye"></i> 780 views</span></div><h3>Kinas Group Expands to New Markets</h3><p>Announcing our expansion into key African markets...</p><a href="/blog/post.php?id=6" class="read-more">Read More →</a></div></div>
        <div class="post-card"><div class="post-image"><img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400&q=80" alt="Post"><span class="category-badge">Buyer's Guides</span></div><div class="post-content"><div class="post-meta"><span><i class="fas fa-calendar"></i> April 8, 2024</span><span><i class="fas fa-eye"></i> 620 views</span></div><h3>First-Time Luxury Buyer: A Complete Guide</h3><p>Everything you need to know before making your first luxury purchase...</p><a href="/blog/post.php?id=7" class="read-more">Read More →</a></div></div>
    </div>

    <div class="pagination"><button class="page-btn active">1</button><button class="page-btn">2</button><button class="page-btn">3</button><button class="page-btn">Next →</button></div>
</div>

<div class="newsletter-section"><div class="newsletter-card"><h3>Subscribe to Our Newsletter</h3><p>Get the latest luxury insights and exclusive offers delivered to your inbox</p><form class="newsletter-form" id="newsletterForm"><input type="email" placeholder="Your email address"><button type="submit">Subscribe</button></form></div></div>

<script>
function searchBlog() { const q = document.getElementById('blogSearch').value; if(q.trim()) window.location.href = '/blog/search.php?q=' + encodeURIComponent(q); }
document.getElementById('blogSearch')?.addEventListener('keypress', function(e) { if(e.key === 'Enter') searchBlog(); });
document.getElementById('newsletterForm')?.addEventListener('submit', function(e) { e.preventDefault(); alert('Thank you for subscribing! Check your inbox for updates.'); this.reset(); });
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
BLOG_INDEX_EOF

echo "✅ Updated: blog/index.php"
