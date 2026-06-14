<?php
/**
 * KINAS VOLT — Solar & Energy division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

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

<section id="heroSection" style="position:relative; height:70vh; min-height:480px; padding-top:90px; box-sizing:border-box; background:linear-gradient(135deg, rgba(10,40,20,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=2000&q=80') center/cover no-repeat; display:flex; align-items:center;">
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS VOLT</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Premium Solar &amp; Energy Solutions</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From residential rooftop systems to industrial installations — discover <?= number_format($totalSystems) ?>+ trusted solar solutions from verified providers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Systems</a>
            <a href="search.php?service_type=residential" class="je-btn je-btn-lg" style="background:transparent;border-color:rgba(255,255,255,0.3);color:#fff;">Residential</a>
            <!-- Solar Calculator Button -->
            <button type="button" id="openSolarCalcBtn" style="background:#2c7a47; border:none; color:#fff; font-family:Inter,sans-serif; font-weight:600; padding:0 24px; border-radius:40px; height:48px; cursor:pointer;">
                <i class="fas fa-calculator"></i> Solar Calculator
            </button>
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

<section style="padding:60px 0; background:#F8F6F1;">
    <div class="je-container">
        <div style="text-align:center; margin-bottom:40px;">
            <div style="font-size:11px; letter-spacing:2.5px; text-transform:uppercase; color:#C6A43F; margin-bottom:6px; font-weight:600;">BROWSE BY SERVICE</div>
            <h2 style="font-family:'Prata',serif; font-size:32px;">Find what you need</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px;">
            <?php foreach ($services as $s): ?>
                <a href="search.php?service_type=<?= urlencode($s['service_type']) ?>" style="background:#fff; border:1px solid #e8e8e8; padding:24px; text-align:center; border-radius:4px; text-decoration:none; transition:all 0.25s;">
                    <div style="font-family:'Prata',serif; font-size:16px; color:#0A0A0A; margin-bottom:4px;"><?= htmlspecialchars(ucfirst($s['service_type'])) ?></div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px;"><?= (int)$s['cnt'] ?> listings</div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding:80px 0;">
    <div class="je-container">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:40px; text-align:center;">
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-solar-panel"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Premium Hardware</h3><p style="font-size:13px; color:#666; line-height:1.6;">Only Tier-1 solar brands and components.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-tools"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Certified Installers</h3><p style="font-size:13px; color:#666; line-height:1.6;">Vetted installation professionals with proven track records.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-shield-alt"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Long Warranties</h3><p style="font-size:13px; color:#666; line-height:1.6;">Up to 25-year performance warranties on premium systems.</p></div>
            <div><div style="width:60px; height:60px; border-radius:50%; background:rgba(198,164,63,0.1); color:#C6A43F; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px;"><i class="fas fa-chart-line"></i></div><h3 style="font-family:'Prata',serif; font-size:17px; margin-bottom:8px;">Financing Available</h3><p style="font-size:13px; color:#666; line-height:1.6;">Flexible payment options for residential and commercial projects.</p></div>
        </div>
    </div>
</section>

<section style="background:#0A0A0A; padding:80px 0; text-align:center; color:#fff;">
    <div class="je-container">
        <h2 style="font-family:'Prata',serif; font-size:36px; margin-bottom:14px;">Power the future with KINAS Volt</h2>
        <p style="color:rgba(255,255,255,0.7); font-size:15px; max-width:560px; margin:0 auto 28px;">List your solar services and reach customers ready to switch.</p>
        <a href="/auth/register.php" class="je-btn je-btn-gold je-btn-lg">List Your Services</a>
    </div>
</section>

<!-- ========== STANDALONE SOLAR CALCULATOR LIGHTBOX ========== -->
<style>
  /* Lightbox CSS - completely independent */
  .solar-calc-modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.85);
    z-index: 10000;
    justify-content: center;
    align-items: center;
  }
  .solar-calc-modal.show { display: flex; }
  .solar-calc-modal-content {
    background: #fff;
    width: 90%;
    max-width: 1200px;
    height: 85%;
    border-radius: 16px;
    position: relative;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
  }
  .solar-calc-modal-header {
    padding: 16px 20px;
    background: #0A0A0A;
    color: #fff;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .solar-calc-modal-header h3 {
    margin: 0;
    font-family: 'Prata', serif;
    font-size: 1.2rem;
  }
  .solar-calc-close {
    background: none;
    border: none;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
  }
  .solar-calc-body {
    flex: 1;
    position: relative;
    background: #f5f5f5;
  }
  .solar-calc-body iframe {
    width: 100%;
    height: 100%;
    border: none;
  }
  .solar-calc-footer {
    padding: 12px 20px;
    background: #F8F6F1;
    font-size: 12px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    border-radius: 0 0 16px 16px;
    border-top: 1px solid #e0e0e0;
  }
  .calc-loader {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
  }
  .calc-spinner {
    width: 40px; height: 40px;
    border: 4px solid #e0e0e0;
    border-top-color: #2c7a47;
    border-radius: 50%;
    animation: calc-spin 1s linear infinite;
    margin: 0 auto;
  }
  @keyframes calc-spin { to { transform: rotate(360deg); } }
  @media (max-width: 768px) {
    .solar-calc-modal-content { width: 95%; height: 90%; }
    .solar-calc-modal-header h3 { font-size: 1rem; }
  }
</style>

<div id="solarCalcModal" class="solar-calc-modal">
  <div class="solar-calc-modal-content">
    <div class="solar-calc-modal-header">
      <h3><i class="fas fa-sun" style="color:#C6A43F;"></i> Solar Savings Estimator</h3>
      <button class="solar-calc-close" id="closeSolarCalcBtn">&times;</button>
    </div>
    <div class="solar-calc-body">
      <div id="calcLoader" class="calc-loader">
        <div class="calc-spinner"></div>
        <p style="margin-top:12px; color:#666;">Loading official NREL PVWatts® calculator...</p>
      </div>
      <iframe id="pvWattsFrame" src="https://pvwatts.nrel.gov/index.php" title="NREL Solar Calculator" allow="geolocation"></iframe>
    </div>
    <div class="solar-calc-footer">
      <span><i class="fas fa-chart-line"></i> Powered by <strong>NREL PVWatts®</strong> — U.S. government solar data (accuracy ±10%)</span>
      <a href="https://pvwatts.nrel.gov/" target="_blank" style="color:#2c7a47; text-decoration:none;">Open in new tab →</a>
    </div>
  </div>
</div>

<script>
  (function() {
    const modal = document.getElementById('solarCalcModal');
    const openBtn = document.getElementById('openSolarCalcBtn');
    const closeBtn = document.getElementById('closeSolarCalcBtn');
    const iframe = document.getElementById('pvWattsFrame');
    const loader = document.getElementById('calcLoader');
    
    function openModal() {
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
      // Reset iframe and show loader
      iframe.src = iframe.src;
      loader.style.display = 'block';
      iframe.style.opacity = '0';
    }
    
    function closeModal() {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    }
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    
    // Close when clicking outside the white content area
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeModal();
    });
    
    // When iframe loads, hide loader and show iframe
    iframe.addEventListener('load', function() {
      loader.style.display = 'none';
      iframe.style.opacity = '1';
    });
    
    // Fallback after 12 seconds
    setTimeout(function() {
      if (iframe.style.opacity !== '1') {
        loader.innerHTML = '<div style="color:#c0392b;"><i class="fas fa-exclamation-triangle" style="font-size:24px;"></i><p>Unable to load calculator. <a href="https://pvwatts.nrel.gov/" target="_blank">Click here to open PVWatts in a new tab</a>.</p></div>';
      }
    }, 12000);
  })();
</script>
<!-- ========== END SOLAR CALCULATOR ========== -->

<?php include '../../templates/footer.php'; ?>
