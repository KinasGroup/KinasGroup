<?php
/**
 * KINAS VOLT — Solar & Energy division landing
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
require_once '../../includes/email.php'; // Your existing EmailService

$db = Database::getInstance()->getConnection();

// Create solar enquiries table if not exists
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

<section id="heroSection" style="position:relative; height:70vh; min-height:480px; padding-top:90px; box-sizing:border-box; background:linear-gradient(135deg, rgba(10,40,20,0.5), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=2000&q=80') center/cover no-repeat; display:flex; align-items:center;">
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS VOLT</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Premium Solar &amp; Energy Solutions</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From residential rooftop systems to industrial installations — discover <?= number_format($totalSystems) ?>+ trusted solar solutions from verified providers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Systems</a>
            <!-- REPLACED: "Residential" button is now GREEN "Solar Calculator" button (NIGERIAN NAIRA VERSION) -->
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
<!-- SOLAR SAVINGS ESTIMATOR - NIGERIAN NAIRA    -->
<!-- Fully functional, captures leads, emails you -->
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
        max-width: 580px;
        width: 90%;
        max-height: 90vh;
        border-radius: 1.5rem;
        box-shadow: 0 30px 40px rgba(0, 0, 0, 0.4);
        overflow-y: auto;
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
        position: sticky;
        top: 0;
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
    .solar-submit-btn {
        background: #e67e22;
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
    .solar-submit-btn:hover {
        background: #cf711f;
    }
    .solar-submit-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
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
    .solar-status {
        margin-top: 12px;
        padding: 10px;
        border-radius: 10px;
        font-size: 0.85rem;
        text-align: center;
    }
    .solar-status.success {
        background: #d4edda;
        color: #155724;
    }
    .solar-status.error {
        background: #f8d7da;
        color: #721c24;
    }
    hr {
        margin: 1rem 0;
        border: 0;
        height: 1px;
        background: #e0e8e2;
    }
</style>

<!-- Solar Calculator Modal -->
<div id="solarCalculatorModal" class="solar-modal-overlay">
    <div class="solar-modal-container">
        <div class="solar-modal-header">
            <h3>☀️ Solar Savings Estimator (₦ Naira)</h3>
            <button class="solar-close-modal" id="closeSolarModalBtn">&times;</button>
        </div>
        <div class="solar-modal-body">
            <div style="text-align:center; margin-bottom:12px;">
                <span class="solar-success-badge">✓ Get Instant Quote • No Server Errors</span>
            </div>
            
            <!-- Customer Details Section -->
            <div class="solar-group">
                <label>👤 Your Full Name *</label>
                <input type="text" id="customerFullName" placeholder="e.g., John Okonkwo">
            </div>
            <div class="solar-group">
                <label>📧 Email Address *</label>
                <input type="email" id="customerEmailAddr" placeholder="you@example.com">
            </div>
            <div class="solar-group">
                <label>📞 Phone Number *</label>
                <input type="tel" id="customerPhoneNum" placeholder="0803 123 4567">
            </div>
            
            <hr>
            
            <!-- Calculator Section -->
            <div class="solar-group">
                <label>🏠 Average monthly electricity bill (₦)</label>
                <input type="number" id="estimateMonthlyBill" placeholder="e.g., 50000" value="50000" step="10000">
            </div>
            
            <div class="solar-group">
                <label>🔋 Recommended System size (kWp)</label>
                <input type="number" id="estimateSystemSize" placeholder="kW peak" value="5" step="0.5" readonly style="background:#f0f0f0;">
                <small style="color:#5d7b65;">Auto-calculated based on your monthly bill</small>
            </div>
            
            <div class="solar-group">
                <label>☀️ Daily peak sun hours (your region in Nigeria)</label>
                <select id="estimateSunHours">
                    <option value="4">🌥️ North Nigeria (4h)</option>
                    <option value="4.5" selected>🌤️ Central Nigeria (4.5h)</option>
                    <option value="5">☀️ South Nigeria (5h)</option>
                </select>
            </div>
            
            <div id="solarEstimateResults" class="solar-results-area">
                <p><strong>💰 Estimated Annual Savings:</strong> <span id="resultAnnualSavings" class="solar-savings-highlight">--</span></p>
                <p><strong>📈 20-Year Net Savings:</strong> <span id="resultNet20Savings">--</span></p>
                <p><strong>⏱️ Payback Period:</strong> <span id="resultPayback">--</span> years</p>
                <p><strong>🌿 CO₂ Offset (yearly):</strong> <span id="resultCO2">--</span> metric tons</p>
            </div>
            
            <div id="formStatus"></div>
            
            <button id="submitSolarEnquiry" class="solar-submit-btn">
                📩 Send Enquiry — Get Custom Quote
            </button>
            
            <div class="solar-disclaimer">
                *We'll contact you within 24 hours with a detailed site assessment and exact pricing.<br>
                Estimates based on NERC ₦225/kWh tariff + 5% annual inflation.
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
    
    // Input fields
    const billInput = document.getElementById('estimateMonthlyBill');
    const sizeInput = document.getElementById('estimateSystemSize');
    const sunSelect = document.getElementById('estimateSunHours');
    
    // Customer fields
    const nameInput = document.getElementById('customerFullName');
    const emailInput = document.getElementById('customerEmailAddr');
    const phoneInput = document.getElementById('customerPhoneNum');
    
    // Result spans
    const annualSpan = document.getElementById('resultAnnualSavings');
    const net20Span = document.getElementById('resultNet20Savings');
    const paybackSpan = document.getElementById('resultPayback');
    const co2Span = document.getElementById('resultCO2');
    
    const submitBtn = document.getElementById('submitSolarEnquiry');
    const statusDiv = document.getElementById('formStatus');
    
    // Nigerian constants
    const NGN_RATE_PER_KWH = 225;      // NERC average tariff 2024 (₦/kWh)
    const PERFORMANCE_RATIO = 0.85;
    const CO2_PER_KWH_LBS = 0.85;
    const SYSTEM_COST_PER_WATT = 650;   // ₦650 per watt (approx cost in Nigeria)
    const DEGRADATION = 0.005;          // 0.5% annual panel degradation
    const INFLATION = 0.05;             // 5% annual tariff increase in Nigeria
    const YEARS = 20;
    
    // Calculate recommended system size based on monthly bill
    function calculateSystemSize(bill) {
        if (bill <= 0) return 3;
        let annualConsumption = (bill * 12) / NGN_RATE_PER_KWH;
        let recommendedKw = (annualConsumption / (365 * 4.5 * PERFORMANCE_RATIO)) * 0.8;
        return Math.max(2, Math.min(20, Math.round(recommendedKw * 2) / 2));
    }
    
    // Core calculation engine (Nigerian Naira version)
    function calculateSolarSavings() {
        // Get values with validation
        let monthlyBill = parseFloat(billInput.value);
        if (isNaN(monthlyBill) || monthlyBill < 0) monthlyBill = 0;
        
        let systemSizeKw = parseFloat(sizeInput.value);
        if (isNaN(systemSizeKw) || systemSizeKw <= 0) systemSizeKw = 5;
        
        let sunHours = parseFloat(sunSelect.value);
        if (isNaN(sunHours)) sunHours = 4.5;
        
        // Annual energy production (kWh)
        let annualProductionKwh = systemSizeKw * sunHours * 365 * PERFORMANCE_RATIO;
        
        // Current annual electricity cost
        let currentAnnualCost = monthlyBill * 12;
        
        // Calculate offset ratio (solar production vs consumption)
        let annualConsumptionKwh = currentAnnualCost / NGN_RATE_PER_KWH;
        if (annualConsumptionKwh <= 0 || isNaN(annualConsumptionKwh)) {
            annualConsumptionKwh = 8000;
        }
        
        let offsetRatio = Math.min(0.95, annualProductionKwh / annualConsumptionKwh);
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
                annualCostYear = annualCostYear * (1 + INFLATION);
                yearlyProductionFactor = yearlyProductionFactor * (1 - DEGRADATION);
                let adjustedOffset = Math.min(0.95, offsetRatio * yearlyProductionFactor);
                savingsThisYear = annualCostYear * adjustedOffset;
            }
            cumulativeSavings += savingsThisYear;
        }
        
        // Total installed cost in Naira
        let totalInstalledCost = systemSizeKw * 1000 * SYSTEM_COST_PER_WATT;
        
        // Payback period (years)
        let paybackYears = (year1Savings > 0) ? totalInstalledCost / year1Savings : 0;
        if (isNaN(paybackYears) || paybackYears < 0) paybackYears = 0;
        paybackYears = Math.min(20, paybackYears);
        
        // Net savings after 20 years
        let netSavings = cumulativeSavings - totalInstalledCost;
        netSavings = Math.max(-totalInstalledCost, netSavings);
        
        // CO2 offset (metric tons per year)
        let co2MetricTons = (annualProductionKwh * CO2_PER_KWH_LBS) / 2204.62;
        co2MetricTons = Math.max(0, co2MetricTons);
        
        // Format currency in Naira
        const formatter = new Intl.NumberFormat('en-NG', { 
            style: 'currency', 
            currency: 'NGN', 
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
            annualSpan.innerHTML = '₦0/year (enter bill amount)';
            net20Span.innerHTML = formatter.format(-totalInstalledCost);
            paybackSpan.innerHTML = 'n/a';
        }
        
        return { year1Savings, systemSizeKw, paybackYears };
    }
    
    // Auto-update system size when bill changes
    billInput.addEventListener('input', function() {
        let newSize = calculateSystemSize(parseFloat(this.value) || 0);
        sizeInput.value = newSize;
        calculateSolarSavings();
    });
    
    sunSelect.addEventListener('change', calculateSolarSavings);
    
    // Submit enquiry to backend
    submitBtn.addEventListener('click', async function() {
        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const phone = phoneInput.value.trim();
        const monthlyBill = parseFloat(billInput.value) || 0;
        const systemSize = parseFloat(sizeInput.value) || 5;
        
        // Get current savings from display
        let annualSavingsText = annualSpan.innerText;
        let annualSavings = parseFloat(annualSavingsText.replace(/[^0-9.-]+/g, '')) || 0;
        let payback = parseFloat(paybackSpan.innerText) || 0;
        
        if (!name || !email || !phone) {
            statusDiv.innerHTML = '<div class="solar-status error">⚠️ Please fill in your name, email, and phone number</div>';
            return;
        }
        
        if (!email.includes('@')) {
            statusDiv.innerHTML = '<div class="solar-status error">⚠️ Please enter a valid email address</div>';
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        try {
            const response = await fetch('/send_solar_enquiry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    full_name: name,
                    email: email,
                    phone: phone,
                    monthly_bill: monthlyBill,
                    system_size: systemSize,
                    annual_savings: annualSavings,
                    payback_years: payback
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                statusDiv.innerHTML = '<div class="solar-status success">✅ ' + result.message + '</div>';
                // Clear form after successful submission
                nameInput.value = '';
                emailInput.value = '';
                phoneInput.value = '';
                setTimeout(() => {
                    modal.classList.remove('active');
                    statusDiv.innerHTML = '';
                }, 2000);
            } else {
                statusDiv.innerHTML = '<div class="solar-status error">❌ ' + result.message + '</div>';
            }
        } catch (error) {
            statusDiv.innerHTML = '<div class="solar-status error">❌ Network error. Please try again.</div>';
        }
        
        submitBtn.disabled = false;
        submitBtn.textContent = '📩 Send Enquiry — Get Custom Quote';
    });
    
    // Modal controls
    function openModal() {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        calculateSolarSavings();
    }
    
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        statusDiv.innerHTML = '';
    }
    
    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    
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
    
    // Initial calculation
    calculateSolarSavings();
    
    console.log("Solar Calculator ready — Nigerian Naira version. Residential button replaced with green Solar Calculator button.");
})();
</script>

<?php include '../../templates/footer.php'; ?>
