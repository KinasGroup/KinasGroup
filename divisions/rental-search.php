<?php
/**
 * KINAS AUTOMOBILE — Car Rental Search / Results
 * Mirrors search.php (Car Sales) in structure, but tailored for rental:
 *  - Sidebar: date range picker, location, car type, seats, features
 *  - Grid:    price shown as ₦X/day
 *  - URL:     rental-search.php
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

$db = Database::getInstance()->getConnection();

// ── Read & whitelist input
$q            = trim($_GET['q'] ?? '');
$brand        = trim($_GET['brand'] ?? '');
$body_type    = trim($_GET['body_type'] ?? '');
$transmission = trim($_GET['transmission'] ?? '');
$fuel_type    = trim($_GET['fuel_type'] ?? '');
$seats        = (int)($_GET['seats'] ?? 0);
$color        = trim($_GET['color'] ?? '');
$min_price    = trim($_GET['min_price'] ?? '');
$max_price    = trim($_GET['max_price'] ?? '');
$city         = trim($_GET['city'] ?? '');
$state        = trim($_GET['state'] ?? '');
$start_date   = trim($_GET['start_date'] ?? '');
$end_date     = trim($_GET['end_date'] ?? '');
$sort         = $_GET['sort'] ?? 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 12;
$offset       = ($page - 1) * $per_page;

// ── Validate dates
$rentalDays = 1;
if ($start_date && $end_date) {
    $sd = DateTime::createFromFormat('Y-m-d', $start_date);
    $ed = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($sd && $ed && $ed > $sd) {
        $rentalDays = max(1, (int)$sd->diff($ed)->days);
    }
}

// ── Build WHERE (rental listings only)
$where  = ["c.status = 'active'", "c.listing_type = 'rental'"];
$params = [];

if ($q !== '') {
    $where[] = "(c.title LIKE ? OR c.brand LIKE ? OR c.model LIKE ? OR c.description LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($brand        !== '') { $where[] = "c.brand = ?";           $params[] = $brand; }
if ($body_type    !== '') { $where[] = "c.body_type = ?";       $params[] = $body_type; }
if ($transmission !== '') { $where[] = "c.transmission = ?";    $params[] = $transmission; }
if ($fuel_type    !== '') { $where[] = "c.fuel_type = ?";       $params[] = $fuel_type; }
if ($seats > 0)           { $where[] = "c.seats >= ?";          $params[] = $seats; }
if ($color        !== '') { $where[] = "c.color = ?";           $params[] = $color; }
if ($min_price !== '' && is_numeric($min_price)) { $where[] = "c.price >= ?"; $params[] = $min_price; }
if ($max_price !== '' && is_numeric($max_price)) { $where[] = "c.price <= ?"; $params[] = $max_price; }
if ($city  !== '') { $where[] = "c.city = ?";  $params[] = $city; }
if ($state !== '') { $where[] = "c.state = ?"; $params[] = $state; }

// Availability: exclude cars already booked for the requested dates
if ($start_date && $end_date) {
    $where[] = "c.id NOT IN (
        SELECT car_id FROM car_rental_bookings
        WHERE status NOT IN ('cancelled','rejected')
          AND NOT (end_date <= ? OR start_date >= ?)
    )";
    $params[] = $start_date;
    $params[] = $end_date;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM car_listings c WHERE $whereSql");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per_page));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $per_page;

// Order
$orderBy = match ($sort) {
    'price_low'  => 'c.price ASC',
    'price_high' => 'c.price DESC',
    'seats_most' => 'c.seats DESC',
    default      => 'c.featured DESC, c.created_at DESC',
};

// Results
$sql = "
    SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.seats,
           c.transmission, c.fuel_type, c.color, c.body_type,
           c.featured, c.views,
           c.city, c.state, c.country,
           a.verified as agent_verified,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
    FROM car_listings c
    LEFT JOIN users a ON c.agent_id = a.id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

// Facets
$facet = function (string $col) use ($db): array {
    try {
        return $db->query("SELECT DISTINCT $col AS v FROM car_listings WHERE status='active' AND listing_type='rental' AND $col IS NOT NULL AND $col != '' ORDER BY $col")
                  ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { return []; }
};
$brands        = $facet('brand');
$body_types    = $facet('body_type');
$transmissions = $facet('transmission');
$fuel_types    = $facet('fuel_type');
$colors        = $facet('color');
$cities        = $facet('city');
$states        = $facet('state');

$pageTitle       = 'Luxury Car Rentals - KINAS Automobile';
$pageDescription = 'Rent premium luxury and exotic vehicles from verified KINAS Automobile dealers. Filter by date, car type, location, and more.';

include '../../templates/header.php';
?>

<!-- ── Page-level styles ───────────────────────────────────── -->
<style>
/* ══════════════════════════════════════════════════
   RENTAL DURATION — Custom Calendar Widget
   ══════════════════════════════════════════════════ */

/* Section header */
.krd-section-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #C6A43F;
    margin-bottom: 14px;
}

/* Date display pills — show selected dates / placeholders */
.krd-date-pills {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 14px;
}
.krd-pill {
    background: #f9f6ee;
    border: 1.5px solid #e8dfc0;
    border-radius: 4px;
    padding: 8px 10px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    min-width: 0;
}
.krd-pill.is-active {
    border-color: #C6A43F;
    background: #fff;
}
.krd-pill-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #C6A43F;
    margin-bottom: 3px;
}
.krd-pill-value {
    font-size: 12px;
    font-weight: 600;
    color: #0A0A0A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.krd-pill-value.placeholder {
    color: #aaa;
    font-weight: 400;
}

/* Duration summary bar */
.krd-summary {
    display: none;
    align-items: center;
    gap: 6px;
    background: rgba(198,164,63,0.10);
    border: 1px solid rgba(198,164,63,0.30);
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 14px;
    font-size: 12px;
    font-weight: 600;
    color: #C6A43F;
}
.krd-summary.is-visible { display: flex; }

/* Calendar widget container */
.krd-calendar-wrap {
    display: none;
    background: #fff;
    border: 1.5px solid #e0d5b0;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 4px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.krd-calendar-wrap.is-open { display: block; }

/* Calendar nav bar */
.krd-cal-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 8px;
    background: #0A0A0A;
    color: #fff;
}
.krd-cal-nav-btn {
    background: none;
    border: none;
    color: #C6A43F;
    font-size: 16px;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 3px;
    line-height: 1;
}
.krd-cal-nav-btn:hover { background: rgba(198,164,63,0.15); }
.krd-cal-month-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #fff;
}

/* Calendar grid */
.krd-cal-grid {
    padding: 8px 10px 12px;
}
.krd-cal-dow {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 4px;
}
.krd-cal-dow span {
    text-align: center;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #aaa;
    text-transform: uppercase;
    padding: 4px 0;
}
.krd-cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}
.krd-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 500;
    color: #333;
    border-radius: 3px;
    cursor: pointer;
    border: none;
    background: none;
    padding: 0;
    transition: background .1s, color .1s;
}
.krd-day:hover:not(.disabled):not(.empty) {
    background: rgba(198,164,63,0.15);
    color: #0A0A0A;
}
.krd-day.empty { cursor: default; }
.krd-day.disabled {
    color: #ccc;
    cursor: not-allowed;
}
.krd-day.in-range {
    background: rgba(198,164,63,0.12);
    border-radius: 0;
}
.krd-day.range-start,
.krd-day.range-end {
    background: #C6A43F !important;
    color: #0A0A0A !important;
    font-weight: 700;
    border-radius: 3px;
}
.krd-day.range-start { border-radius: 3px 0 0 3px; }
.krd-day.range-end   { border-radius: 0 3px 3px 0; }
.krd-day.today {
    border: 1.5px solid #C6A43F;
    color: #C6A43F;
    font-weight: 600;
}

/* Picking-mode label at top of calendar */
.krd-picking-hint {
    text-align: center;
    font-size: 10px;
    color: #C6A43F;
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 6px 0 2px;
    text-transform: uppercase;
}

/* Hidden real inputs (carry values on form submit) */
.krd-hidden { display: none; }

/* Duration badge */
.kinas-rental-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #C6A43F;
    color: #0A0A0A;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 2px;
}
.kinas-rental-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #C6A43F;
    color: #0A0A0A;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 2px;
}
.kinas-per-day {
    font-size: 11px;
    color: #888;
    font-weight: 400;
    margin-left: 2px;
}
/* override card price for rental display */
.je-card-price .per-day-suffix {
    font-size: 11px;
    font-weight: 400;
    color: #888;
}
.kinas-total-cost {
    font-size: 11px;
    color: #666;
    margin-top: 2px;
}
/* Hero rental strip override */
.kinas-rental-hero-bar {
    background: linear-gradient(135deg, #0A0A0A 0%, #1a1400 100%);
    padding: 28px 0;
    border-bottom: 1px solid rgba(198,164,63,0.2);
}
.kinas-rental-hero-eyebrow {
    font-size: 10px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #C6A43F;
    font-weight: 600;
    margin-bottom: 6px;
}
.kinas-rental-hero-title {
    font-family: 'Prata', serif;
    font-size: 26px;
    color: #fff;
    font-weight: 400;
    margin-bottom: 16px;
}
.kinas-rental-hero-search {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.kinas-rental-hero-input {
    flex: 1;
    min-width: 200px;
    padding: 13px 18px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 3px;
    color: #fff;
    font-family: Inter, sans-serif;
    font-size: 14px;
}
.kinas-rental-hero-input::placeholder { color: rgba(255,255,255,0.45); }
.kinas-rental-hero-input:focus { outline: none; border-color: #C6A43F; }
</style>

<style>
.ka-mode-tabs {
    display: flex;
    border-bottom: 2px solid rgba(198,164,63,0.18);
    background: var(--je-surface, #fff);
    padding: 0 24px;
}
.ka-mode-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 16px 22px;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #888;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: color .2s, border-color .2s;
}
.ka-mode-tab:hover { color: #C6A43F; }
.ka-mode-tab.is-active {
    color: #C6A43F;
    border-bottom-color: #C6A43F;
}
</style>

<div class="je-search-page">

<!-- ── Hero bar ── -->
<div class="kinas-rental-hero-bar">
    <div class="je-container">
        <div class="kinas-rental-hero-eyebrow">KINAS AUTOMOBILE — RENTALS</div>
        <div class="kinas-rental-hero-title">Luxury Car Rentals</div>
        <form method="GET" action="rental-search.php" class="kinas-rental-hero-search" role="search">
            <?php
            $preserved = compact('brand','body_type','transmission','fuel_type','seats','color','min_price','max_price','city','state','start_date','end_date','sort');
            foreach ($preserved as $k => $v) {
                if ($v === '' || $v === null || $v == 0) continue;
                echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string)$v) . '">';
            }
            ?>
            <input type="text" name="q" class="kinas-rental-hero-input"
                   placeholder="Search by brand, model, keyword…"
                   value="<?= htmlspecialchars($q) ?>" autocomplete="off">
            <button type="submit" class="je-btn je-btn-gold" style="padding:13px 28px;">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
    </div>
</div>

<div class="ka-mode-tabs">
    <a href="search.php" class="ka-mode-tab">
        <i class="fas fa-tag"></i> Car Sales
    </a>
    <a href="rental-search.php" class="ka-mode-tab is-active">
        <i class="fas fa-key"></i> Car Rentals
    </a>
</div>

<div class="je-search-body">

    <!-- ── Filter sidebar ── -->
    <aside class="je-filter-panel" id="je-rental-filter-panel">
        <form method="GET" action="rental-search.php" id="je-rental-filter-form">
            <?php if ($q !== ''): ?>
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
            <?php endif; ?>
            <?php if ($sort !== 'newest'): ?>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <?php endif; ?>

            <!-- ── RENTAL DURATION — Custom Calendar ── -->
            <div class="je-filter-section" style="padding-bottom:0;">

                <!-- Hidden real inputs submitted with the form -->
                <input type="hidden" name="start_date" id="krd_start" class="krd-hidden"
                       value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date"   id="krd_end"   class="krd-hidden"
                       value="<?= htmlspecialchars($end_date) ?>">

                <div class="krd-section-head">
                    <i class="fas fa-calendar-alt"></i> Rental Duration
                </div>

                <!-- Date pills -->
                <div class="krd-date-pills">
                    <div class="krd-pill" id="krd-pill-start" onclick="krdOpenPicking('start')">
                        <div class="krd-pill-label">Pick-up</div>
                        <div class="krd-pill-value <?= $start_date ? '' : 'placeholder' ?>" id="krd-pill-start-val">
                            <?= $start_date ? date('D, M j', strtotime($start_date)) : 'Select date' ?>
                        </div>
                    </div>
                    <div class="krd-pill" id="krd-pill-end" onclick="krdOpenPicking('end')">
                        <div class="krd-pill-label">Return</div>
                        <div class="krd-pill-value <?= $end_date ? '' : 'placeholder' ?>" id="krd-pill-end-val">
                            <?= $end_date ? date('D, M j', strtotime($end_date)) : 'Select date' ?>
                        </div>
                    </div>
                </div>

                <!-- Duration summary (shown once both dates selected) -->
                <div class="krd-summary <?= ($start_date && $end_date) ? 'is-visible' : '' ?>" id="krd-summary">
                    <i class="fas fa-moon"></i>
                    <span id="krd-summary-text">
                        <?php if ($start_date && $end_date && $rentalDays > 0): ?>
                            <?= $rentalDays ?> night<?= $rentalDays !== 1 ? 's' : '' ?>
                            &nbsp;·&nbsp;
                            <?= date('M j', strtotime($start_date)) ?> – <?= date('M j', strtotime($end_date)) ?>
                        <?php endif; ?>
                    </span>
                    <button type="button" onclick="krdClearDates()"
                            style="margin-left:auto;background:none;border:none;color:#C6A43F;cursor:pointer;font-size:13px;">✕</button>
                </div>

                <!-- Inline calendar -->
                <div class="krd-calendar-wrap <?= ($start_date || !$start_date) ? 'is-open' : '' ?>"
                     id="krd-calendar">
                    <div class="krd-picking-hint" id="krd-hint">
                        <?= $start_date && !$end_date ? 'Select return date' : 'Select pick-up date' ?>
                    </div>
                    <div class="krd-cal-nav">
                        <button type="button" class="krd-cal-nav-btn" onclick="krdPrevMonth()">&#8249;</button>
                        <span class="krd-cal-month-label" id="krd-month-label"></span>
                        <button type="button" class="krd-cal-nav-btn" onclick="krdNextMonth()">&#8250;</button>
                    </div>
                    <div class="krd-cal-grid">
                        <div class="krd-cal-dow">
                            <span>Su</span><span>Mo</span><span>Tu</span>
                            <span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                        </div>
                        <div class="krd-cal-days" id="krd-days"></div>
                    </div>
                </div>

            </div>

            <!-- ── Location ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">City</span>
                <select name="city" class="je-filter-select">
                    <option value="">Any City</option>
                    <?php foreach ($cities as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $city === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="je-filter-section">
                <span class="je-filter-label">State</span>
                <select name="state" class="je-filter-select">
                    <option value="">Any State</option>
                    <?php foreach ($states as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $state === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Vehicle Type ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Vehicle Type</span>
                <select name="body_type" class="je-filter-select">
                    <option value="">Any Type</option>
                    <?php foreach ($body_types as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $body_type === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Brand ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Brand</span>
                <select name="brand" class="je-filter-select">
                    <option value="">Any Brand</option>
                    <?php foreach ($brands as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $brand === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Price Per Day ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Price per Day (₦)</span>
                <div class="je-price-row">
                    <input type="text" name="min_price" class="je-filter-input" placeholder="Min"
                           value="<?= htmlspecialchars($min_price) ?>">
                    <input type="text" name="max_price" class="je-filter-input" placeholder="Max"
                           value="<?= htmlspecialchars($max_price) ?>">
                </div>
            </div>

            <!-- ── Minimum Seats ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Minimum Seats</span>
                <select name="seats" class="je-filter-select">
                    <option value="0">Any</option>
                    <?php foreach ([2, 4, 5, 6, 7, 8] as $s): ?>
                        <option value="<?= $s ?>" <?= $seats === $s ? 'selected' : '' ?>>
                            <?= $s ?>+ seats
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Transmission ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Transmission</span>
                <select name="transmission" class="je-filter-select">
                    <option value="">Any</option>
                    <?php foreach ($transmissions as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $transmission === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Fuel Type ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Fuel Type</span>
                <select name="fuel_type" class="je-filter-select">
                    <option value="">Any</option>
                    <?php foreach ($fuel_types as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $fuel_type === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Color ── -->
            <div class="je-filter-section">
                <span class="je-filter-label">Exterior Color</span>
                <select name="color" class="je-filter-select">
                    <option value="">Any Color</option>
                    <?php foreach ($colors as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>" <?= $color === $opt ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Actions ── -->
            <div class="je-filter-section">
                <button type="submit" class="je-filter-apply-btn">
                    <i class="fas fa-search" style="margin-right:6px;"></i>Apply Filters
                </button>
                <a href="rental-search.php" class="je-filter-clear">Clear all filters</a>
            </div>
        </form>
    </aside>

    <!-- ── Results panel ── -->
    <div class="je-results-panel">
        <div class="je-results-topbar">
            <div class="je-results-count">
                <strong><?= number_format($total) ?></strong> <?= $total === 1 ? 'rental vehicle' : 'rental vehicles' ?> available
                <?php if ($q): ?>for "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
                <?php if ($start_date && $end_date): ?>
                    <span style="color:#C6A43F; font-size:12px; margin-left:8px;">
                        <?= htmlspecialchars(date('M j', strtotime($start_date))) ?> – <?= htmlspecialchars(date('M j, Y', strtotime($end_date))) ?>
                        (<?= $rentalDays ?> day<?= $rentalDays !== 1 ? 's' : '' ?>)
                    </span>
                <?php endif; ?>
            </div>
            <div class="je-flex" style="gap:16px;">
                <button class="je-mobile-filter-btn"
                        onclick="document.getElementById('jeMobileRentalFilter').classList.add('is-open')">
                    <i class="fas fa-sliders-h"></i> Filters
                </button>
                <?php
                $sortOptions = [
                    'newest'     => 'Newest first',
                    'price_low'  => 'Price: Low → High',
                    'price_high' => 'Price: High → Low',
                    'seats_most' => 'Most seats first',
                ];
                $sortPreserved = compact('brand','body_type','transmission','fuel_type','seats','color','min_price','max_price','city','state','start_date','end_date');
                if ($q !== '') $sortPreserved['q'] = $q;
                je_render_sort_row($sortOptions, $sort, $sortPreserved, 'rental-search.php');
                ?>
            </div>
        </div>

        <?php if (empty($cars)): ?>
            <div class="je-empty">
                <div class="je-empty-icon">🚗</div>
                <div class="je-empty-title">No rental vehicles available</div>
                <div class="je-empty-text">
                    <?php if ($start_date && $end_date): ?>
                        No vehicles are available for your selected dates. Try different dates or widen your filters.
                    <?php else: ?>
                        Try adjusting your filters or check back soon.
                    <?php endif; ?>
                </div>
                <a href="rental-search.php" class="je-empty-btn">Clear filters</a>
            </div>
        <?php else: ?>
            <div class="je-listings-grid">
                <?php foreach ($cars as $c):
                    $specParts = array_filter([
                        $c['year']         ?? null,
                        $c['transmission'] ?? null,
                        $c['fuel_type']    ?? null,
                        $c['body_type']    ?? null,
                        ($c['seats']       ?? null) ? ($c['seats'] . ' seats') : null,
                    ]);
                    $locParts = array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country'] ?? null]);
                    $thumb    = $c['thumbnail'] ?? '';
                    $title    = trim(($c['brand'] ?? '') . ' ' . ($c['model'] ?? '') . ' ' . ($c['year'] ?? ''));
                    $priceDay = (float)($c['price'] ?? 0);
                    $isFeat   = !empty($c['featured']);
                    $isVerif  = !empty($c['agent_verified']);
                    $totalCost = $rentalDays > 1 ? $priceDay * $rentalDays : null;
                ?>
                <a class="je-card" href="rental-detail.php?id=<?= (int)$c['id'] ?><?= $start_date ? '&start_date=' . urlencode($start_date) : '' ?><?= $end_date ? '&end_date=' . urlencode($end_date) : '' ?>">
                    <div class="je-card-img" style="position:relative;">
                        <?php if ($thumb): ?>
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
                        <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#C6A43F;font-size:36px;">
                                <i class="fas fa-car"></i>
                            </div>
                        <?php endif; ?>
                        <span class="kinas-rental-badge"><i class="fas fa-key"></i> FOR RENT</span>
                        <?php if ($isFeat): ?>
                            <span class="je-card-badge">Featured</span>
                        <?php elseif ($isVerif): ?>
                            <span class="je-card-verified-badge">Verified</span>
                        <?php endif; ?>
                        <button type="button" class="je-card-fav" onclick="event.preventDefault();">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="je-card-body">
                        <div class="je-card-eyebrow">KINAS AUTOMOBILE — RENTAL</div>
                        <div class="je-card-title"><?= htmlspecialchars($title) ?></div>
                        <?php if ($specParts): ?>
                            <div class="je-card-specs"><?= htmlspecialchars(implode(' • ', $specParts)) ?></div>
                        <?php endif; ?>
                        <?php if ($locParts): ?>
                            <div class="je-card-location">
                                <i class="fas fa-map-marker-alt" style="color:#C6A43F"></i>
                                <?= htmlspecialchars(implode(', ', $locParts)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="je-card-bottom">
                            <div>
                                <div class="je-card-price">
                                    <?= function_exists('formatPrice') ? formatPrice($priceDay) : '₦' . number_format($priceDay) ?>
                                    <span class="per-day-suffix">/day</span>
                                </div>
                                <?php if ($totalCost): ?>
                                    <div class="kinas-total-cost">
                                        Total: <?= function_exists('formatPrice') ? formatPrice($totalCost) : '₦' . number_format($totalCost) ?>
                                        for <?= $rentalDays ?> days
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($c['views'])): ?>
                                <div class="je-card-views">
                                    <i class="far fa-eye"></i> <?= number_format((int)$c['views']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php
            $paginateParams = compact('brand','body_type','transmission','fuel_type','seats','color','min_price','max_price','city','state','start_date','end_date','sort');
            if ($q !== '') $paginateParams['q'] = $q;
            je_render_pagination($page, $total, $per_page, 'rental-search.php', 'page', $paginateParams);
            ?>
        <?php endif; ?>
    </div><!-- /.je-results-panel -->
</div><!-- /.je-search-body -->

<!-- ── Mobile filter overlay ── -->
<div class="je-filter-overlay" id="jeMobileRentalFilter"
     onclick="if(event.target===this) this.classList.remove('is-open')">
    <div class="je-filter-overlay-inner">
        <button class="je-filter-overlay-close"
                onclick="document.getElementById('jeMobileRentalFilter').classList.remove('is-open')">✕</button>
        <!-- Clone of sidebar form for mobile -->
        <aside class="je-filter-panel" style="width:100%;min-width:0;border-right:none;">
            <form method="GET" action="rental-search.php">
                <?php if ($q !== ''): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                <?php endif; ?>
                <?php if ($sort !== 'newest'): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>

                <!-- Mobile: re-uses the same krd_ widget; just show native inputs as fallback -->
                <div class="je-filter-section" style="padding-bottom:0;">
                    <input type="hidden" name="start_date" id="krd_start_m" class="krd-hidden"
                           value="<?= htmlspecialchars($start_date) ?>">
                    <input type="hidden" name="end_date"   id="krd_end_m"   class="krd-hidden"
                           value="<?= htmlspecialchars($end_date) ?>">
                    <div class="krd-section-head">
                        <i class="fas fa-calendar-alt"></i> Rental Duration
                    </div>
                    <div class="krd-date-pills">
                        <div class="krd-pill" onclick="krdOpenPicking('start')">
                            <div class="krd-pill-label">Pick-up</div>
                            <div class="krd-pill-value <?= $start_date ? '' : 'placeholder' ?>" id="krd-pill-start-val-m">
                                <?= $start_date ? date('D, M j', strtotime($start_date)) : 'Select date' ?>
                            </div>
                        </div>
                        <div class="krd-pill" onclick="krdOpenPicking('end')">
                            <div class="krd-pill-label">Return</div>
                            <div class="krd-pill-value <?= $end_date ? '' : 'placeholder' ?>" id="krd-pill-end-val-m">
                                <?= $end_date ? date('D, M j', strtotime($end_date)) : 'Select date' ?>
                            </div>
                        </div>
                    </div>
                    <div class="krd-summary <?= ($start_date && $end_date) ? 'is-visible' : '' ?>" id="krd-summary-m">
                        <i class="fas fa-moon"></i>
                        <span id="krd-summary-text-m">
                            <?php if ($start_date && $end_date && $rentalDays > 0): ?>
                                <?= $rentalDays ?> night<?= $rentalDays !== 1 ? 's' : '' ?>
                                &nbsp;·&nbsp;
                                <?= date('M j', strtotime($start_date)) ?> – <?= date('M j', strtotime($end_date)) ?>
                            <?php endif; ?>
                        </span>
                        <button type="button" onclick="krdClearDates()"
                                style="margin-left:auto;background:none;border:none;color:#C6A43F;cursor:pointer;font-size:13px;">✕</button>
                    </div>
                    <div class="krd-calendar-wrap is-open" id="krd-calendar-m">
                        <div class="krd-picking-hint" id="krd-hint-m">Select pick-up date</div>
                        <div class="krd-cal-nav">
                            <button type="button" class="krd-cal-nav-btn" onclick="krdPrevMonth()">&#8249;</button>
                            <span class="krd-cal-month-label" id="krd-month-label-m"></span>
                            <button type="button" class="krd-cal-nav-btn" onclick="krdNextMonth()">&#8250;</button>
                        </div>
                        <div class="krd-cal-grid">
                            <div class="krd-cal-dow">
                                <span>Su</span><span>Mo</span><span>Tu</span>
                                <span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                            </div>
                            <div class="krd-cal-days" id="krd-days-m"></div>
                        </div>
                    </div>
                </div>

                <div class="je-filter-section">
                    <span class="je-filter-label">City</span>
                    <select name="city" class="je-filter-select">
                        <option value="">Any City</option>
                        <?php foreach ($cities as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $city === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">State</span>
                    <select name="state" class="je-filter-select">
                        <option value="">Any State</option>
                        <?php foreach ($states as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $state === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Vehicle Type</span>
                    <select name="body_type" class="je-filter-select">
                        <option value="">Any Type</option>
                        <?php foreach ($body_types as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $body_type === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Brand</span>
                    <select name="brand" class="je-filter-select">
                        <option value="">Any Brand</option>
                        <?php foreach ($brands as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $brand === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Price per Day (₦)</span>
                    <div class="je-price-row">
                        <input type="text" name="min_price" class="je-filter-input" placeholder="Min" value="<?= htmlspecialchars($min_price) ?>">
                        <input type="text" name="max_price" class="je-filter-input" placeholder="Max" value="<?= htmlspecialchars($max_price) ?>">
                    </div>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Min Seats</span>
                    <select name="seats" class="je-filter-select">
                        <option value="0">Any</option>
                        <?php foreach ([2,4,5,6,7,8] as $s): ?>
                            <option value="<?= $s ?>" <?= $seats === $s ? 'selected' : '' ?>><?= $s ?>+ seats</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Transmission</span>
                    <select name="transmission" class="je-filter-select">
                        <option value="">Any</option>
                        <?php foreach ($transmissions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $transmission === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <span class="je-filter-label">Fuel Type</span>
                    <select name="fuel_type" class="je-filter-select">
                        <option value="">Any</option>
                        <?php foreach ($fuel_types as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $fuel_type === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="je-filter-section">
                    <button type="submit" class="je-filter-apply-btn">Apply Filters</button>
                    <a href="rental-search.php" class="je-filter-clear">Clear all</a>
                </div>
            </form>
        </aside>
    </div>
</div>

</div><!-- /.je-search-page -->

<script>
/* ══════════════════════════════════════════════════════
   KRD — KINAS Rental Duration Calendar Engine
   Single JS object drives both desktop + mobile calendars
   ══════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* ── State ── */
    var startDate  = null;   // Date object or null
    var endDate    = null;   // Date object or null
    var picking    = 'start'; // 'start' | 'end'
    var viewYear   = 0;
    var viewMonth  = 0;      // 0-based

    var MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
    var today  = new Date(); today.setHours(0,0,0,0);

    /* ── Helpers ── */
    function toYMD(d) {
        if (!d) return '';
        var y = d.getFullYear(),
            m = String(d.getMonth()+1).padStart(2,'0'),
            dd = String(d.getDate()).padStart(2,'0');
        return y+'-'+m+'-'+dd;
    }
    function fmtDisplay(d) {
        if (!d) return '';
        var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        return days[d.getDay()]+', '+MONTHS[d.getMonth()].slice(0,3)+' '+d.getDate();
    }
    function diffDays(a, b) {
        return Math.round((b - a) / 86400000);
    }
    function sameDay(a, b) {
        return a && b && toYMD(a) === toYMD(b);
    }

    /* ── DOM helpers (works for both desktop + mobile twins) ── */
    function setEl(id, prop, val) {
        var el = document.getElementById(id);
        if (el) el[prop] = val;
    }
    function setClass(id, cls, on) {
        var el = document.getElementById(id);
        if (!el) return;
        if (on) el.classList.add(cls);
        else    el.classList.remove(cls);
    }

    /* ── Update all UI elements to reflect current state ── */
    function render() {
        /* Hidden form inputs */
        ['krd_start','krd_start_m'].forEach(function(id){
            setEl(id, 'value', toYMD(startDate));
        });
        ['krd_end','krd_end_m'].forEach(function(id){
            setEl(id, 'value', toYMD(endDate));
        });

        /* Pick-up pills */
        var startTxt = startDate ? fmtDisplay(startDate) : 'Select date';
        var startPh  = !startDate;
        ['krd-pill-start-val','krd-pill-start-val-m'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = startTxt;
            el.classList.toggle('placeholder', startPh);
        });

        /* Return pills */
        var endTxt = endDate ? fmtDisplay(endDate) : 'Select date';
        var endPh  = !endDate;
        ['krd-pill-end-val','krd-pill-end-val-m'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = endTxt;
            el.classList.toggle('placeholder', endPh);
        });

        /* Pill active states */
        setClass('krd-pill-start', 'is-active', picking === 'start');
        setClass('krd-pill-end',   'is-active', picking === 'end');

        /* Summary bar */
        var showSummary = !!(startDate && endDate);
        var nights = showSummary ? diffDays(startDate, endDate) : 0;
        var summaryHtml = showSummary
            ? nights + (nights === 1 ? ' night' : ' nights') + ' &nbsp;·&nbsp; '
              + MONTHS[startDate.getMonth()].slice(0,3) + ' ' + startDate.getDate()
              + ' – ' + MONTHS[endDate.getMonth()].slice(0,3) + ' ' + endDate.getDate()
            : '';

        ['krd-summary','krd-summary-m'].forEach(function(id){
            var el = document.getElementById(id);
            if (!el) return;
            el.classList.toggle('is-visible', showSummary);
            var txt = el.querySelector('span');
            if (txt) txt.innerHTML = summaryHtml;
        });

        /* Hint text */
        var hint = picking === 'start' ? 'Select pick-up date' : 'Select return date';
        ['krd-hint','krd-hint-m'].forEach(function(id){
            setEl(id,'textContent', hint);
        });

        /* Month label */
        var lbl = MONTHS[viewMonth] + ' ' + viewYear;
        ['krd-month-label','krd-month-label-m'].forEach(function(id){
            setEl(id,'textContent', lbl);
        });

        /* Build day grids */
        ['krd-days','krd-days-m'].forEach(function(gridId){
            buildGrid(gridId);
        });
    }

    function buildGrid(gridId) {
        var grid = document.getElementById(gridId);
        if (!grid) return;
        grid.innerHTML = '';

        var firstDay = new Date(viewYear, viewMonth, 1).getDay(); // 0=Sun
        var daysInMonth = new Date(viewYear, viewMonth+1, 0).getDate();

        /* Empty cells before day 1 */
        for (var i = 0; i < firstDay; i++) {
            var emp = document.createElement('button');
            emp.type = 'button';
            emp.className = 'krd-day empty';
            emp.disabled = true;
            grid.appendChild(emp);
        }

        for (var d = 1; d <= daysInMonth; d++) {
            var thisDate = new Date(viewYear, viewMonth, d);
            thisDate.setHours(0,0,0,0);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = d;
            btn.className = 'krd-day';

            /* Disabled: past dates; also can't pick end <= start */
            var isPast = thisDate < today;
            var isBeforeStart = picking === 'end' && startDate && thisDate <= startDate;
            if (isPast || isBeforeStart) {
                btn.classList.add('disabled');
                btn.disabled = true;
            }

            /* Today marker */
            if (sameDay(thisDate, today)) btn.classList.add('today');

            /* Range highlighting */
            if (startDate && endDate) {
                if (sameDay(thisDate, startDate)) btn.classList.add('range-start');
                else if (sameDay(thisDate, endDate)) btn.classList.add('range-end');
                else if (thisDate > startDate && thisDate < endDate) btn.classList.add('in-range');
            } else if (startDate && sameDay(thisDate, startDate)) {
                btn.classList.add('range-start');
            }

            /* Click handler — closure over thisDate */
            (function(dt){
                btn.addEventListener('click', function(){ krdSelectDay(dt); });
            })(new Date(thisDate));

            grid.appendChild(btn);
        }
    }

    /* ── Public: open picking mode ── */
    window.krdOpenPicking = function(mode) {
        picking = mode;
        /* If re-picking start, clear end too */
        if (mode === 'start') endDate = null;
        render();
    };

    /* ── Public: prev / next month ── */
    window.krdPrevMonth = function() {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        render();
    };
    window.krdNextMonth = function() {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        render();
    };

    /* ── Public: clear dates ── */
    window.krdClearDates = function() {
        startDate = null; endDate = null; picking = 'start';
        render();
    };

    /* ── Select a day ── */
    function krdSelectDay(dt) {
        if (picking === 'start') {
            startDate = dt;
            endDate   = null;
            picking   = 'end';
        } else {
            if (dt <= startDate) {
                /* Clicked before/on start — restart */
                startDate = dt;
                endDate   = null;
                picking   = 'end';
            } else {
                endDate = dt;
                picking = 'start'; /* both set, reset mode */
            }
        }
        render();
    }

    /* ── Init ── */
    function init() {
        var now = new Date();
        viewYear  = now.getFullYear();
        viewMonth = now.getMonth();

        /* Pre-fill from server-rendered values */
        var si = document.getElementById('krd_start');
        var ei = document.getElementById('krd_end');
        if (si && si.value) {
            var p = si.value.split('-');
            startDate = new Date(+p[0], +p[1]-1, +p[2]);
            startDate.setHours(0,0,0,0);
            picking = 'end';
            if (startDate.getFullYear() === viewYear ? false : true) {
                viewYear = startDate.getFullYear();
                viewMonth = startDate.getMonth();
            } else {
                viewMonth = startDate.getMonth();
            }
        }
        if (ei && ei.value) {
            var p2 = ei.value.split('-');
            endDate = new Date(+p2[0], +p2[1]-1, +p2[2]);
            endDate.setHours(0,0,0,0);
            picking = 'start';
        }
        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<?php include '../../templates/footer.php'; ?>
