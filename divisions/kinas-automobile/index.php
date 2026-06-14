<?php
/**
 * KINAS AUTOMOBILE — Division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

// Active cars
$cars = $db->query("
    SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.mileage, c.transmission, c.fuel_type, c.status, c.featured,
           c.city, c.state, c.country, c.views, c.body_type, c.color,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM car_listings c
    LEFT JOIN users a ON c.agent_id = a.id
    WHERE c.status = 'active'
    ORDER BY c.featured DESC, c.created_at DESC
    LIMIT 12
")->fetchAll();

// Featured
$featured = array_filter($cars, fn($c) => !empty($c['featured']));

// Top brands
$brands = $db->query("
    SELECT brand, COUNT(*) as cnt FROM car_listings
    WHERE status='active' AND brand IS NOT NULL AND brand != ''
    GROUP BY brand ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$totalCars = (int)$db->query("SELECT COUNT(*) FROM car_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'KINAS AUTOMOBILE | Luxury Cars & Exotic Vehicles';
$pageDescription = 'Browse the world\'s finest luxury cars, supercars, and exotic vehicles from verified KINAS Automobile dealers.';

include '../../templates/header.php';
?>

<!-- Hero Carousel Styles -->
<style>
/* Hero Section with Rotating Backgrounds - preserving original positioning */
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

.hero-slides {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
}

.hero-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
}

@media (max-width: 768px) {
    .hero-slide {
        background-position: 65% center;
    }
}

@media (max-width: 480px) {
    .hero-slide {
        background-position: 70% center;
    }
}

.hero-slide.active {
    opacity: 1;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(10,10,10,0.5), rgba(0,0,0,0.7));
    z-index: 1;
}

/* Original content stays exactly as was - no extra wrappers */
.je-container {
    position: relative;
    z-index: 2;
}
</style>

<!-- ── Hero with Rotating Backgrounds ── -->
<section id="heroSection">
    <!-- Rotating Background Slides -->
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.pexels.com/photos/170811/pexels-photo-170811.jpeg?w=1920&q=80'); background-position: center 40%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.pexels.com/photos/919073/pexels-photo-919073.jpeg?w=1920&q=80'); background-position: center 35%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.pexels.com/photos/120049/pexels-photo-120049.jpeg?w=1920&q=80'); background-position: center 40%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=1920&fit=crop'); background-position: center 40%;"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <!-- ORIGINAL CONTENT - EXACTLY AS IT WAS, NO CHANGES TO STRUCTURE -->
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS AUTOMOBILE</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Finest Luxury &amp; Exotic Vehicles</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From supercars to grand tourers — discover <?= number_format($totalCars) ?>+ verified luxury vehicles from trusted dealers worldwide.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Inventory</a>
            <a href="search.php?sort=price_high" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Car Rentals</a>
        </div>
    </div>
</section>

<!-- ── Search strip ── -->
<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Search by make, model, keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            <select name="brand" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:160px;">
                <option value="">Any Brand</option>
                <?php foreach ($brands as $b): ?><option value="<?= htmlspecialchars($b['brand']) ?>"><?= htmlspecialchars($b['brand']) ?> (<?= (int)$b['cnt'] ?>)</option><?php endforeach; ?>
            </select>
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<!-- ── Featured grid ── -->
<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED COLLECTION</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Exceptional vehicles</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        $cards = array_map(function ($c) {
            $specParts = array_filter([$c['year'] ?? null, ($c['mileage'] ?? null) !== null ? number_format((int)$c['mileage']) . ' km' : null, $c['transmission'] ?? null, $c['fuel_type'] ?? null]);
            $locParts = array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country'] ?? null]);
            return [
                'id'         => $c['id'],
                'title'      => trim(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ' ' . ($c['year'] ?? '')),
                'price'      => $c['price'],
                'thumbnail'  => $c['thumbnail'] ?: '',
                'specs'      => implode(' • ', $specParts),
                'location'   => implode(', ', $locParts),
                'detail_url' => 'detail.php?id=' . (int)$c['id'],
                'featured'   => !empty($c['featured']),
                'verified'   => !empty($c['agent_verified']),
                'views'      => $c['views'] ?? 0,
            ];
        }, array_slice($cars, 0, 9));
        je_render_listing_grid($cards);
        ?>
    </div>
</section>

<!-- ── Browse by brand ── -->
<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">BROWSE BY MARQUE</div>
            <h2 style="font-family:'Prata',serif; font-size:32px;">World-renowned brands</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <?php foreach ($brands as $b): ?>
                <a href="search.php?brand=<?= urlencode($b['brand']) ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
                    <div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars($b['brand']) ?></div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$b['cnt'] ?> vehicles</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Why Kinas ── -->
<section style="padding:80px 0;">
    <div class="je-container">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:40px; text-align:center;">
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/verified-dealers-icon-120.png" srcset="/assets/images/trust/verified-dealers-icon-240.png 2x" alt="Verified Dealers" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Verified Dealers</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Every dealer on KINAS is identity-verified through our secure KYC partner.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/global-inventory-icon-120.png" srcset="/assets/images/trust/global-inventory-icon-240.png 2x" alt="Global Inventory" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Global Inventory</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Browse vehicles from dealers across 100+ countries, all in one place.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/secure-transactions-icon-120.png" srcset="/assets/images/trust/secure-transactions-icon-240.png 2x" alt="Secure Transactions" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Secure Transactions</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">End-to-end encrypted messaging and escrow-protected payments.</p>
            </div>
            <div>
                <div style="width:120px; height:120px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 24px rgba(0,0,0,0.12);"><img src="/assets/images/trust/concierge-service-icon-120.png" srcset="/assets/images/trust/concierge-service-icon-240.png 2x" alt="Concierge Service" width="120" height="120" loading="lazy" style="width:120px; height:120px; display:block;"></div>
                <h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Concierge Service</h3>
                <p style="font-size:13px; color:#666; line-height:1.6;">Our specialists can source specific vehicles on request.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA & Solar Calculator Button Section ── -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">List your vehicle with KINAS</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach an audience of qualified luxury buyers. Get verified in minutes.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become a Dealer</a>
        
        <!-- Solar Calculator Button -->
        <div style="margin-top: 50px; padding-top: 40px; border-top: 1px solid rgba(255,255,255,0.15);">
            <button id="openSolarEstimatorBtn" style="background: #2d6a4f; border: none; padding: 16px 40px; font-size: 1.2rem; font-weight: 700; color: white; border-radius: 60px; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 8px 18px rgba(0,0,0,0.3); display: inline-flex; align-items: center; gap: 12px;">
                🧮 Solar Calculator
            </button>
            <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 12px;">Estimate your solar savings — no server errors, instant results</p>
        </div>
    </div>
</section>

<!-- Solar Modal Styles (same as original) -->
<style>
    .solar-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 10000; visibility: hidden; opacity: 0; transition: visibility 0.2s, opacity 0.2s ease; }
    .solar-modal-overlay.active { visibility: visible; opacity: 1; }
    .solar-modal-container { background: #ffffff; max-width: 550px; width: 90%; border-radius: 1.5rem; box-shadow: 0 30px 40px rgba(0, 0, 0, 0.4); overflow: hidden; transform: scale(0.96); transition: transform 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1); }
    .solar-modal-overlay.active .solar-modal-container { transform: scale(1); }
    .solar-modal-header { background: #2d6a4f; color: white; padding: 1.2rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .solar-modal-header h3 { margin: 0; font-size: 1.4rem; font-weight: 600; }
    .solar-close-modal { background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; line-height: 1; transition: opacity 0.2s; }
    .solar-close-modal:hover { opacity: 0.7; }
    .solar-modal-body { padding: 1.8rem; max-height: 70vh; overflow-y: auto; }
    .solar-group { margin-bottom: 1.3rem; }
    .solar-group label { font-weight: 600; color: #1f3b2c; display: block; margin-bottom: 0.5rem; font-size: 0.85rem; }
    .solar-group input, .solar-group select { width: 100%; padding: 0.8rem 1rem; border: 1.5px solid #d0dfd4; border-radius: 1rem; font-size: 0.95rem; background: #fefcf7; transition: 0.2s; }
    .solar-group input:focus, .solar-group select:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.1); }
    .solar-calc-btn { background: #e67e22; color: white; border: none; padding: 0.9rem; font-weight: bold; border-radius: 2rem; width: 100%; cursor: pointer; margin-top: 0.5rem; font-size: 1rem; transition: background 0.2s; }
    .solar-calc-btn:hover { background: #cf711f; }
    .solar-results { margin-top: 1.5rem; background: #eef3ef; padding: 1.2rem; border-radius: 1rem; font-size: 0.85rem; }
    .solar-results p { margin: 0.5rem 0; }
    .solar-savings-highlight { font-size: 1.3rem; font-weight: 800; color: #e67e22; }
    .solar-disclaimer { font-size: 0.65rem; color: #5a6e5e; text-align: center; margin-top: 1rem; }
    hr { margin: 0.8rem 0; border: 0; height: 1px; background: #d4e2d9; }
</style>

<div id="solarModal" class="solar-modal-overlay">
    <div class="solar-modal-container">
        <div class="solar-modal-header">
            <h3>☀️ Solar Savings Estimator</h3>
            <button class="solar-close-modal" id="closeSolarModal">&times;</button>
        </div>
        <div class="solar-modal-body">
            <div class="solar-group">
                <label>🏠 Monthly electricity bill ($)</label>
                <input type="number" id="monthlyBill" value="135" step="10">
            </div>
            <div class="solar-group">
                <label>🔋 Solar system size (kWp)</label>
                <input type="number" id="systemSize" value="6.5" step="0.5">
            </div>
            <div class="solar-group">
                <label>☀️ Daily peak sun hours</label>
                <select id="sunHours">
                    <option value="3.5">Low (3.5h) - Cloudy regions</option>
                    <option value="4.5" selected>Average (4.5h) - Moderate climate</option>
                    <option value="5.5">High (5.5h) - Sunny states</option>
                    <option value="6.2">Very high (6.2h) - Desert/Southwest</option>
                </select>
            </div>
            <div class="solar-group">
                <label>💰 Installation cost ($ per watt)</label>
                <input type="number" id="costPerWatt" value="2.8" step="0.1">
                <small style="color: #5d7b65;">Typical: $2.50 - $3.50</small>
            </div>
            <button id="calculateSolarBtn" class="solar-calc-btn">📊 Calculate Savings</button>
            
            <div id="solarResultsArea" class="solar-results">
                <p><strong>💰 Annual Savings:</strong> <span id="annualSavings" class="solar-savings-highlight">--</span></p>
                <p><strong>📈 20-Year Net Savings:</strong> <span id="net20Savings">--</span></p>
                <p><strong>⏱️ Payback Period:</strong> <span id="paybackPeriod">--</span> years</p>
                <p><strong>🌿 CO₂ Offset:</strong> <span id="co2Offset">--</span> tons/year</p>
                <hr>
                <p style="font-size:0.7rem; color:#4a6b55;">✅ No server IP required — all calculations run locally.</p>
            </div>
            <div class="solar-disclaimer">
                *Estimates based on $0.16/kWh avg rate. Actual savings may vary.
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('solarModal');
    const openBtn = document.getElementById('openSolarEstimatorBtn');
    const closeBtn = document.getElementById('closeSolarModal');
    const calcBtn = document.getElementById('calculateSolarBtn');
    
    const billInput = document.getElementById('monthlyBill');
    const sizeInput = document.getElementById('systemSize');
    const sunSelect = document.getElementById('sunHours');
    const costInput = document.getElementById('costPerWatt');
    
    const annualSpan = document.getElementById('annualSavings');
    const net20Span = document.getElementById('net20Savings');
    const paybackSpan = document.getElementById('paybackPeriod');
    const co2Span = document.getElementById('co2Offset');
    
    function calculateSolar() {
        let bill = parseFloat(billInput.value) || 0;
        let systemKw = parseFloat(sizeInput.value) || 3;
        let sunHours = parseFloat(sunSelect.value) || 4.5;
        let costPerW = parseFloat(costInput.value) || 2.8;
        
        const RATE = 0.16;
        const PR = 0.85;
        const DEGRADATION = 0.005;
        const YEARS = 20;
        
        let annualProduction = systemKw * sunHours * 365 * PR;
        let currentAnnualCost = bill * 12;
        let annualConsumption = currentAnnualCost / RATE;
        let offsetRatio = Math.min(0.98, annualProduction / (annualConsumption || 8000));
        let year1Savings = currentAnnualCost * offsetRatio;
        
        let totalInstalledCost = systemKw * 1000 * costPerW;
        let payback = year1Savings > 0 ? totalInstalledCost / year1Savings : 0;
        payback = Math.min(30, Math.max(0, payback));
        
        let cumulative = 0;
        let prodFactor = 1;
        let annualCost = currentAnnualCost;
        for (let y = 1; y <= YEARS; y++) {
            if (y > 1) {
                annualCost *= 1.025;
                prodFactor *= (1 - DEGRADATION);
            }
            let adjOffset = Math.min(0.98, offsetRatio * prodFactor);
            cumulative += annualCost * adjOffset;
        }
        let netSavings = cumulative - totalInstalledCost;
        
        let co2Tons = (annualProduction * 0.85) / 2204.62;
        
        annualSpan.innerHTML = '$' + Math.round(year1Savings).toLocaleString() + '/year';
        net20Span.innerHTML = '$' + Math.round(netSavings).toLocaleString();
        paybackSpan.innerHTML = payback.toFixed(1);
        co2Span.innerHTML = co2Tons.toFixed(1);
    }
    
    function openModal() { modal.classList.add('active'); calculateSolar(); }
    function closeModal() { modal.classList.remove('active'); }
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (calcBtn) calcBtn.addEventListener('click', (e) => { e.preventDefault(); calculateSolar(); });
    
    if (modal) {
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    }
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.classList.contains('active')) closeModal();
    });
    
    calculateSolar();
    
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
