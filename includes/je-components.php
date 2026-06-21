<?php
/**
 * JE Components - JamesEdition style components for KINAS GROUP
 * Loads after style.css
 */

// Load constants if not already loaded
if (!defined('SOCIAL_MEDIA')) {
    require_once __DIR__ . '/../api/config/constants.php';
}

function je_render_footer(string $variant = 'site'): void
{
    $year = date('Y');
    
    // Get social media from constants
    $socials = defined('SOCIAL_MEDIA') ? SOCIAL_MEDIA : [
        'facebook' => '#',
        'twitter' => '#',
        'instagram' => '#',
        'linkedin' => '#',
        'youtube' => '#'
    ];
    ?>
    <footer class="je-footer">
        <div class="je-container">
            <div class="je-footer-grid">
                <div>
                    <div class="je-footer-brand">KINAS GROUP</div>
                    <div class="je-footer-tag">The World's Luxury Marketplace — Homes, Cars, Solar &amp; Curated Goods.</div>
                    <div class="je-footer-social" aria-label="Social media">
                        <a href="<?= htmlspecialchars($socials['facebook'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?= htmlspecialchars($socials['twitter'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                        <a href="<?= htmlspecialchars($socials['instagram'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="<?= htmlspecialchars($socials['linkedin'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="<?= htmlspecialchars($socials['youtube'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div>
                    <h4>Divisions</h4>
                    <ul>
                        <li><a href="/divisions/kinas-automobile/">🚗 Kinas Automobile</a></li>
                        <li><a href="/divisions/williams-connect-home/">🏠 Williams Connect Home</a></li>
                        <li><a href="/divisions/kinas-volt/">☀️ Kinas Volt</a></li>
                        <li><a href="/divisions/kinas-marketplace/">🛍️ Kinas Marketplace</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="/pages/about.php">About Us</a></li>
                        <li><a href="/pages/contact.php">Contact</a></li>
                        <li><a href="/blog/">Journal</a></li>
                        <li><a href="/pages/about.php#careers">Careers</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="/pages/faq.php">FAQ</a></li>
                        <li><a href="/pages/terms.php">Terms &amp; Conditions</a></li>
                        <li><a href="/pages/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Stay Connected</h4>
                    <p style="color:rgba(255,255,255,0.5); font-size:13px; margin-bottom:12px;">
                        Subscribe to receive updates on new luxury listings.
                    </p>
                    <div class="je-footer-newsletter">
                        <input type="email" placeholder="Your email address" aria-label="Email address" style="width:100%; padding:11px 14px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); border-radius:3px; color:#fff; font-family:'Inter',sans-serif; font-size:13px; margin-bottom:10px; box-sizing:border-box;">
                        <button class="je-btn je-btn-gold" style="width:100%; padding:12px; background:#C6A43F; color:#0A0A0A; border:none; border-radius:3px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif;">
                            Subscribe
                        </button>
                    </div>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <div class="je-admin-portal-cta" style="margin-top:16px;">
                            <a href="/admin/dashboard.php" class="je-admin-portal-btn">
                                <span class="je-admin-portal-shield"><i class="fas fa-crown"></i></span>
                                <span class="je-admin-portal-text">
                                    <span class="je-admin-portal-eyebrow">KINAS GROUP</span>
                                    <span class="je-admin-portal-label">Admin Portal</span>
                                </span>
                                <span class="je-admin-portal-arrow"><i class="fas fa-chevron-right"></i></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="je-footer-bottom">
                <div>
                    &copy; <?= $year ?> KINAS GROUP OF COMPANY LIMITED. All rights reserved.
                </div>
                <div class="je-footer-payments">
                    <i class="fas fa-credit-card"></i>
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                </div>
            </div>
        </div>
    </footer>
    <?php
}

function je_render_listing_grid($cards) {
    if (empty($cards)) {
        echo '<div class="je-empty"><div class="je-empty-icon"><i class="fas fa-search"></i></div><div class="je-empty-title">No listings found</div><div class="je-empty-text">Try adjusting your search filters</div></div>';
        return;
    }
    ?>
    <div class="je-listings-grid">
        <?php foreach ($cards as $card): ?>
            <a href="<?php echo htmlspecialchars($card['detail_url'] ?? '#'); ?>" class="je-card">
                <div class="je-card-img">
                    <?php if (!empty($card['thumbnail'])): ?>
                        <img src="<?php echo htmlspecialchars($card['thumbnail']); ?>" alt="<?php echo htmlspecialchars($card['title'] ?? ''); ?>" loading="lazy">
                    <?php else: ?>
                        <div style="width:100%; height:100%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:40px;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($card['featured'])): ?>
                        <span class="je-card-badge">⭐ Featured</span>
                    <?php endif; ?>
                    <?php if (!empty($card['verified'])): ?>
                        <span class="je-card-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </div>
                <div class="je-card-body">
                    <div class="je-card-eyebrow"><?php echo htmlspecialchars($card['division'] ?? 'KINAS GROUP'); ?></div>
                    <div class="je-card-title"><?php echo htmlspecialchars($card['title'] ?? ''); ?></div>
                    <div class="je-card-specs"><?php echo htmlspecialchars($card['specs'] ?? ''); ?></div>
                    <div class="je-card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($card['location'] ?? ''); ?></div>
                    <div class="je-card-bottom">
                        <div class="je-card-price">₦<?php echo number_format($card['price'] ?? 0); ?></div>
                        <div class="je-card-views"><i class="far fa-eye"></i> <?php echo number_format($card['views'] ?? 0); ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
