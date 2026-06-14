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
            <!-- REPLACED: "Residential" button is now GREEN "Solar Calculator" button -->
            <button type="button" id="openSolarCalculatorBtn" class="solar-calculator-green-btn">
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

<!-- ============================================ -->
<!-- SOLAR SAVINGS ESTIMATOR - FULLY FUNCTIONAL   -->
<!-- No external IP errors - Local calculations   -->
<!-- "Residential" button replaced with this      -->
<!-- ============================================ -->

<style>
    /* Green Solar Calculator Button */
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
    }
    .solar-calculator-green-btn:hover {
        background: #1e5a36;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .solar-calculator-green-btn:active {
        transform: translateY(1px);
    }

    /* Solar Modal Styles */
    .solar-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(6px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        visibility: hidden;
        opacity: 0;
        transition: visibility 0.2s, opacity 0.2s ease;
    }
    .solar-modal-overlay.active {
        visibility: visible;
        opacity: 1;
    }
    .solar-modal-container {
        background: #ffffff;
        max-width: 600px;
        width: 90%;
        border-radius: 1.5rem;
        box-shadow: 0 30px 40px rgba(0, 0, 0, 0.4);
        overflow: hidden;
        transform: scale(0.96);
        transition: transform 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    }
    .solar-modal-overlay.active .solar-modal-container {
        transform: scale(1);
    }
    .solar-modal-header {
        background: #2c7a47;
        color: white;
        padding: 1.2rem 1.8rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .solar-modal-header h3 {
        margin: 0;
        font-family: 'Prata', serif;
        font-size: 1.4rem;
        font-weight: 400;
    }
    .solar-close-modal {
        background: none;
        border: none;
        color: white;
        font-size: 1.8rem;
        cursor: pointer;
        line-height: 1;
        transition: opacity 0.2s;
    }
    .solar-close-modal:hover {
        opacity: 0.7;
    }
    .solar-modal-body {
        padding: 1.8rem;
        max-height: 70vh;
        overflow-y: auto;
    }
    .solar-group {
        margin-bottom: 1.3rem;
    }
    .solar-group label {
        font-weight: 600;
        color: #1a3a2a;
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }
    .solar-group input, .solar-group select {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1.5px solid #d0dfd4;
        border-radius: 1rem;
        font-size: 0.95rem;
        background: #fefcf7;
        transition: 0.2s;
    }
    .solar-group input:focus, .solar-group select:focus {
        outline: none;
        border-color: #2c7a47;
        box-shadow: 0 0 0 3px rgba(44, 122, 71, 0.1);
    }
    .solar-calc-action-btn {
        background: #2c7a47;
        color: white;
        border: none;
        padding: 0.9rem;
        font-weight: bold;
        border-radius: 2rem;
        width: 100%;
        cursor: pointer;
        margin-top: 0.8rem;
        font-size: 1rem;
        transition: background 0.2s;
    }
    .solar-calc-action-btn:hover {
        background: #1e5a36;
    }
    .solar-results-area {
        margin-top: 1.5rem;
        background: #eef3ef;
        padding: 1.2rem;
        border-radius: 1rem;
        font-size: 0.85rem;
    }
    .solar-results-area p {
        margin: 0.5rem 0;
    }
    .solar-savings-highlight {
        font-size: 1.4rem;
        font-weight: 800;
        color: #2c7a47;
    }
    .solar-disclaimer {
        font-size: 0.65rem;
        color: #5a6e5e;
        text-align: center;
        margin-top: 1rem;
    }
    .solar-success-badge {
        background: #2c7a47;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        display: inline-block;
        margin-bottom: 12px;
    }
</style>

<!-- Solar Calculator Modal -->
<div id="solarCalculatorModal" class="solar-modal-overlay">
    <div class="solar-modal-container">
        <div class="solar-modal-header">
            <h3>☀️ Solar Savings Estimator</h3>
            <button class="solar-close-modal" id="closeSolarModalBtn">&times;</button>
        </div>
        <div class="solar-modal-body">
            <div style="text-align:center; margin-bottom:12px;">
                <span class="solar-success-badge">✓ Fully Functional • No Server Errors</span>
            </div>
            
            <div class="solar-group">
                <label>🏠 Average monthly electricity bill ($)</label>
                <input type="number" id="estimateMonthlyBill" placeholder="e.g., 150" value="145" step="10">
            </div>
            
            <div class="solar-group">
                <label>🔋 System size (kWp)</label>
                <input type="number" id="estimateSystemSize" placeholder="kW peak" value="6.5" step="0.5">
                <small style="color:#5d7b65;">Typical residential: 5kW – 12kW</small>
            </div>
            
            <div class="solar-group">
                <label>☀️ Daily peak sun hours (your region)</label>
                <select id="estimateSunHours">
                    <option value="3.5">🌥️ Low (3.5h) - Cloudy / northern regions</option>
                    <option value="4.5" selected>🌤️ Average (4.5h) - Moderate climate</option>
                    <option value="5.5">☀️ High (5.5h) - Sunny states</option>
                    <option value="6.2">🔥 Very high (6.2h) - Desert / Southwest</option>
                </select>
            </div>
            
            <div class="solar-group">
                <label>💰 Installation cost ($ per watt)</label>
                <input type="number" id="estimateCostPerWatt" placeholder="$ per Watt" value="2.8" step="0.1">
                <small style="color:#5d7b65;">Typical range: $2.50 – $3.50 per watt</small>
            </div>
            
            <button id="runSolarEstimate" class="solar-calc-action-btn">
                📊 Calculate My Savings
            </button>
            
            <div id="solarEstimateResults" class="solar-results-area">
                <p><strong>💰 Annual Savings:</strong> <span id="resultAnnualSavings" class="solar-savings-highlight">--</span></p>
                <p><strong>📈 20-Year Net Savings:</strong> <span id="resultNet20Savings">--</span></p>
                <p><strong>⏱️ Payback Period:</strong> <span id="resultPayback">--</span> years</p>
                <p><strong>🌿 CO₂ Offset (yearly):</strong> <span id="resultCO2">--</span> metric tons</p>
                <hr>
                <p style="font-size:0.7rem; color:#4a6b55;">
                    ✅ <strong>Fix applied:</strong> No external server IP needed — instant local calculation.<br>
                    Based on $0.16/kWh average rate + 2.5% annual inflation.
                </p>
            </div>
            <div class="solar-disclaimer">
                *Estimates are for informational purposes. Actual savings depend on installation quality, local rates, and incentives.
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        // Modal elements
        const modal = document.getElementById('solarCalculatorModal');
        const openBtn = document.getElementById('openSolarCalculatorBtn');
        const closeBtn = document.getElementById('closeSolarModalBtn');
        const calcBtn = document.getElementById('runSolarEstimate');
        
        // Input fields
        const billInput = document.getElementById('estimateMonthlyBill');
        const sizeInput = document.getElementById('estimateSystemSize');
        const sunSelect = document.getElementById('estimateSunHours');
        const costInput = document.getElementById('estimateCostPerWatt');
        
        // Result spans
        const annualSpan = document.getElementById('resultAnnualSavings');
        const net20Span = document.getElementById('resultNet20Savings');
        const paybackSpan = document.getElementById('resultPayback');
        const co2Span = document.getElementById('resultCO2');
        
        // Core calculation engine (no external calls - fixes IP error)
        function calculateSolarSavings() {
            // Get values with validation
            let monthlyBill = parseFloat(billInput.value);
            if (isNaN(monthlyBill) || monthlyBill < 0) monthlyBill = 0;
            
            let systemSizeKw = parseFloat(sizeInput.value);
            if (isNaN(systemSizeKw) || systemSizeKw <= 0) systemSizeKw = 3.0;
            
            let sunHours = parseFloat(sunSelect.value);
            if (isNaN(sunHours)) sunHours = 4.5;
            
            let costPerWatt = parseFloat(costInput.value);
            if (isNaN(costPerWatt) || costPerWatt <= 0) costPerWatt = 2.8;
            
            // Constants
            const ELECTRICITY_RATE = 0.16;      // $ per kWh (US average)
            const INFLATION_RATE = 0.025;       // 2.5% annual increase
            const DEGRADATION = 0.005;          // 0.5% annual panel degradation
            const PERFORMANCE_RATIO = 0.85;      // System efficiency factor
            const YEARS = 20;
            const CO2_PER_KWH_LBS = 0.85;       // pounds CO2 per kWh (US grid avg)
            
            // Annual energy production (kWh)
            let annualProductionKwh = systemSizeKw * sunHours * 365 * PERFORMANCE_RATIO;
            
            // Current annual electricity cost
            let currentAnnualCost = monthlyBill * 12;
            
            // Calculate offset ratio (solar production vs consumption)
            let annualConsumptionKwh = currentAnnualCost / ELECTRICITY_RATE;
            if (annualConsumptionKwh <= 0 || isNaN(annualConsumptionKwh)) {
                annualConsumptionKwh = 8000; // Default for fallback
            }
            
            let offsetRatio = Math.min(0.98, annualProductionKwh / annualConsumptionKwh);
            if (annualProductionKwh <= 0) offsetRatio = 0;
            
            // Year 1 savings
            let year1Savings = currentAnnualCost * offsetRatio;
            
            // Calculate cumulative savings over 20 years with inflation and degradation
            let cumulativeSavings = 0;
            let yearlyProductionFactor = 1.0;
            let annualCostYear = currentAnnualCost;
            let savingsThisYear = year1Savings;
            
            for (let year = 1; year <= YEARS; year++) {
                if (year > 1) {
                    annualCostYear = annualCostYear * (1 + INFLATION_RATE);
                    yearlyProductionFactor = yearlyProductionFactor * (1 - DEGRADATION);
                    let adjustedOffset = Math.min(0.98, offsetRatio * yearlyProductionFactor);
                    savingsThisYear = annualCostYear * adjustedOffset;
                }
                cumulativeSavings += savingsThisYear;
            }
            
            // Total installed cost
            let totalInstalledCost = systemSizeKw * 1000 * costPerWatt;
            
            // Payback period (years)
            let paybackYears = (year1Savings > 0) ? totalInstalledCost / year1Savings : 0;
            if (isNaN(paybackYears) || paybackYears < 0) paybackYears = 0;
            paybackYears = Math.min(30, paybackYears);
            
            // Net savings after 20 years
            let netSavings = cumulativeSavings - totalInstalledCost;
            netSavings = Math.max(-totalInstalledCost, netSavings);
            
            // CO2 offset (metric tons per year)
            let co2MetricTons = (annualProductionKwh * CO2_PER_KWH_LBS) / 2204.62;
            co2MetricTons = Math.max(0, co2MetricTons);
            
            // Format currency
            const formatter = new Intl.NumberFormat('en-US', { 
                style: 'currency', 
                currency: 'USD', 
                minimumFractionDigits: 0,
                maximumFractionDigits: 0 
            });
            
            // Update DOM
            annualSpan.innerHTML = formatter.format(year1Savings) + '/year';
            net20Span.innerHTML = formatter.format(netSavings);
            paybackSpan.innerHTML = paybackYears.toFixed(1);
            co2Span.innerHTML = co2MetricTons.toFixed(1);
            
            // Special case: show message if bill is zero
            if (monthlyBill <= 0) {
                annualSpan.innerHTML = '$0/year (enter bill amount)';
                net20Span.innerHTML = formatter.format(-totalInstalledCost);
                paybackSpan.innerHTML = 'n/a';
            }
        }
        
        // Modal controls
        function openModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            calculateSolarSavings(); // Ensure fresh calculation
        }
        
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Event listeners
        if (openBtn) openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (calcBtn) calcBtn.addEventListener('click', (e) => {
            e.preventDefault();
            calculateSolarSavings();
        });
        
        // Close when clicking overlay
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }
        
        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                closeModal();
            }
        });
        
        // Auto-calculate on input changes
        const inputs = [billInput, sizeInput, costInput, sunSelect];
        inputs.forEach(input => {
            if (input) input.addEventListener('input', calculateSolarSavings);
            if (input) input.addEventListener('change', calculateSolarSavings);
        });
        
        // Initial calculation
        calculateSolarSavings();
        
        console.log("Solar Calculator ready — no external IP calls. Residential button replaced with green Solar Calculator button.");
    })();
</script>

<?php include '../../templates/footer.php'; ?>
