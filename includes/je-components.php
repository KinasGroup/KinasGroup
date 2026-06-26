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
    $role = $_SESSION['user_role'] ?? null;

    // Work out which division's social icons to show, based on the URL.
    // The 3 product divisions each get their own TikTok/Instagram/Facebook;
    // everything else (homepage, Kinas Marketplace, dashboards, blog, etc.)
    // falls back to the group-wide 'kinasgroup' set.
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $divisionKey = 'kinasgroup';
    foreach (['williams-connect-home', 'kinas-automobile', 'kinas-volt'] as $divSlug) {
        if (strpos($requestPath, '/divisions/' . $divSlug) !== false) {
            $divisionKey = $divSlug;
            break;
        }
    }

    $divisionSocials = defined('DIVISION_SOCIAL_MEDIA') ? DIVISION_SOCIAL_MEDIA : [];
    $socials = $divisionSocials[$divisionKey] ?? [
        'tiktok' => '#',
        'instagram' => '#',
        'facebook' => '#'
    ];
    ?>
    <footer class="je-footer">
        <div class="je-container">
            <div class="je-footer-grid">
                <div>
                    <div class="je-footer-brand">KINAS GROUP</div>
                    <div class="je-footer-tag">The World's Luxury Marketplace — Homes, Cars, Solar &amp; Curated Goods.</div>
                    <div class="je-footer-social" aria-label="Social media">
                        <a href="<?= htmlspecialchars($socials['tiktok'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                        <a href="<?= htmlspecialchars($socials['instagram'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="<?= htmlspecialchars($socials['facebook'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    </div>
                    <!-- Company Address - displayed below social icons -->
                    <div class="je-footer-address">
                        <p style="color:rgba(255,255,255,0.5); font-size:12px; line-height:1.6; margin:12px 0 0 0;">
                            KINAS GROUP OF COMPANIES LIMITED &bull; RC Number: 7997266<br>
                            Gwarinpa, 900108, Federal Capital Territory, Nigeria<br>
                            Phone: <a href="tel:+2348107576042" style="color:rgba(255,255,255,0.6); text-decoration:none;">+234 810 757 6042</a> &bull; 
                            Email: <a href="mailto:support@kinas-group.com" style="color:rgba(255,255,255,0.6); text-decoration:none;">support@kinas-group.com</a>
                        </p>
                    </div>
                </div>
                <div>
                    <h4>Divisions</h4>
                    <ul>
                        <li><a href="/divisions/kinas-automobile/">Kinas Automobile</a></li>
                        <li><a href="/divisions/williams-connect-home/">Williams Connect Home</a></li>
                        <li><a href="/divisions/kinas-volt/">Kinas Volt</a></li>
                        <li><a href="/divisions/kinas-marketplace/">Kinas Marketplace</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="/pages/about.php">About Us</a></li>
                        <li><a href="/pages/contact.php">Contact</a></li>
                        <li><a href="/pages/careers.php#careers">Careers</a></li>
                        <li><a href="/pages/privacypolicy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="/pages/faq.php">FAQ</a></li>
                        <li><a href="/pages/termsandconditions.php">Terms &amp; Conditions</a></li>
                        <li><a href="/pages/privacypolicy.php">Privacy Policy</a></li>
                        <li><a href="/pages/cookiepolicy.php">Cookie Policy</a></li>
                        <li><a href="/pages/refundpolicy.php">Refund Policy</a></li>
                        <li><a href="/pages/termsofuse.php">Terms of Use</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Stay Connected</h4>
                    <p style="color:rgba(255,255,255,0.5); font-size:13px; margin-bottom:12px;">
                        Subscribe to receive updates on new luxury listings.
                    </p>
                    <div class="je-footer-newsletter">
                        <input type="email" placeholder="Your email address" aria-label="Email address" style="width:100%; padding:8px 14px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.15); border-radius:3px; color:#fff; font-family:'Inter',sans-serif; font-size:13px; margin-bottom:10px; box-sizing:border-box;">
                        <button class="je-btn je-btn-gold" style="width:100%; padding:9px 14px; background:#C6A43F; color:#0A0A0A; border:none; border-radius:3px; font-weight:600; cursor:pointer; font-family:'Inter',sans-serif; font-size:13px; transition:opacity 0.2s;">
                            Subscribe
                        </button>
                    </div>
                    <!-- ADMIN PORTAL BUTTON REMOVED -->
                </div>
            </div>
            <div class="je-footer-bottom">
                <div>
                    &copy; <?= $year ?> KINAS GROUP OF COMPANY LIMITED. All rights reserved.
                </div>
            </div>
        </div>
    </footer>
    <?php
}

/**
 * Render Hero Search Bar
 */
function je_render_hero_bar($title, $placeholder, $query, $action, $current) {
    ?>
    <div class="je-hero-bar">
        <div class="je-hero-title"><?php echo htmlspecialchars($title); ?></div>
        <form class="je-hero-form" method="GET" action="<?php echo htmlspecialchars($action); ?>">
            <input class="je-hero-input" type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="<?php echo htmlspecialchars($placeholder); ?>">
            <button class="je-hero-btn" type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    <?php
}

/**
 * Render Filter Panel
 */
function je_render_filter_panel($filters, $current, $action) {
    ?>
    <div class="je-filter-panel" id="jeFilterPanel">
        <form method="GET" action="<?php echo htmlspecialchars($action); ?>">
            <?php foreach ($filters as $f): ?>
                <div class="je-filter-section">
                    <div class="je-filter-label"><?php echo htmlspecialchars($f['label']); ?></div>
                    <?php if ($f['type'] === 'select' && isset($f['options'])): ?>
                        <select class="je-filter-select" name="<?php echo htmlspecialchars($f['name']); ?>">
                            <option value="">Any</option>
                            <?php foreach ($f['options'] as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($current[$f['name']] ?? '') == $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($f['type'] === 'price'): ?>
                        <div class="je-price-row">
                            <input class="je-filter-input" type="number" name="min_price" placeholder="Min" value="<?php echo htmlspecialchars($current['min_price'] ?? ''); ?>" step="1000">
                            <span class="je-price-sep">—</span>
                            <input class="je-filter-input" type="number" name="max_price" placeholder="Max" value="<?php echo htmlspecialchars($current['max_price'] ?? ''); ?>" step="1000">
                        </div>
                    <?php elseif ($f['type'] === 'range'): ?>
                        <div class="je-price-row">
                            <input class="je-filter-input" type="number" name="<?php echo htmlspecialchars($f['min_name']); ?>" placeholder="<?php echo htmlspecialchars($f['min_placeholder'] ?? 'Min'); ?>" value="<?php echo htmlspecialchars($current[$f['min_name']] ?? ''); ?>" step="0.1">
                            <span class="je-price-sep">—</span>
                            <input class="je-filter-input" type="number" name="<?php echo htmlspecialchars($f['max_name']); ?>" placeholder="<?php echo htmlspecialchars($f['max_placeholder'] ?? 'Max'); ?>" value="<?php echo htmlspecialchars($current[$f['max_name']] ?? ''); ?>" step="0.1">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button class="je-filter-apply-btn" type="submit">Apply Filters</button>
            <a class="je-filter-clear" href="<?php echo htmlspecialchars($action); ?>">Clear all filters</a>
        </form>
    </div>
    <?php
}

/**
 * Render Sort Row
 */
function je_render_sort_row($sortOptions, $currentSort, $current, $action) {
    ?>
    <div class="je-sort-row">
        <span class="je-sort-label">Sort:</span>
        <select class="je-sort-select" onchange="window.location.href=this.value;">
            <?php foreach ($sortOptions as $key => $label): 
                $params = array_merge($current, ['sort' => $key]);
                $url = $action . '?' . http_build_query($params);
            ?>
                <option value="<?php echo htmlspecialchars($url); ?>" <?php echo $currentSort === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

/**
 * Render Pagination
 */
function je_render_pagination($page, $total, $perPage, $action, $pageParam, $current) {
    $totalPages = max(1, ceil($total / $perPage));
    if ($totalPages <= 1) return;
    ?>
    <div class="je-pagination">
        <?php if ($page > 1): ?>
            <a class="je-page-btn" href="?<?php echo http_build_query(array_merge($current, [$pageParam => $page - 1])); ?>">‹</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="je-page-btn is-active"><?php echo $i; ?></span>
            <?php elseif ($i <= 3 || $i > $totalPages - 3 || abs($i - $page) <= 2): ?>
                <a class="je-page-btn" href="?<?php echo http_build_query(array_merge($current, [$pageParam => $i])); ?>"><?php echo $i; ?></a>
            <?php elseif ($i == 4 && $page > 5): ?>
                <span class="je-page-btn is-dots">…</span>
            <?php elseif ($i == $totalPages - 3 && $page < $totalPages - 4): ?>
                <span class="je-page-btn is-dots">…</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a class="je-page-btn" href="?<?php echo http_build_query(array_merge($current, [$pageParam => $page + 1])); ?>">›</a>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * je_render_card - Renders a single listing card
 * FIXED: This function was missing and causing fatal errors
 */
function je_render_card($card) {
    // Handle both array and object input
    $card = (array)$card;
    
    // Extract card data with defaults
    $id = $card['id'] ?? 0;
    $title = $card['title'] ?? 'Untitled';
    $price = $card['price'] ?? null;
    $thumbnail = $card['thumbnail'] ?? '';
    $specs = $card['specs'] ?? '';
    $location = $card['location'] ?? '';
    $detail_url = $card['detail_url'] ?? '#';
    $featured = $card['featured'] ?? false;
    $verified = $card['verified'] ?? false;
    $views = $card['views'] ?? 0;
    $division = $card['division'] ?? 'KINAS GROUP';
    
    // Format price
    $priceFormatted = $price !== null ? '₦' . number_format((float)$price) : 'Contact for price';
    ?>
    <a href="<?php echo htmlspecialchars($detail_url); ?>" class="je-card">
        <div class="je-card-img">
            <?php if (!empty($thumbnail)): ?>
                <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($title); ?>" loading="lazy">
            <?php else: ?>
                <div style="width:100%; height:100%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:40px;">
                    <i class="fas fa-image"></i>
                </div>
            <?php endif; ?>
            <?php if ($featured): ?>
                <span class="je-card-badge">⭐ Featured</span>
            <?php endif; ?>
            <?php if ($verified): ?>
                <span class="je-card-verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
            <?php endif; ?>
        </div>
        <div class="je-card-body">
            <div class="je-card-eyebrow"><?php echo htmlspecialchars($division); ?></div>
            <div class="je-card-title"><?php echo htmlspecialchars($title); ?></div>
            <?php if (!empty($specs)): ?>
                <div class="je-card-specs"><?php echo htmlspecialchars($specs); ?></div>
            <?php endif; ?>
            <?php if (!empty($location)): ?>
                <div class="je-card-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?></div>
            <?php endif; ?>
            <div class="je-card-bottom">
                <div class="je-card-price"><?php echo $priceFormatted; ?></div>
                <?php if ($views > 0): ?>
                    <div class="je-card-views"><i class="far fa-eye"></i> <?php echo number_format($views); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php
}

/**
 * Render Listing Grid
 */
function je_render_listing_grid($cards, $emptyTitle = 'No listings found', $emptyText = 'Try adjusting your search filters', $action = 'search.php') {
    if (empty($cards)) {
        echo '<div class="je-empty"><div class="je-empty-icon"><i class="fas fa-search"></i></div><div class="je-empty-title">' . htmlspecialchars($emptyTitle) . '</div><div class="je-empty-text">' . htmlspecialchars($emptyText) . '</div><a href="' . htmlspecialchars($action) . '" class="je-empty-btn">Reset Filters</a></div>';
        return;
    }
    ?>
    <div class="je-listings-grid">
        <?php foreach ($cards as $card): ?>
            <?php je_render_card($card); ?>
        <?php endforeach; ?>
    </div>
    <?php
}
?>
