<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../templates/header.php';

$query = $_GET['q'] ?? '';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.search-hero { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); padding: 60px 20px; text-align: center; }
.search-hero h1 { font-family: 'Prata', serif; font-size: 42px; color: white; margin-bottom: 24px; }
.search-form { max-width: 500px; margin: 0 auto; display: flex; gap: 12px; }
.search-form input { flex: 1; padding: 14px 20px; border: 1px solid #333; background: #1A1A1A; border-radius: 40px; color: white; font-family: 'Inter', sans-serif; }
.search-form button { padding: 14px 28px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.container { max-width: 800px; margin: 0 auto; padding: 60px 20px; }
.results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 16px; border-bottom: 1px solid #E0E0E0; }
.results-header p { color: #666; }
.results-header strong { color: #C6A43F; }
.results-count { color: #C6A43F; font-size: 14px; }
.search-results { display: flex; flex-direction: column; gap: 20px; }
.result-item { background: white; border-radius: 16px; padding: 24px; border: 1px solid #E0E0E0; transition: all 0.3s; }
.result-item:hover { transform: translateX(5px); border-color: #C6A43F; }
.result-item a { text-decoration: none; }
.result-item h3 { font-size: 18px; font-weight: 700; color: #C6A43F; margin-bottom: 12px; }
.result-item p { font-size: 14px; color: #666; margin-bottom: 12px; line-height: 1.5; }
.result-meta { font-size: 12px; color: #999; }
.no-results, .no-query { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 1px solid #E0E0E0; }
.no-results i, .no-query i { font-size: 48px; color: #C6A43F; margin-bottom: 20px; }
.no-results h3, .no-query h3 { font-size: 24px; margin-bottom: 12px; color: #0A0A0A; }
.btn-browse { display: inline-block; padding: 12px 28px; background: #C6A43F; border-radius: 40px; color: #0A0A0A; text-decoration: none; font-weight: 600; margin-top: 20px; }
@media (max-width: 768px) { .search-hero h1 { font-size: 32px; } .search-form { flex-direction: column; padding: 0 20px; } .results-header { flex-direction: column; text-align: center; gap: 8px; } }
</style>

<div class="search-hero"><h1>Search Results</h1><div class="search-form"><input type="text" id="searchInput" placeholder="Search articles..." value="<?php echo htmlspecialchars($query); ?>"><button id="searchBtn"><i class="fas fa-search"></i> Search</button></div></div>

<div class="container">
    <?php if ($query): ?>
        <div class="results-header"><p>Showing results for: <strong>"<?php echo htmlspecialchars($query); ?>"</strong></p><span class="results-count">Found 8 articles</span></div>
        <div class="search-results">
            <div class="result-item"><a href="/blog/post.php?id=1"><h3>The Future of Luxury: Trends Shaping 2024</h3><p>Explore the emerging trends in luxury automobiles, real estate, and sustainable energy...</p><span class="result-meta">May 15, 2024 · Automobile</span></a></div>
            <div class="result-item"><a href="/blog/post.php?id=2"><h3>Top 10 Luxury Cars of 2024</h3><p>Discover the most anticipated luxury vehicles hitting the market this year...</p><span class="result-meta">May 10, 2024 · Automobile</span></a></div>
            <div class="result-item"><a href="/blog/post.php?id=3"><h3>Investing in Premium Properties: A Guide</h3><p>Key factors to consider when investing in high-end real estate...</p><span class="result-meta">May 5, 2024 · Real Estate</span></a></div>
            <div class="result-item"><a href="/blog/post.php?id=4"><h3>How Solar Energy is Powering Luxury Homes</h3><p>The integration of sustainable energy in modern luxury architecture...</p><span class="result-meta">April 28, 2024 · Solar Energy</span></a></div>
        </div>
    <?php else: ?>
        <div class="no-query"><i class="fas fa-search"></i><h3>Enter a search term</h3><p>Search for articles, news, or guides</p></div>
    <?php endif; ?>
</div>

<script>
document.getElementById('searchBtn')?.addEventListener('click', function() { const q = document.getElementById('searchInput').value; if(q.trim()) window.location.href = '/blog/search.php?q=' + encodeURIComponent(q); });
document.getElementById('searchInput')?.addEventListener('keypress', function(e) { if(e.key === 'Enter') { const q = this.value; if(q.trim()) window.location.href = '/blog/search.php?q=' + encodeURIComponent(q); } });
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
