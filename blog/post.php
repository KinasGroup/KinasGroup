<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../templates/header.php';

$post_id = $_GET['id'] ?? 1;
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.post-container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; display: grid; grid-template-columns: 1fr 320px; gap: 50px; }
.post-header { margin-bottom: 40px; }
.post-category { display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 16px; }
.post-header h1 { font-family: 'Prata', serif; font-size: 36px; color: #0A0A0A; margin-bottom: 16px; line-height: 1.3; }
.post-meta { display: flex; gap: 20px; flex-wrap: wrap; font-size: 13px; color: #666; margin-bottom: 30px; }
.post-meta i { color: #C6A43F; margin-right: 6px; }
.post-featured-image { border-radius: 20px; overflow: hidden; margin-bottom: 30px; }
.post-featured-image img { width: 100%; height: auto; }
.post-content { font-size: 16px; line-height: 1.8; color: #333; }
.post-content h2 { font-family: 'Prata', serif; font-size: 24px; color: #C6A43F; margin: 32px 0 16px; }
.post-content p { margin-bottom: 20px; }
.post-content blockquote { margin: 30px 0; padding: 20px 30px; background: #F8F8F8; border-left: 4px solid #C6A43F; font-style: italic; }
.post-content blockquote p { margin-bottom: 10px; }
.post-share { margin: 40px 0; padding: 20px 0; border-top: 1px solid #E0E0E0; border-bottom: 1px solid #E0E0E0; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.share-links { display: flex; gap: 12px; }
.share-links a { width: 36px; height: 36px; background: #F8F8F8; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #C6A43F; transition: all 0.3s; }
.share-links a:hover { background: #C6A43F; color: #0A0A0A; }
.author-bio { display: flex; gap: 20px; padding: 24px; background: #F8F8F8; border-radius: 20px; margin: 40px 0; }
.author-avatar { width: 80px; height: 80px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: #0A0A0A; }
.author-info h4 { font-size: 18px; margin-bottom: 8px; }
.author-info p { color: #666; font-size: 14px; margin-bottom: 12px; }
.comments-section { margin-top: 40px; }
.comments-section h3 { font-size: 22px; margin-bottom: 24px; }
.comment { display: flex; gap: 16px; margin-bottom: 24px; }
.comment.reply { margin-left: 56px; }
.comment-avatar { width: 48px; height: 48px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0A0A0A; flex-shrink: 0; }
.comment-content { flex: 1; background: #F8F8F8; padding: 16px; border-radius: 16px; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
.comment-header strong { color: #C6A43F; }
.reply-btn { background: none; border: none; color: #C6A43F; font-size: 12px; cursor: pointer; margin-top: 8px; }
.leave-comment { margin-top: 40px; }
.leave-comment h4 { font-size: 18px; margin-bottom: 20px; }
.comment-form { display: flex; flex-direction: column; gap: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.comment-form input, .comment-form textarea { padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; }
.btn-submit { padding: 12px 24px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; width: fit-content; }
.sidebar-widget { background: white; border-radius: 20px; padding: 24px; border: 1px solid #E0E0E0; margin-bottom: 30px; }
.sidebar-widget h4 { font-size: 18px; color: #C6A43F; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.sidebar-search { display: flex; gap: 10px; }
.sidebar-search input { flex: 1; padding: 10px 14px; border: 1px solid #E0E0E0; border-radius: 10px; }
.sidebar-search button { padding: 10px 16px; background: #C6A43F; border: none; border-radius: 10px; cursor: pointer; }
.category-list, .recent-posts { list-style: none; }
.category-list li, .recent-posts li { margin-bottom: 12px; }
.category-list a, .recent-posts a { color: #333; text-decoration: none; display: flex; justify-content: space-between; transition: color 0.3s; }
.category-list a:hover, .recent-posts a:hover { color: #C6A43F; }
.recent-posts li span { display: block; font-size: 11px; color: #999; margin-top: 4px; }
.newsletter-widget form { display: flex; flex-direction: column; gap: 12px; }
.newsletter-widget input { padding: 12px; border: 1px solid #E0E0E0; border-radius: 10px; }
.newsletter-widget button { padding: 12px; background: #C6A43F; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
@media (max-width: 968px) { .post-container { grid-template-columns: 1fr; gap: 40px; } .post-header h1 { font-size: 28px; } .form-row { grid-template-columns: 1fr; gap: 12px; } .comment.reply { margin-left: 20px; } .author-bio { flex-direction: column; text-align: center; } .author-avatar { margin: 0 auto; } }
</style>

<div class="post-container">
    <article class="post-article">
        <header class="post-header"><div class="post-category">Automobile</div><h1>The Future of Luxury: Trends Shaping 2024</h1><div class="post-meta"><span><i class="fas fa-calendar"></i> May 15, 2024</span><span><i class="fas fa-user"></i> By John Smith</span><span><i class="fas fa-clock"></i> 8 min read</span><span><i class="fas fa-eye"></i> 2.3k views</span></div><div class="post-featured-image"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=80" alt="Featured"></div></header>
        <div class="post-content">
            <p>As we navigate through 2024, the luxury landscape is undergoing a remarkable transformation. From cutting-edge automotive innovations to sustainable real estate solutions, the definition of luxury is being redefined by technology, environmental consciousness, and personalized experiences.</p>
            <h2>The Rise of Sustainable Luxury</h2>
            <p>Today's discerning consumers are increasingly seeking products and services that align with their values. This shift has given rise to sustainable luxury - where premium quality meets environmental responsibility. Electric vehicles, eco-friendly building materials, and solar-integrated homes are no longer niche offerings but mainstream expectations in the luxury sector.</p>
            <blockquote><p>"The future belongs to those who can deliver exceptional quality while embracing innovation and sustainability."</p><cite>- Kinas Group Market Report 2024</cite></blockquote>
            <h2>Technology Integration in Premium Spaces</h2>
            <p>Smart home technology has evolved from a novelty to a necessity in luxury real estate. Properties now feature integrated systems that control lighting, climate, security, and entertainment through seamless interfaces.</p>
            <div class="post-share"><span>Share this article:</span><div class="share-links"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-linkedin-in"></i></a><a href="#"><i class="fab fa-whatsapp"></i></a></div></div>
            <div class="author-bio"><div class="author-avatar">JS</div><div class="author-info"><h4>John Smith</h4><p>Senior Market Analyst at Kinas Group with over 10 years of experience in luxury market research and trend forecasting.</p><div class="author-social"><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-linkedin"></i></a></div></div></div>
        </div>
        <div class="comments-section"><h3>Comments (3)</h3><div class="comments-list"><div class="comment"><div class="comment-avatar">JD</div><div class="comment-content"><div class="comment-header"><strong>John Doe</strong><span>May 16, 2024</span></div><p>Excellent article! Very insightful predictions for the luxury market.</p><button class="reply-btn">Reply</button></div></div><div class="comment reply"><div class="comment-avatar">JS</div><div class="comment-content"><div class="comment-header"><strong>Jane Smith</strong><span>May 16, 2024</span></div><p>I especially agree with the points about sustainable luxury. It's becoming a deciding factor for many buyers.</p><button class="reply-btn">Reply</button></div></div></div>
        <div class="leave-comment"><h4>Leave a Comment</h4><form class="comment-form"><div class="form-row"><input type="text" placeholder="Your Name" required><input type="email" placeholder="Your Email" required></div><textarea rows="4" placeholder="Your Comment" required></textarea><button type="submit" class="btn-submit">Post Comment</button></form></div></div>
    </article>
    <aside class="post-sidebar">
        <div class="sidebar-widget"><h4>Search</h4><div class="sidebar-search"><input type="text" placeholder="Search articles..."><button><i class="fas fa-search"></i></button></div></div>
        <div class="sidebar-widget"><h4>Categories</h4><ul class="category-list"><li><a href="#">Automobile <span>(12)</span></a></li><li><a href="#">Real Estate <span>(8)</span></a></li><li><a href="#">Solar Energy <span>(6)</span></a></li><li><a href="#">Marketplace <span>(10)</span></a></li><li><a href="#">Company News <span>(15)</span></a></li></ul></div>
        <div class="sidebar-widget"><h4>Recent Posts</h4><ul class="recent-posts"><li><a href="#">Top 10 Luxury Cars of 2024</a><span>May 10, 2024</span></li><li><a href="#">Investing in Premium Properties</a><span>May 5, 2024</span></li><li><a href="#">Solar Power in Luxury Homes</a><span>April 28, 2024</span></li></ul></div>
        <div class="sidebar-widget newsletter-widget"><h4>Newsletter</h4><p style="margin-bottom: 12px;">Get the latest luxury insights</p><form><input type="email" placeholder="Your email"><button type="submit">Subscribe</button></form></div>
    </aside>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
