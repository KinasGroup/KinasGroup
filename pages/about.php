<?php
/**
 * KINAS GROUP — About Us
 */
$pageTitle = 'About Us - KINAS GROUP';
$pageDescription = 'The story behind the world\'s luxury marketplace.';
include __DIR__ . '/../templates/header.php';
?>

<div class="je-page-header" style="background-image: linear-gradient(135deg, rgba(10,10,10,0.85), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=2000&q=80'); background-size:cover; background-position:center;">
    <h1>Our Story</h1>
    <p>Curating the world's finest homes, cars, energy and goods — connecting discerning buyers with verified professionals.</p>
</div>

<section style="max-width: 900px; margin: 0 auto; padding: 80px 30px;">
    <h2 style="font-family:'Prata',serif; font-size:32px; color:#0A0A0A; margin-bottom:20px;">A marketplace built on trust</h2>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        KINAS GROUP was founded with a simple belief: luxury transactions should be transparent, secure, and dignified. Whether you are acquiring a penthouse in Lagos, sourcing a 1960s grand tourer from Milan, commissioning a residential solar system, or finding an authenticated Rolex, the standard should be the same — verified counterparties, frictionless process, and concierge support.
    </p>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        Today we operate four specialised divisions — <strong>KINAS Automobile</strong> for luxury and exotic vehicles, <strong>Williams Connect Home</strong> for premium real estate, <strong>KINAS Volt</strong> for solar and energy solutions, and <strong>KINAS Marketplace</strong> for curated luxury goods. Every dealer, agent, and seller on the platform is identity-verified through our MetaMap-powered KYC process.
    </p>

    <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A; margin:48px 0 20px;">Our four divisions</h2>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px;">
        <a href="/divisions/kinas-automobile/" style="background:#fff; border:1px solid #e8e8e8; padding:28px; border-radius:4px; text-decoration:none; color:#0A0A0A; transition:all 0.25s;">
            <div style="font-size:32px; color:#C6A43F; margin-bottom:10px;"><i class="fas fa-car"></i></div>
            <h3 style="font-family:'Prata',serif; font-size:18px; margin-bottom:6px;">KINAS AUTOMOBILE</h3>
            <p style="font-size:13px; color:#666; line-height:1.6;">Luxury and exotic vehicles from verified dealers worldwide.</p>
        </a>
        <a href="/divisions/williams-connect-home/" style="background:#fff; border:1px solid #e8e8e8; padding:28px; border-radius:4px; text-decoration:none; color:#0A0A0A; transition:all 0.25s;">
            <div style="font-size:32px; color:#C6A43F; margin-bottom:10px;"><i class="fas fa-home"></i></div>
            <h3 style="font-family:'Prata',serif; font-size:18px; margin-bottom:6px;">WILLIAMS CONNECT HOME</h3>
            <p style="font-size:13px; color:#666; line-height:1.6;">Premium real estate in the world's most desirable addresses.</p>
        </a>
        <a href="/divisions/kinas-volt/" style="background:#fff; border:1px solid #e8e8e8; padding:28px; border-radius:4px; text-decoration:none; color:#0A0A0A; transition:all 0.25s;">
            <div style="font-size:32px; color:#C6A43F; margin-bottom:10px;"><i class="fas fa-solar-panel"></i></div>
            <h3 style="font-family:'Prata',serif; font-size:18px; margin-bottom:6px;">KINAS VOLT</h3>
            <p style="font-size:13px; color:#666; line-height:1.6;">Solar and energy solutions from certified installers.</p>
        </a>
        <a href="/divisions/kinas-marketplace/" style="background:#fff; border:1px solid #e8e8e8; padding:28px; border-radius:4px; text-decoration:none; color:#0A0A0A; transition:all 0.25s;">
            <div style="font-size:32px; color:#C6A43F; margin-bottom:10px;"><i class="fas fa-gem"></i></div>
            <h3 style="font-family:'Prata',serif; font-size:18px; margin-bottom:6px;">KINAS MARKETPLACE</h3>
            <p style="font-size:13px; color:#666; line-height:1.6;">Authenticated luxury watches, jewelry, art and collectibles.</p>
        </a>
    </div>

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

    <h2 style="font-family:'Prata',serif; font-size:28px; color:#0A0A0A; margin:48px 0 20px;">Press &amp; partnerships</h2>
    <p style="font-size:15px; color:#555; line-height:1.9; margin-bottom:24px;">
        For press inquiries, partnership opportunities, or to discuss being featured in our editorial, please contact <a href="mailto:press@kinasgroup.com" style="color:#C6A43F;">press@kinasgroup.com</a>.
    </p>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>
