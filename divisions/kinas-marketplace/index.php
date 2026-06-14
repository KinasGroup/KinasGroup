<?php
/**
 * KINAS MARKETPLACE — Curated goods division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

$items = $db->query("
    SELECT m.id, m.title, m.category_id, m.price, m.brand, m.condition_status, m.featured, m.views,
           m.city, m.state, m.country, c.name AS category_name, c.slug AS category_slug,
           a.verified AS agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM marketplace_listings m
    LEFT JOIN marketplace_categories c ON m.category_id = c.id
    LEFT JOIN users a ON m.agent_id = a.id
    WHERE m.status = 'active'
    ORDER BY m.featured DESC, m.created_at DESC
    LIMIT 12
")->fetchAll();

$categories = $db->query("
    SELECT id, name, slug, (SELECT COUNT(*) FROM marketplace_listings ml WHERE ml.category_id = marketplace_categories.id AND ml.status='active') AS cnt
    FROM marketplace_categories
    ORDER BY name
")->fetchAll();

$totalItems = (int)$db->query("SELECT COUNT(*) FROM marketplace_listings WHERE status='active'")->fetchColumn();

$pageTitle = 'KINAS MARKETPLACE | Curated Luxury Goods';
$pageDescription = 'Watches, jewelry, art, fashion and other curated luxury goods from verified KINAS sellers.';
include '../../templates/header.php';
?>

<!-- Hero Carousel Styles -->
<style>
/* Hero Section with Rotating Backgrounds - Preserving original positioning */
.hero-section {
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

/* Mobile-specific background positioning */
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
    background: linear-gradient(135deg, rgba(40,20,40,0.5), rgba(0,0,0,0.7));
    z-index: 1;
}

/* Original content styling - exactly preserved */
.hero-content {
    position: relative;
    z-index: 2;
    width: 100%;
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

/* Scrollbar styling */
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

<!-- HERO SECTION WITH ROTATING BACKGROUNDS - ORIGINAL STRUCTURE PRESERVED -->
<section id="heroSection" style="position:relative; height:70vh; min-height:480px; padding-top:90px; box-sizing:border-box; display:flex; align-items:center; overflow:hidden;">
    <!-- Rotating Background Slides -->
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=1920&q=80'); background-position: center 35%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1920&q=80'); background-position: center 30%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1585386959984-a4155221c2c9?w=1920&q=80'); background-position: center 25%;"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1537832816519-689ad163238b?w=1920&q=80'); background-position: center 40%;"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <!-- ORIGINAL CONTENT - EXACTLY AS IT WAS, NO CHANGES -->
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS MARKETPLACE</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Curated Luxury Goods</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">Watches, jewelry, art, fashion and rare collectibles — <?= number_format($totalItems) ?>+ authenticated pieces from verified sellers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Items</a>
            <a href="search.php?sort=price_high" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Most Expensive</a>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:24px 0;">
    <div class="je-container">
        <form method="GET" action="search.php" id="searchForm" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" placeholder="Brand, item, category…" style="flex:1; min-width:240px; padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px;">
            
            <!-- Custom Dropdown for Categories -->
            <div class="custom-dropdown" id="categoryDropdown">
                <div class="custom-dropdown-toggle">
                    <span id="selectedCategoryText">Any Category</span>
                    <span class="arrow">▼</span>
                </div>
                <div class="custom-dropdown-menu">
                    <div class="custom-dropdown-item" data-value="" data-count="<?= $totalItems ?>">
                        <span>Any Category</span>
                        <span class="count"><?= $totalItems ?></span>
                    </div>
                    <?php foreach ($categories as $c): ?>
                        <div class="custom-dropdown-item" data-value="<?= (int)$c['id'] ?>" data-count="<?= (int)$c['cnt'] ?>">
                            <span><?= htmlspecialchars($c['name']) ?></span>
                            <span class="count"><?= (int)$c['cnt'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Hidden input to store selected category value -->
            <input type="hidden" name="category" id="categoryInput" value="">
            
            <button type="submit" class="je-btn je-btn-gold"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</section>

<section style="padding:60px 0;">
    <div class="je-container">
        <div class="je-flex-between" style="margin-bottom:32px;">
            <div>
                <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">FEATURED ITEMS</div>
                <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A;">Exceptional pieces</h2>
            </div>
            <a href="search.php" class="je-btn je-btn-outline">View all <i class="fas fa-arrow-right"></i></a>
        </div>

        <?php
        if (empty($items)) {
            echo '<div style="text-align:center; padding:60px 20px; background:#fafafa; border-radius:8px;">
                    <div style="font-size:48px; margin-bottom:16px;">🏛️</div>
                    <h3 style="font-family:\'Prata\',serif; font-size:20px; margin-bottom:8px;">No listings found</h3>
                    <p style="color:#666;">Try adjusting your filters or check back soon.</p>
                  </div>';
        } else {
            $cards = array_map(function ($r) {
                $specParts = array_filter([$r['category_name'] ?? null, $r['brand'] ?? null, $r['condition_status'] ?? null]);
                $locParts = array_filter([$r['city'] ?? null, $r['state'] ?? null, $r['country'] ?? null]);
                return [
                    'id' => $r['id'], 'title' => $r['title'] ?? '',
                    'price' => $r['price'], 'thumbnail' => $r['thumbnail'] ?: '',
                    'specs' => implode(' • ', array_map('ucfirst', $specParts)),
                    'location' => implode(', ', $locParts),
                    'detail_url' => 'detail.php?id=' . (int)$r['id'],
                    'featured' => !empty($r['featured']),
                    'verified' => !empty($r['agent_verified']),
                    'views' => $r['views'] ?? 0,
                ];
            }, array_slice($items, 0, 9));
            je_render_listing_grid($cards);
        }
        ?>
    </div>
</section>

<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">BROWSE BY CATEGORY</div>
            <h2 style="font-family:'Prata',serif; font-size:32px;">Find what you're looking for</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <?php foreach ($categories as $c): ?>
                <a href="search.php?category=<?= (int)$c['id'] ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
                    <div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars($c['name']) ?></div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$c['cnt'] ?> items</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding:80px 0;">
    <div class="je-container">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:40px; text-align:center;">
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-certificate"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Authenticated</h3><p style="font-size:13px; color:#666; line-height:1.6;">Every item is verified for authenticity before listing.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-lock"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Secure Payments</h3><p style="font-size:13px; color:#666; line-height:1.6;">Escrow-protected transactions for peace of mind.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-truck"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">White-Glove Shipping</h3><p style="font-size:13px; color:#666; line-height:1.6;">Insured, door-to-door delivery for high-value items.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-undo"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">14-Day Returns</h3><p style="font-size:13px; color:#666; line-height:1.6;">Buyer protection with hassle-free returns on eligible items.</p></div>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">Sell on KINAS Marketplace</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">Reach a global audience of luxury collectors and enthusiasts.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">Become a Seller</a>
    </div>
</section>

<!-- Carousel & Dropdown JavaScript -->
<script>
// ============================================
// ROTATING HERO BACKGROUND (same as home page)
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
    const dropdown = document.getElementById('categoryDropdown');
    if (!dropdown) return;
    
    const toggle = dropdown.querySelector('.custom-dropdown-toggle');
    const items = dropdown.querySelectorAll('.custom-dropdown-item');
    const selectedText = document.getElementById('selectedCategoryText');
    const categoryInput = document.getElementById('categoryInput');
    
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
            categoryInput.value = value;
            
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
