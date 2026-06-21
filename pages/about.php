<?php
/**
 * KINAS GROUP — About Us
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'About Us - KINAS GROUP';
$pageDescription = 'The story behind the world\'s luxury marketplace.';
include __DIR__ . '/../templates/header.php';
?>

<style>
.about-hero {
    background: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=2000&q=80');
    background-size: cover;
    background-position: center;
    padding: 120px 0 60px;
    text-align: center;
    color: #fff;
}
.about-hero h1 {
    font-family: 'Prata', serif;
    font-size: 48px;
    margin-bottom: 12px;
}
.about-hero p {
    color: rgba(255,255,255,0.75);
    font-size: 18px;
    max-width: 700px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Division Cards - 4 in a row */
.divisions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 24px;
}
.division-card {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    min-height: 180px;
    display: flex;
    align-items: flex-end;
    text-decoration: none;
    transition: all 0.4s ease;
    cursor: pointer;
}
.division-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.15);
}
.division-card .card-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.6s ease;
}
.division-card:hover .card-bg {
    transform: scale(1.05);
}
.division-card .card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.15) 60%, transparent 100%);
}
.division-card .card-content {
    position: relative;
    z-index: 2;
    padding: 20px 18px 18px;
    color: #fff;
    width: 100%;
}
.division-card .card-content h3 {
    font-family: 'Prata', serif;
    font-size: 16px;
    margin-bottom: 2px;
    font-weight: 400;
}
.division-card .card-content p {
    font-size: 11px;
    color: rgba(255,255,255,0.8);
    margin: 0;
    line-height: 1.3;
}

@media (max-width: 1024px) {
    .divisions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 768px) {
    .about-hero h1 {
        font-size: 32px;
    }
    .about-hero p {
        font-size: 15px;
    }
    .division-card {
        min-height: 150px;
    }
}
@media (max-width: 480px) {
    .divisions-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .division-card .card-content h3 {
        font-size: 13px;
    }
    .division-card .card-content p {
        font-size: 10px;
    }
}
</style>

<!-- Hero -->
<div class="about-hero">
    <div class="je-container">
        <h1>Our Story</h1>
        <p>Curating the world's finest homes, cars, energy and goods — connecting discerning buyers with verified professionals.</p>
    </div>
</div>

<section style="max-width: 1200px; margin: 0 auto; padding: 80px 30px;">
    <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A; margin-bottom:20px;">A marketplace built on trust</h2>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        KINAS GROUP was founded with a simple belief: luxury transactions should be transparent, secure, and dignified. Whether you are acquiring a penthouse in Lagos, sourcing a 1960s grand tourer from Milan, commissioning a residential solar system, or finding an authenticated Rolex, the standard should be the same — verified counterparties, frictionless process, and concierge support.
    </p>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        Today we operate four specialised divisions — <strong>KINAS Automobile</strong> for luxury and exotic vehicles, <strong>Williams Connect Home</strong> for premium real estate, <strong>KINAS Volt</strong> for solar and energy solutions, and <strong>KINAS Marketplace</strong> for curated luxury goods. Every dealer, agent, and seller on the platform is identity-verified through our MetaMap-powered KYC process.
    </p>

    <!-- Four Divisions - Photo-Realistic Cards -->
    <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A; margin:48px 0 16px;">Our four divisions</h2>
    <div class="divisions-grid">
        <!-- KINAS Automobile -->
        <a href="/divisions/kinas-automobile/" class="division-card">
            <div class="card-bg" style="background-image: url('https://images.pexels.com/photos/170811/pexels-photo-170811.jpeg?w=400&q=80'); background-color: #1a1a2e;"></div>
            <div class="card-overlay"></div>
            <div class="card-content">
                <h3>KINAS AUTOMOBILE</h3>
                <p>Luxury &amp; exotic vehicles</p>
            </div>
        </a>

        <!-- Williams Connect Home -->
        <a href="/divisions/williams-connect-home/" class="division-card">
            <div class="card-bg" style="background-image: url('https://images.pexels.com/photos/106399/pexels-photo-106399.jpeg?w=400&q=80'); background-color: #1a2e1a;"></div>
            <div class="card-overlay"></div>
            <div class="card-content">
                <h3>WILLIAMS CONNECT HOME</h3>
                <p>Premium real estate</p>
            </div>
        </a>

        <!-- KINAS Volt -->
        <a href="/divisions/kinas-volt/" class="division-card">
            <div class="card-bg" style="background-image: url('https://images.pexels.com/photos/3182814/pexels-photo-3182814.jpeg?w=400https://images.pexels.com/photos/258097/pexels-photo-258097.jpeg?w=400&q=80q=80'); background-color: #1a1a2e;"></div>
            <div class="card-overlay"></div>
            <div class="card-content">
                <h3>KINAS VOLT</h3>
                <p>Solar &amp; energy solutions</p>
            </div>
        </a>

        <!-- KINAS Marketplace -->
        <a href="/divisions/kinas-marketplace/" class="division-card">
            <div class="card-bg" style="background-image: url('https://images.pexels.com/photos/298863/pexels-photo-298863.jpeg?w=400&q=80'); background-color: #2c1810;"></div>
            <div class="card-overlay"></div>
            <div class="card-content">
                <h3>KINAS MARKETPLACE</h3>
                <p>Curated luxury goods</p>
            </div>
        </a>
    </div>

    <!-- By the numbers -->
    <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A; margin:48px 0 20px;">By the numbers</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:20px; text-align:center;">
        <div>
            <div style="font-family:'Prata',serif; font-size:42px; color:#C6A43F;">100+</div>
            <div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:1.5px;">Countries served</div>
        </div>
        <div>
            <div style="font-family:'Prata',serif; font-size:42px; color:#C6A43F;">2,000+</div>
            <div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:1.5px;">Verified dealers &amp; agents</div>
        </div>
        <div>
            <div style="font-family:'Prata',serif; font-size:42px; color:#C6A43F;">10,000+</div>
            <div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:1.5px;">Active listings</div>
        </div>
        <div>
            <div style="font-family:'Prata',serif; font-size:42px; color:#C6A43F;">24/7</div>
            <div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:1.5px;">Concierge support</div>
        </div>
    </div>

    <!-- Press -->
    <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A; margin:48px 0 20px;">Press &amp; partnerships</h2>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        For press inquiries, partnership opportunities, or to discuss being featured in our editorial, please contact <a href="mailto:press@kinas-group.com" style="color:#C6A43F;">press@kinasgroup.com</a>.
    </p>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>
