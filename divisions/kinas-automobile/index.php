cat > divisions/kinas-automobile/index.php << 'ENDOFFILE'
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

.je-container {
    position: relative;
    z-index: 2;
}

/* Custom Dropdown Styles */
.custom-dropdown {
    position: relative;
    display: inline-block;
    min-width: 180px;
    font-family: 'Inter', sans-serif;
}

.custom-dropdown-toggle {
    padding: 14px 18px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 3px;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.custom-dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

.custom-dropdown-toggle .arrow {
    font-size: 12px;
    transition: transform 0.2s ease;
}

.custom-dropdown.open .custom-dropdown-toggle .arrow {
    transform: rotate(180deg);
}

.custom-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #1a1a1a;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 4px;
    margin-top: 4px;
    max-height: 280px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.custom-dropdown.open .custom-dropdown-menu {
    display: block;
}

.custom-dropdown-item {
    padding: 12px 18px;
    color: #e0e0e0;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.custom-dropdown-item:hover {
    background: rgba(198, 164, 63, 0.15);
    color: #C6A43F;
}

.custom-dropdown-item.selected {
    background: rgba(198, 164, 63, 0.25);
    color: #C6A43F;
    font-weight: 500;
}

.custom-dropdown-item .count {
    font-size: 11px;
    color: #888;
    background: rgba(255, 255, 255, 0.08);
    padding: 2px 8px;
    border-radius: 12px;
}

.custom-dropdown-item:hover .count {
    background: rgba(198, 164, 63, 0.2);
    color: #C6A43F;
}

.custom-dropdown-menu::-webkit-scrollbar {
    width: 6px;
}

.custom-dropdown-menu::-webkit-scrollbar-track {
    background: #2a2a2a;
    border-radius: 3px;
}

.custom-dropdown-menu::-webkit-scrollbar-thumb {
    background: #C6A43F;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .custom-dropdown {
        width: 100%;
    }
    .custom-dropdown-menu {
        max-height: 240px;
    }
}
</style>

<!-- Hero with Rotating Backgrounds -->
<section id="heroSection">
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.pexels.com/photos/170811/pexels-photo-170811.jpeg?w=1920&q=80'); background-position: center 40%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.pexels.com/photos/919073/pexels-photo-919073.jpeg?w=1920&q=80'); background-position: center 35%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.pexels.com/photos/120049/pexels-photo-120049.jpeg?w=1920&q=80'); background-position: center 40%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=1920&fit=crop'); background-position: center 40%;"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS AUTOMOBILE</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Finest Luxury &amp; Exotic Vehicles</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From supercars to grand tourers — discover <?= number_format($totalCars) ?>+ verified luxury vehicles from trusted dealers worldwide.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Inventory</a>
            <a href="rental-search.php?sort=price_high" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Car Rentals</a>
        </div>
    </div>
</section>

<!-- Search strip with custom dropdown -->
<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Search by make, model, keyword…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            
            <!-- Custom Dropdown for Brands -->
            <div class="custom-dropdown" id="brandDropdown">
                <div class="custom-dropdown-toggle">
                    <span id="selectedBrandText">Any Brand</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="custom-dropdown-menu">
                    <div class="custom-dropdown-item" data-value="" data-count="<?= $totalCars ?>">
                        <span>Any Brand</span>
                        <span class="count"><?= $totalCars ?></span>
                    </div>
                    <?php foreach ($brands as $b): ?>
                        <div class="custom-dropdown-item" data-value="<?= htmlspecialchars($b['brand']) ?>" data-count="<?= (int)$b['cnt'] ?>">
                            <span><?= htmlspecialchars($b['brand']) ?></span>
                            <span class="count"><?= (int)$b['cnt'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <input type="hidden" name="brand" id="brandInput" value="">
            
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<!-- Featured grid -->
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

<!-- Browse by brand -->
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

<!-- Why Kinas -->
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

<!-- CTA Section -->
<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">List your vehicle with KINAS</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach an audience of qualified luxury buyers. Get verified in minutes.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become a Dealer</a>
    </div>
</section>

<script>
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

// ============================================
// CUSTOM DROPDOWN FUNCTIONALITY
// ============================================
(function() {
    const dropdown = document.getElementById('brandDropdown');
    if (!dropdown) return;
    
    const toggle = dropdown.querySelector('.custom-dropdown-toggle');
    const items = dropdown.querySelectorAll('.custom-dropdown-item');
    const selectedText = document.getElementById('selectedBrandText');
    const brandInput = document.getElementById('brandInput');
    
    let isOpen = false;
    
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        if (isOpen) {
            dropdown.classList.add('open');
            document.addEventListener('click', closeDropdownOnClickOutside);
        } else {
            dropdown.classList.remove('open');
            document.removeEventListener('click', closeDropdownOnClickOutside);
        }
    });
    
    items.forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            const text = this.querySelector('span:first-child').innerText;
            
            selectedText.innerHTML = text;
            brandInput.value = value;
            
            items.forEach(function(i) {
                i.classList.remove('selected');
            });
            this.classList.add('selected');
            
            isOpen = false;
            dropdown.classList.remove('open');
            document.removeEventListener('click', closeDropdownOnClickOutside);
        });
    });
    
    function closeDropdownOnClickOutside(e) {
        if (!dropdown.contains(e.target)) {
            isOpen = false;
            dropdown.classList.remove('open');
            document.removeEventListener('click', closeDropdownOnClickOutside);
        }
    }
    
    const defaultItem = dropdown.querySelector('.custom-dropdown-item[data-value=""]');
    if (defaultItem) {
        defaultItem.classList.add('selected');
    }
})();
</script>

<?php include '../../templates/footer.php'; ?>
ENDOFFILE
