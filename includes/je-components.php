<?php
/**
 * KINAS GROUP — JamesEdition component helpers
 *
 * Reusable renderers for the four-division search/detail pages.
 * Every search.php file should `require_once` this and call:
 *   je_render_search_shell($division, $title, $filters, $cards);
 * or use the smaller primitives:
 *   je_render_filter_panel(...)
 *   je_render_listing_grid(...)
 *   je_render_pagination(...)
 *   je_render_card(...)
 */
require_once __DIR__ . '/functions.php';

/* ──────────────────────────────────────────────────────────
   FILTER SIDEBAR
   $filters = [
     ['name' => 'brand', 'label' => 'Brand', 'type' => 'select', 'options' => [...]],
     ['name' => 'min_price', 'label' => 'Min Price', 'type' => 'text', 'placeholder' => 'Min'],
     ['name' => 'year', 'label' => 'Year', 'type' => 'select', 'options' => [...]],
     ['name' => 'body_type', 'label' => 'Body Type', 'type' => 'chips', 'options' => [...]],
     ['name' => 'transmission', 'label' => 'Transmission', 'type' => 'select', 'options' => [...]],
   ];
   $current = ['brand' => 'BMW', 'min_price' => '50000', ...]  // current GET params
   ────────────────────────────────────────────────────────── */
function je_render_filter_panel(array $filters, array $current, string $formAction = '', string $searchQueryKey = 'q'): void
{
    $action = $formAction ?: '';
    ?>
    <aside class="je-filter-panel">
        <form method="GET" action="<?= htmlspecialchars($action) ?>" id="je-filter-form">
            <?php foreach ($current as $k => $v): if ($k === $searchQueryKey || $v === '' || $v === null) continue; ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
            <?php endforeach; ?>

            <?php foreach ($filters as $f):
                $name = $f['name']; $val = $current[$name] ?? '';
                if (($f['type'] ?? '') === 'select'): ?>
                <div class="je-filter-section">
                    <span class="je-filter-label"><?= htmlspecialchars($f['label']) ?></span>
                    <select name="<?= htmlspecialchars($name) ?>" class="je-filter-select">
                        <option value=""><?= htmlspecialchars($f['all_label'] ?? 'Any') ?></option>
                        <?php foreach (($f['options'] ?? []) as $opt): $optVal = is_array($opt) ? ($opt['value'] ?? $opt['id'] ?? '') : $opt; $optLabel = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? $optVal) : $opt; ?>
                        <option value="<?= htmlspecialchars((string)$optVal) ?>" <?= (string)$val === (string)$optVal ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$optLabel) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php elseif (($f['type'] ?? '') === 'chips'): ?>
                <div class="je-filter-section">
                    <span class="je-filter-label"><?= htmlspecialchars($f['label']) ?></span>
                    <div class="je-chip-group">
                        <?php foreach (($f['options'] ?? []) as $opt): $optVal = is_array($opt) ? ($opt['value'] ?? $opt['id'] ?? '') : $opt; ?>
                        <label class="je-chip">
                            <input type="checkbox" name="<?= htmlspecialchars($name) ?>[]" value="<?= htmlspecialchars((string)$optVal) ?>"
                                <?php if (is_array($val) && in_array((string)$optVal, array_map('strval', $val), true)) echo 'checked'; ?>>
                            <span><?= htmlspecialchars((string)$optVal) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif (($f['type'] ?? '') === 'price'): ?>
                <div class="je-filter-section">
                    <span class="je-filter-label"><?= htmlspecialchars($f['label']) ?> (₦)</span>
                    <div class="je-price-row">
                        <input type="text" name="min_price" class="je-filter-input" placeholder="Min"
                               value="<?= htmlspecialchars((string)($current['min_price'] ?? '')) ?>">
                        <input type="text" name="max_price" class="je-filter-input" placeholder="Max"
                               value="<?= htmlspecialchars((string)($current['max_price'] ?? '')) ?>">
                    </div>
                </div>
                <?php elseif (($f['type'] ?? '') === 'range'): ?>
                <div class="je-filter-section">
                    <span class="je-filter-label"><?= htmlspecialchars($f['label']) ?></span>
                    <div class="je-price-row">
                        <input type="<?= htmlspecialchars($f['input_type'] ?? 'text') ?>" name="<?= htmlspecialchars($f['min_name'] ?? $name.'_min') ?>" class="je-filter-input"
                               placeholder="<?= htmlspecialchars($f['min_placeholder'] ?? 'Min') ?>"
                               value="<?= htmlspecialchars((string)($current[$f['min_name'] ?? $name.'_min'] ?? '')) ?>">
                        <input type="<?= htmlspecialchars($f['input_type'] ?? 'text') ?>" name="<?= htmlspecialchars($f['max_name'] ?? $name.'_max') ?>" class="je-filter-input"
                               placeholder="<?= htmlspecialchars($f['max_placeholder'] ?? 'Max') ?>"
                               value="<?= htmlspecialchars((string)($current[$f['max_name'] ?? $name.'_max'] ?? '')) ?>">
                    </div>
                </div>
                <?php else: ?>
                <div class="je-filter-section">
                    <span class="je-filter-label"><?= htmlspecialchars($f['label']) ?></span>
                    <input type="<?= htmlspecialchars($f['type'] ?? 'text') ?>" name="<?= htmlspecialchars($name) ?>" class="je-filter-input"
                           placeholder="<?= htmlspecialchars($f['placeholder'] ?? '') ?>"
                           value="<?= htmlspecialchars((string)$val) ?>">
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="je-filter-section">
                <button type="submit" class="je-filter-apply-btn">Apply Filters</button>
                <a href="<?= htmlspecialchars($action ?: 'search.php') ?>" class="je-filter-clear">Clear all filters</a>
            </div>
        </form>
    </aside>
    <?php
}

/* ──────────────────────────────────────────────────────────
   LISTING CARD
   $item = [
     'id', 'title', 'division', 'price', 'thumbnail',
     'specs' => '2021 • 24,000 km • Automatic',
     'location' => 'Lagos, Nigeria',
     'detail_url' => '/divisions/.../detail.php?id=12',
     'featured' => true|false,
     'verified' => true|false,
     'views' => 120,
   ]
   ────────────────────────────────────────────────────────── */
function je_render_card(array $item): void
{
    $isFeatured = !empty($item['featured']);
    $isVerified = !empty($item['verified']);
    $price      = $item['price'] ?? null;
    $title      = $item['title'] ?? 'Untitled';
    $division   = $item['division'] ?? '';
    $specs      = $item['specs'] ?? '';
    $location   = $item['location'] ?? '';
    $thumb      = $item['thumbnail'] ?? '';
    $url        = $item['detail_url'] ?? '#';
    $views      = $item['views'] ?? null;
    ?>
    <a class="je-card" href="<?= htmlspecialchars($url) ?>">
        <div class="je-card-img">
            <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#C6A43F;font-size:36px;"><i class="fas fa-image"></i></div>
            <?php endif; ?>
            <?php if ($isFeatured): ?><span class="je-card-badge">Featured</span>
            <?php elseif ($isVerified): ?><span class="je-card-verified-badge">Verified</span>
            <?php endif; ?>
            <button type="button" class="je-card-fav" onclick="event.preventDefault();"><i class="far fa-heart"></i></button>
        </div>
        <div class="je-card-body">
            <?php if ($division): ?><div class="je-card-eyebrow"><?= htmlspecialchars($division) ?></div><?php endif; ?>
            <div class="je-card-title"><?= htmlspecialchars($title) ?></div>
            <?php if ($specs): ?><div class="je-card-specs"><?= htmlspecialchars($specs) ?></div><?php endif; ?>
            <?php if ($location): ?><div class="je-card-location"><i class="fas fa-map-marker-alt" style="color:#C6A43F"></i> <?= htmlspecialchars($location) ?></div><?php endif; ?>
            <div class="je-card-bottom">
                <?php if ($price !== null): ?>
                    <div class="je-card-price"><?= function_exists('formatPrice') ? formatPrice((float)$price) : '₦' . number_format((float)$price) ?></div>
                <?php else: ?>
                    <div class="je-card-price">—</div>
                <?php endif; ?>
                <?php if ($views !== null): ?>
                    <div class="je-card-views"><i class="far fa-eye"></i> <?= number_format((int)$views) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php
}

function je_render_listing_grid(array $items, string $emptyTitle = 'No listings found', string $emptyText = '', string $emptyHref = ''): void
{
    if (empty($items)): ?>
        <div class="je-empty">
            <div class="je-empty-icon">🏛️</div>
            <div class="je-empty-title"><?= htmlspecialchars($emptyTitle) ?></div>
            <div class="je-empty-text"><?= htmlspecialchars($emptyText ?: 'Try adjusting your filters or check back soon.') ?></div>
            <?php if ($emptyHref): ?><a href="<?= htmlspecialchars($emptyHref) ?>" class="je-empty-btn">Browse all</a><?php endif; ?>
        </div>
    <?php return; endif; ?>
    <div class="je-listings-grid">
    <?php foreach ($items as $it) je_render_card($it); ?>
    </div>
    <?php
}

/* ──────────────────────────────────────────────────────────
   PAGINATION
   $baseUrl  – the URL to use for page links (without query)
   $page     – current page (1-based)
   $total    – total results
   $perPage  – items per page
   $paramKey – the query param name (default 'page')
   $params   – additional query params to preserve
   ────────────────────────────────────────────────────────── */
function je_render_pagination(int $page, int $total, int $perPage, string $baseUrl, string $paramKey = 'page', array $params = []): void
{
    if ($total <= $perPage) return;
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages <= 1) return;

    $build = function (int $p) use ($baseUrl, $paramKey, $params): string {
        $q = $params;
        $q[$paramKey] = $p;
        return htmlspecialchars($baseUrl . '?' . http_build_query($q));
    };

    $window = 1; // pages around current
    $pages  = [];
    $pages[] = 1;
    for ($i = max(2, $page - $window); $i <= min($totalPages - 1, $page + $window); $i++) {
        $pages[] = $i;
    }
    if ($totalPages > 1) $pages[] = $totalPages;
    $pages = array_unique($pages);
    sort($pages);

    ?>
    <div class="je-pagination">
        <?php if ($page > 1): ?>
            <a class="je-page-btn" href="<?= $build($page - 1) ?>">‹ Prev</a>
        <?php endif; ?>
        <?php $prev = 0; foreach ($pages as $p):
            if ($prev && $p - $prev > 1): ?>
                <span class="je-page-btn is-dots">…</span>
            <?php endif;
            $prev = $p; ?>
            <a class="je-page-btn <?= $p === $page ? 'is-active' : '' ?>" href="<?= $build($p) ?>"><?= $p ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?>
            <a class="je-page-btn" href="<?= $build($page + 1) ?>">Next ›</a>
        <?php endif; ?>
    </div>
    <?php
}

/* ──────────────────────────────────────────────────────────
   SORT ROW
   $sortOptions = [ 'newest' => 'Newest', 'price_low' => 'Price: Low → High', ... ]
   $current     – current sort key
   $params      – preserved query params
   $baseUrl     – form action
   ────────────────────────────────────────────────────────── */
function je_render_sort_row(array $sortOptions, string $current, array $params, string $baseUrl, string $paramKey = 'sort'): void
{
    ?>
    <form method="GET" action="<?= htmlspecialchars($baseUrl) ?>" style="display:inline-flex;align-items:center;gap:10px;">
        <?php foreach ($params as $k => $v): if ($v === '' || $v === null || $k === $paramKey) continue; ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
        <?php endforeach; ?>
        <span class="je-sort-label">Sort:</span>
        <select name="<?= htmlspecialchars($paramKey) ?>" class="je-sort-select" onchange="this.form.submit()">
            <?php foreach ($sortOptions as $k => $label): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $current === $k ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}

/* ──────────────────────────────────────────────────────────
   HERO SEARCH BAR (the black bar with inline search input)
   ────────────────────────────────────────────────────────── */
function je_render_hero_bar(string $title, string $placeholder, string $q, string $baseUrl, array $preserved = []): void
{
    ?>
    <div class="je-hero-bar">
        <div class="je-hero-title"><?= htmlspecialchars($title) ?></div>
        <form method="GET" action="<?= htmlspecialchars($baseUrl) ?>" class="je-hero-form" role="search">
            <?php foreach ($preserved as $k => $v): if ($k === 'q' || $v === '' || $v === null) continue; ?>
                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
            <?php endforeach; ?>
            <input type="text" name="q" class="je-hero-input"
                   placeholder="<?= htmlspecialchars($placeholder) ?>"
                   value="<?= htmlspecialchars((string)$q) ?>" autocomplete="off">
            <button type="submit" class="je-hero-btn">Search</button>
        </form>
    </div>
    <?php
}

/* ──────────────────────────────────────────────────────────
   LUXURY FOOTER
   ────────────────────────────────────────────────────────── */
function je_render_footer(string $variant = 'site'): void
{
    $year = date('Y');
    $loggedIn = !empty($_SESSION['user_id']);
    $role     = $_SESSION['user_role'] ?? null;
    ?>
    <footer class="je-footer">
        <div class="je-container">
            <div class="je-footer-grid">
                <div>
                    <div class="je-footer-brand">KINAS GROUP</div>
                    <div class="je-footer-tag">The World's Luxury Marketplace — Homes, Cars, Solar &amp; Curated Goods.</div>
                    <div class="je-footer-social" aria-label="Social media">
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
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
                        <li><a href="/blog/">Journal</a></li>
                        <li><a href="/pages/about.php#careers">Careers</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Support</h4>
                    <ul>
                        <li><a href="/pages/terms-of-use.php">Terms of Use</a></li>
                        <li><a href="/pages/privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="/pages/contact.php">Help Center</a></li>
                        <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li><a href="/admin/dashboard.php"><i class="fas fa-lock" style="color:#C6A43F;"></i> Admin Panel</a></li>
                        <?php else: ?>
                            <li><a href="/auth/login.php?redirect=/admin/dashboard.php" title="Administrator sign-in"><i class="fas fa-lock"></i> Admin Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4>Newsletter</h4>
                    <div class="je-footer-newsletter">
                        <form onsubmit="event.preventDefault(); alert('Thanks — we\\'ll be in touch.');">
                            <input type="email" placeholder="Your email address" required>
                            <button type="submit" class="je-btn je-btn-gold je-btn-block je-btn-sm">Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="je-footer-bottom">
                <div>© <?= $year ?> KINAS GROUP. All rights reserved.</div>
                <div class="je-footer-payments">
                    <span style="margin-right:8px;">Secure payments</span>
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-amex"></i>
                    <i class="fab fa-cc-stripe"></i>
                    <i class="fab fa-cc-paypal"></i>
                </div>
            </div>
        </div>
    </footer>
    <?php
}
