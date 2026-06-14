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
</style>

<!-- Hero with Rotating Backgrounds -->
<section id="heroSection">
    <div class="hero-slides">
        <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1613665813446-82a78c468a1d?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?w=1920&q=80');"></div>
        <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1532601224476-15c79f2f7a51?w=1920&q=80');"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <!-- ORIGINAL CONTENT - EXACTLY AS WAS -->
    <div class="je-container" style="color:#fff; position:relative; z-index:1;">
        <div style="font-size:11px; letter-spacing:3px; text-transform:uppercase; color:#C6A43F; margin-bottom:12px; font-weight:600;">KINAS VOLT</div>
        <h1 style="font-family:'Prata',serif; font-size:42px; font-weight:400; line-height:1.15; max-width:680px; margin-bottom:18px;">Premium Solar &amp; Energy Solutions</h1>
        <p style="font-size:17px; color:rgba(255,255,255,0.85); max-width:560px; line-height:1.6; margin-bottom:32px;">From residential rooftop systems to industrial installations — discover <?= number_format($totalSystems) ?>+ trusted solar solutions from verified providers.</p>
        <div class="je-flex" style="gap:14px;">
            <a href="search.php" class="je-btn je-btn-gold je-btn-lg"><i class="fas fa-search"></i> Browse Systems</a>
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
            <select name="service_type" style="padding:14px 18px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:3px; color:#fff; font-family:Inter,sans-serif; font-size:14px; min-width:
