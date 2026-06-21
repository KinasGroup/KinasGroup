<?php
/**
 * KINAS VOLT — Solar & Energy division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
require_once '../../includes/email.php';

$db = Database::getInstance()->getConnection();

$db->exec("
    CREATE TABLE IF NOT EXISTS solar_enquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        monthly_bill DECIMAL(10,2) NOT NULL,
        system_size DECIMAL(10,2) NOT NULL,
        annual_savings DECIMAL(12,2) NOT NULL,
        payback_years DECIMAL(5,2) NOT NULL,
        status ENUM('new', 'contacted', 'converted') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$systems = $db->query("
    SELECT s.id, s.title, s.service_type, s.price, s.brand, s.capacity_kw, s.warranty_years, s.views,
           s.city, s.state, s.country,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM solar_listings s
    LEFT JOIN users a ON s.agent_id = a.id
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 12
")->fetchAll();

$services = $db->query("
    SELECT service_type, COUNT(*) AS cnt FROM solar_listings
    WHERE status='active' AND service_type IS NOT NULL
    GROUP BY service_type ORDER BY cnt DESC
")->fetchAll();

$totalSystems = (int)$db->query("SELECT COUNT(*) FROM solar_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'KINAS VOLT | Solar & Energy Solutions';
$pageDescription = 'Premium solar panels, inverters, batteries, and energy services from verified KINAS Volt providers.';
include '../../templates/header.php';
?>

<!-- Hero Carousel Styles -->
<style>
#heroSection {
    position: relative;
    height: 70vh;
    min-height: 480px;
    padding-top: 90px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    overflow: hidden;
}
.hero-slides { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
.hero-slide {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background-size: cover; background-position: center;
    opacity: 0; transition: opacity 1.5s ease-in-out;
}
@media (max-width: 768px) { .hero-slide { background-position: 65% center; } }
@media (max-width: 480px) { .hero-slide { background-position: 70% center; } }
.hero-slide.active { opacity: 1; }
.hero-overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: linear-gradient(135deg, rgba(10,40,20,0.5), rgba(0,0,0,0.7));
    z-index: 1;
}
.je-container { position: relative; z-index: 2; }

/* Solar Calculator Button Style */
.solar-calculator-green-btn {
    background: #2c7a47;
    border: none;
    color: #fff;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    padding: 0 28px;
    border-radius: 40px;
    height: 48px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    text-decoration: none;
}
.solar-calculator-green-btn:hover {
    background: #1e5a36;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    color: #fff;
}

/* Feature Cards with Photo-Realistic Images */
.feature-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s ease;
    cursor: default;
    min-height: 280px;
    display: flex;
    align-items: flex-end;
}
.feature-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.15);
}
.feature-card .feature-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s ease;
}
.feature-card:hover .feature-bg {
    transform: scale(1.05);
}
.feature-card .feature-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);
}
.feature-card .feature-content {
    position: relative;
    z-index: 2;
    padding: 32px 28px 28px;
    color: #fff;
    width: 100%;
}
.feature-card .feature-content h3 {
    font-family: 'Prata', serif;
    font-size: 22px;
    margin-bottom: 8px;
    font-weight: 400;
}
.feature-card .feature-content p {
    font-size: 14px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 12px;
    line-height: 1.5;
}
</style>

<!-- Hero with Rotating Backgrounds -->
<section id="heroSection">
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=1920&q=80'); background-position: center 30%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1613665813446-82a78c468a1d?w=1920&q=80'); background-position: center 25%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1611365892117-00ac5ef43c90?w=1920&h=1080&fit=crop'); background-position: center 30%;"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS VOLT</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Premium Solar &amp; Energy Solutions</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From residential rooftop systems to industrial installations — discover <?= number_format($totalSystems) ?>+ trusted solar solutions from verified providers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Systems</a>
            <a href="/divisions/kinas-volt/calculator.php" class="solar-calculator-green-btn">
                <i class="fas fa-calculator"></i> Solar Calculator
            </a>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Brand, system type, keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            <select name="service_type" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:160px;">
                <option value="">Any Service</option>
                <?php foreach ($services as $s): ?><option value="<?= htmlspecialchars($s['service_type']) ?>"><?= htmlspecialchars(ucfirst($s['service_type'])) ?> (<?= (int)$s['cnt'] ?>)</option><?php endforeach; ?>
            </select>
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED SYSTEMS</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Reliable energy solutions</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        $cards = array_map(function ($s) {
            $specParts = array_filter([$s['service_type'] ?? null, ($s['capacity_kw'] ?? null) !== null ? rtrim(rtrim(number_format((float)$s['capacity_kw'], 2), '0'), '.') . ' kW' : null, ($s['warranty_years'] ?? null) !== null ? $s['warranty_years'] . '-yr warranty' : null, $s['brand'] ?? null]);
            $locParts = array_filter([$s['city'] ?? null, $s['state'] ?? null, $s['country'] ?? null]);
            return [
                'id' => $s['id'], 'title' => $s['title'] ?? '',
                'price' => $s['price'], 'thumbnail' => $s['thumbnail'] ?: '',
                'specs' => implode(' • ', array_map('ucfirst', $specParts)),
                'location' => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$s['id'],
                'featured' => false, 'verified' => !empty($s['agent_verified']),
                'views' => $s['views'] ?? 0,
            ];
        }, array_slice($systems, 0, 9));
        je_render_listing_grid($cards);
        ?>
    </div>
</section>

<!-- Why Kinas Volt - Photo-Realistic Feature Cards -->
<section style="padding:80px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:48px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">WHY KINAS VOLT</div>
            <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Powering a sustainable future</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
            
            <!-- Premium Hardware -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.unsplash.com/photo-1613665813446-82a78c468a1d?w=600&q=80');"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Premium Hardware</h3>
                    <p>Only Tier-1 solar brands and components, engineered for maximum efficiency and longevity.</p>
                </div>
            </div>
            
            <!-- Certified Installers -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.unsplash.com/photo-1581094794322-c7c5c9a0ee65?w=600&q=80');"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Certified Installers</h3>
                    <p>Vetted installation professionals with proven track records and industry certifications.</p>
                </div>
            </div>
            
            <!-- Long Warranties -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.unsplash.com/photo-1581091226033-d5d7e5f3c6b2?w=600&q=80');"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Long Warranties</h3>
                    <p>Up to 25-year performance warranties on premium systems, giving you peace of mind.</p>
                </div>
            </div>
            
            <!-- Financing Available -->
            <div class="feature-card">
                <div class="feature-bg" style="background-image: url('https://images.unsplash.com/photo-1563013544-824ae1b704d3?w=600&q=80');"></div>
                <div class="feature-overlay"></div>
                <div class="feature-content">
                    <h3>Financing Available</h3>
                    <p>Flexible payment options for residential and commercial projects. Get started today.</p>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">Power the future with KINAS Volt</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Discover our range of premium solar products and solutions.</p>
        <a href="search.php" class="je-btn je-btn-gold je-btn-lg">Explore Our Products</a>
    </div>
</section>

<script>
(function() {
    // ============================================
    // ROTATING HERO BACKGROUND
    // ============================================
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    const totalSlides = slides.length;
    
    function rotateHeroBackground() {
        if (totalSlides > 1) {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % totalSlides;
            slides[currentSlide].classList.add('active');
        }
    }
    
    if (totalSlides > 1) {
        setInterval(rotateHeroBackground, 6000);
    }
})();
</script>

<?php include '../../templates/footer.php'; ?>
