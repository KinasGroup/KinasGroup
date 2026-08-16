<?php
/**
* KINAS BUILD: 2026.08.15.08
* FILE: divisions/kinas-automobile/detail.php
*
* KINAS AUTOMOBILE — Listing detail page
*
* RESTORED FEATURES:
* - Full car details panel.
* - Rental booking widget for rental listings.
* - Schedule viewing.
* - Paid inspection fee / Paystack flow.
* - Inline contact agent modal.
* - Save/favourite button.
* - Finance calculator.
* - Map.
* - Features & equipment.
* - Similar cars.
* - Owner/admin preview for non-active listings.
*
* VISIBILITY:
* - Restored to original behaviour:
*   only active listings are public.
* - Pending/flagged/sold/removed listings are only visible
*   to the listing owner or admin.
*
* AMENDED:
* - Public display now prefers the agent's username (@username)
*   where available, while preserving all original functionality.
*/
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/helpers.php';
require_once '../../includes/public-identity.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';

// CRITICAL: Include security BEFORE any HTML output.
require_once '../../includes/security.php';

$id = (int)($_GET['id'] ?? 0);

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
SELECT c.*,
       a.verified AS agent_verified,
       a.name AS agent_name,
       a.username AS agent_username,
       a.email AS agent_email,
       a.phone AS agent_phone,
       ap.company_name AS agent_company,
       ap.avatar AS agent_avatar
FROM car_listings c
LEFT JOIN users a ON c.agent_id = a.id
LEFT JOIN agent_profiles ap ON a.id = ap.user_id
WHERE c.id = ?
");

$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// Non-active listings are only visible to the agent who owns them
// or an admin previewing before approval.
$isOwnerOrAdmin = $item && SessionManager::isLoggedIn()
    && ((int)$item['agent_id'] === SessionManager::getUserId() || SessionManager::getUserRole() === 'admin');

if (!$item || ($item['status'] !== 'active' && !$isOwnerOrAdmin)) {
    http_response_code(404);
    include __DIR__ . '/../../pages/404.php';
    exit;
}

$isPreview = $item['status'] !== 'active';

if (!$isPreview) {
    try {
        $db->prepare("UPDATE car_listings SET views = views + 1 WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) {
        // Views column may not exist on all schemas.
    }
}

$images = $db->prepare("
SELECT *
FROM listing_images
WHERE listing_id = ?
AND listing_type = 'car'
ORDER BY sort_order
");

$images->execute([$id]);
$images = $images->fetchAll(PDO::FETCH_ASSOC);

$similar = $db->prepare("
SELECT c.id, c.title, c.brand, c.model, c.year, c.price,
(
    SELECT url
    FROM listing_images
    WHERE listing_id = c.id
    AND listing_type = 'car'
    ORDER BY sort_order
    LIMIT 1
) AS thumbnail
FROM car_listings c
WHERE c.id != ?
AND c.status = 'active'
AND (c.brand = ? OR c.body_type = ?)
ORDER BY c.featured DESC, c.created_at DESC
LIMIT 4
");

$similar->execute([
    $id,
    (string)($item['brand'] ?? ''),
    (string)($item['body_type'] ?? ''),
]);

$similar = $similar->fetchAll(PDO::FETCH_ASSOC) ?: [];

$features = [];

if (!empty($item['features'])) {
    $features = is_array($item['features'])
        ? $item['features']
        : (json_decode((string)$item['features'], true) ?: []);
}

$pageTitle = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['year'] ?? '')) . ' - KINAS AUTOMOBILE';

$pageDescription = !empty($item['description'])
    ? substr(strip_tags($item['description']), 0, 160)
    : trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['year'] ?? '')) . ' on KINAS Automobile.';

if (!empty($images[0]['url'])) {
    $pageImage = $images[0]['url'];
}

$division = 'car';

include '../../templates/header.php';

$locParts = array_filter([
    $item['city'] ?? null,
    $item['state'] ?? null,
    $item['country'] ?? null,
]);

$location = implode(', ', $locParts);

$addressParts = array_filter([
    $item['address'] ?? null,
    $item['city'] ?? null,
    $item['state'] ?? null,
    $item['country'] ?? null,
]);

$fullAddress = implode(', ', $addressParts);

$listingId = (int)$item['id'];
$agentId = (int)$item['agent_id'];

$agentNameRaw = $item['agent_name'] ?? 'Agent';
$agentUsername = (string)($item['agent_username'] ?? '');

$agentDisplayName = function_exists('kinas_public_display_name')
    ? kinas_public_display_name($agentUsername !== '' ? $agentUsername : null, $agentNameRaw)
    : ($agentUsername !== '' ? '@' . $agentUsername : $agentNameRaw);

$agentInitialSource = ltrim($agentDisplayName, '@');

$listingTitleRaw = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['year'] ?? '')) ?: ($item['title'] ?? 'Car');

$agentVerified = !empty($item['agent_verified']);
$inspectionFee = (float)($item['inspection_fee'] ?? 0);
?>
<div class="je-page">
    <div class="je-detail-wrap">

        <?php if ($isPreview): ?>
        <div style="background:#FFF8E1; border:1px solid #F0C419; color:#7A5B00; padding:14px 18px; border-radius:4px; margin-bottom:20px; font-size:14px;">
            <i class="fas fa-eye"></i>
            <strong>Preview only</strong> — this listing is <?= htmlspecialchars(ucfirst((string)$item['status'])) ?> and not visible to the public yet.
        </div>
        <?php endif; ?>

        <div class="je-breadcrumb">
            <a href="/">Home</a>
            <span class="je-breadcrumb-sep">/</span>
            <a href="/divisions/kinas-automobile/">KINAS AUTOMOBILE</a>
            <span class="je-breadcrumb-sep">/</span>
            <a href="/divisions/kinas-automobile/search.php">Search</a>
            <span class="je-breadcrumb-sep">/</span>
            <span><?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?></span>
        </div>

        <div class="je-detail-grid">

            <!-- Gallery -->
            <div>
                <?php if (empty($images)): ?>
                    <div class="je-gallery-main" style="background:linear-gradient(135deg,#1a1a1a,#0a0a0a); display:flex; align-items:center; justify-content:center; color:#C6A43F; font-size:64px;">
                        <i class="fas fa-car"></i>
                    </div>
                <?php else: ?>
                    <div class="je-gallery-main" id="jeGalleryMain">
                        <img id="jeMainImage"
                             src="<?= htmlspecialchars($images[0]['url']) ?>"
                             alt="<?= htmlspecialchars($item['title'] ?? 'Car') ?>"
                             onerror="this.onerror=null; this.src='/assets/images/placeholder/car-placeholder.svg';">

                        <?php if (!empty($item['featured'])): ?>
                            <span class="je-card-badge" style="top:16px;left:16px;">Featured</span>
                        <?php endif; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="je-gallery-thumbs">
                            <?php foreach ($images as $idx => $img): ?>
                                <div class="je-gallery-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                                     onclick="document.getElementById('jeMainImage').src='<?= htmlspecialchars($img['url']) ?>';
                                              document.querySelectorAll('.je-gallery-thumb').forEach(t=>t.classList.remove('is-active'));
                                              this.classList.add('is-active');">
                                    <img src="<?= htmlspecialchars($img['url']) ?>"
                                         alt="thumb <?= $idx + 1 ?>"
                                         onerror="this.onerror=null; this.src='/assets/images/placeholder/car-placeholder.svg';">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Spec panel -->
            <aside class="je-spec-panel">
                <div class="je-spec-eyebrow">KINAS AUTOMOBILE</div>

                <h1 class="je-spec-title">
                    <?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''))) ?>
                </h1>

                <div style="font-size:13px; color:#888; margin-bottom:8px;">
                    <?= htmlspecialchars($item['year'] ?? '') ?>
                </div>

                <div class="je-spec-price">
                    <?= function_exists('formatPrice') ? formatPrice((float)$item['price']) : '₦' . number_format((float)$item['price']) ?>
                </div>

                <div class="je-spec-price-note">
                    <?= !empty($item['negotiable']) ? 'Negotiable' : 'Fixed price' ?>
                </div>

                <!-- Car Details -->
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #E0E0E0;">
                    <h3 style="font-size:14px; font-weight:600; color:#C6A43F; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        Car Details
                    </h3>

                    <dl class="je-spec-key">
                        <?php
                        if (!empty($item['brand'])) {
                            echo '<div><dt>Make</dt><dd>' . htmlspecialchars($item['brand']) . '</dd></div>';
                        }

                        if (!empty($item['model'])) {
                            echo '<div><dt>Model</dt><dd>' . htmlspecialchars($item['model']) . '</dd></div>';
                        }

                        if (!empty($item['year'])) {
                            echo '<div><dt>Year</dt><dd>' . htmlspecialchars($item['year']) . '</dd></div>';
                        }

                        if (!empty($location)) {
                            echo '<div><dt>Location</dt><dd>' . htmlspecialchars($location) . '</dd></div>';
                        }

                        if (!empty($fullAddress) && !empty($item['address'])) {
                            echo '<div><dt>Address</dt><dd>' . htmlspecialchars($fullAddress) . '</dd></div>';
                        } elseif (!empty($item['address'])) {
                            echo '<div><dt>Address</dt><dd>' . htmlspecialchars($item['address']) . '</dd></div>';
                        }

                        if (!empty($item['mileage'])) {
                            echo '<div><dt>Mileage</dt><dd>' . htmlspecialchars($item['mileage']) . '</dd></div>';
                        }

                        if (!empty($item['engine'])) {
                            echo '<div><dt>Engine</dt><dd>' . htmlspecialchars($item['engine']) . '</dd></div>';
                        }

                        if (!empty($item['gearbox'])) {
                            echo '<div><dt>Gearbox</dt><dd>' . htmlspecialchars($item['gearbox']) . '</dd></div>';
                        } elseif (!empty($item['transmission'])) {
                            echo '<div><dt>Transmission</dt><dd>' . htmlspecialchars($item['transmission']) . '</dd></div>';
                        }

                        if (!empty($item['car_type'])) {
                            echo '<div><dt>Car Type</dt><dd>' . htmlspecialchars($item['car_type']) . '</dd></div>';
                        } elseif (!empty($item['body_type'])) {
                            echo '<div><dt>Body Type</dt><dd>' . htmlspecialchars($item['body_type']) . '</dd></div>';
                        }

                        if (!empty($item['drive'])) {
                            echo '<div><dt>Drive</dt><dd>' . htmlspecialchars($item['drive']) . '</dd></div>';
                        }

                        if (!empty($item['drive_train'])) {
                            echo '<div><dt>Drive Train</dt><dd>' . htmlspecialchars($item['drive_train']) . '</dd></div>';
                        } elseif (!empty($item['drivetrain'])) {
                            echo '<div><dt>Drivetrain</dt><dd>' . htmlspecialchars($item['drivetrain']) . '</dd></div>';
                        }

                        if (!empty($item['fuel_type'])) {
                            echo '<div><dt>Fuel Type</dt><dd>' . htmlspecialchars($item['fuel_type']) . '</dd></div>';
                        }

                        if (!empty($item['condition_status'])) {
                            echo '<div><dt>Condition</dt><dd>' . htmlspecialchars($item['condition_status']) . '</dd></div>';
                        }

                        if (!empty($item['vin'])) {
                            echo '<div><dt>VIN</dt><dd>' . htmlspecialchars($item['vin']) . '</dd></div>';
                        }

                        if (!empty($item['color'])) {
                            echo '<div><dt>Exterior Color</dt><dd>' . htmlspecialchars($item['color']) . '</dd></div>';
                        }

                        if (!empty($item['interior_color'])) {
                            echo '<div><dt>Interior Color</dt><dd>' . htmlspecialchars($item['interior_color']) . '</dd></div>';
                        }

                        if (!empty($item['doors'])) {
                            echo '<div><dt>Doors</dt><dd>' . htmlspecialchars($item['doors']) . '</dd></div>';
                        }

                        if (!empty($item['seats'])) {
                            echo '<div><dt>Seats</dt><dd>' . htmlspecialchars($item['seats']) . '</dd></div>';
                        }

                        if (!empty($item['country']) && empty($location)) {
                            echo '<div><dt>Country</dt><dd>' . htmlspecialchars($item['country']) . '</dd></div>';
                        }
                        ?>
                    </dl>
                </div>

                <?php if (($item['listing_type'] ?? 'sale') === 'rental'): ?>
                    <!-- Rental Booking Widget -->
                    <div class="je-agent-card" style="display:block;margin-bottom:16px;">
                        <h3 style="margin:0 0 12px;font-size:16px;">
                            <i class="fas fa-key"></i> Book This Rental
                        </h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">Start Date</label>
                                <input type="date" id="rbStartDate" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;">End Date</label>
                                <input type="date" id="rbEndDate" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;box-sizing:border-box;">
                            </div>
                        </div>

                        <div id="rbEstimate" style="font-size:13px;color:#666;margin-bottom:10px;"></div>

                        <button type="button" class="je-cta-primary" style="width:100%;" onclick="submitRentalBooking(<?= $listingId ?>)">
                            <i class="fas fa-calendar-check"></i> Request Booking
                        </button>

                        <div id="rbMsg" style="margin-top:10px;font-size:13px;display:none;"></div>
                    </div>

                    <script>
                    (function () {
                        const pricePerDay = <?= (float)($item['price'] ?? 0) ?>;
                        const startEl = document.getElementById('rbStartDate');
                        const endEl = document.getElementById('rbEndDate');
                        const estEl = document.getElementById('rbEstimate');

                        const today = new Date().toISOString().split('T')[0];

                        startEl.min = today;
                        endEl.min = today;

                        function updateEstimate() {
                            if (!startEl.value || !endEl.value) {
                                estEl.textContent = '';
                                return;
                            }

                            const start = new Date(startEl.value);
                            const end = new Date(endEl.value);
                            const days = Math.round((end - start) / 86400000);

                            if (days <= 0) {
                                estEl.textContent = 'End date must be after start date.';
                                return;
                            }

                            const total = days * pricePerDay;

                            estEl.textContent = days + ' day' + (days === 1 ? '' : 's') +
                                ' × ₦' + pricePerDay.toLocaleString('en-NG') +
                                ' = ₦' + total.toLocaleString('en-NG') + ' (estimate)';
                        }

                        startEl.addEventListener('change', function () {
                            endEl.min = startEl.value;
                            updateEstimate();
                        });

                        endEl.addEventListener('change', updateEstimate);
                    })();

                    async function submitRentalBooking(carId) {
                        if (!isUserLoggedIn()) {
                            showLoginRequired();
                            return;
                        }

                        const start = document.getElementById('rbStartDate').value;
                        const end = document.getElementById('rbEndDate').value;
                        const msgEl = document.getElementById('rbMsg');

                        if (!start || !end) {
                            msgEl.style.display = 'block';
                            msgEl.style.background = '#FFEBEE';
                            msgEl.style.color = '#C62828';
                            msgEl.style.padding = '10px';
                            msgEl.style.borderRadius = '6px';
                            msgEl.textContent = 'Please select both a start and end date.';
                            return;
                        }

                        try {
                            const res = await fetch('/api/rental/book.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    car_id: carId,
                                    start_date: start,
                                    end_date: end
                                })
                            });

                            const data = await res.json();

                            msgEl.style.display = 'block';
                            msgEl.style.padding = '10px';
                            msgEl.style.borderRadius = '6px';

                            if (res.ok && data.success) {
                                msgEl.style.background = '#E8F5E9';
                                msgEl.style.color = '#2E7D32';
                                msgEl.textContent = data.message || 'Booking request sent! The agent will confirm shortly.';
                            } else {
                                msgEl.style.background = '#FFEBEE';
                                msgEl.style.color = '#C62828';
                                msgEl.textContent = data.error || 'Something went wrong. Please try again.';
                            }
                        } catch (e) {
                            msgEl.style.display = 'block';
                            msgEl.style.background = '#FFEBEE';
                            msgEl.style.color = '#C62828';
                            msgEl.style.padding = '10px';
                            msgEl.style.borderRadius = '6px';
                            msgEl.textContent = 'Network error. Please try again.';
                        }
                    }
                    </script>
                <?php endif; ?>

                <!-- Buttons -->
                <div class="je-cta-row">
                    <button class="je-cta-primary"
                            id="scheduleBtn"
                            onclick="openScheduleViewing(<?= $listingId ?>, 'car', <?= $agentId ?>, <?= $inspectionFee ?>);">
                        <i class="far fa-calendar-alt"></i> Schedule Viewing
                    </button>

                    <button class="je-cta-secondary"
                            id="contactBtn"
                            onclick="openContactAgent(
                                <?= $agentId ?>,
                                <?= htmlspecialchars(json_encode($agentDisplayName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>,
                                'car'
                            );">
                        <i class="far fa-envelope"></i> Contact Agent
                    </button>

                    <button class="je-cta-secondary"
                            id="saveBtn"
                            onclick="jeSaveListing('car', <?= $listingId ?>);">
                        <i class="far fa-heart"></i> Save
                    </button>
                </div>

                <div class="je-agent-card">
                    <div class="je-agent-avatar">
                        <?php if (!empty($item['agent_avatar'])): ?>
                            <img src="<?= htmlspecialchars($item['agent_avatar']) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($agentInitialSource, 0, 1)) ?>
                        <?php endif; ?>
                    </div>

                    <div class="je-agent-info">
                        <div class="je-agent-name"><?= htmlspecialchars($agentDisplayName) ?></div>
                        <div class="je-agent-meta">
                            <?= htmlspecialchars($item['agent_company'] ?? 'Independent Agent') ?>
                            <?php if ($agentVerified): ?>
                                · <span style="color:#1B5E20;font-weight:600;">✓ Verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Description -->
        <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8; margin-top:40px;">
            <h2>About This Car</h2>

            <?php if (!empty($item['description'])): ?>
                <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            <?php else: ?>
                <p style="color:#999;font-style:italic;">No description provided.</p>
            <?php endif; ?>

            <?php if (!empty($features)): ?>
                <h2 style="margin-top:32px;">Features &amp; Equipment</h2>
                <div class="je-features-grid">
                    <?php foreach ($features as $f): ?>
                        <div class="je-feature-pill">
                            <i class="fas fa-check"></i>
                            <?= htmlspecialchars(is_array($f) ? ($f['name'] ?? json_encode($f)) : $f) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($item['latitude']) && !empty($item['longitude'])): ?>
                <h2 style="margin-top:32px;">Location</h2>
                <div id="listing-map"
                     data-lat="<?= htmlspecialchars($item['latitude']) ?>"
                     data-lng="<?= htmlspecialchars($item['longitude']) ?>"
                     data-title="<?= htmlspecialchars($item['title'] ?? '') ?>"
                     style="height:360px;border-radius:8px;overflow:hidden;border:1px solid #e8e8e8;"></div>
                <script src="/assets/js/map.js"></script>
            <?php endif; ?>

            <h2 style="margin-top:32px;">Finance Calculator</h2>

            <div class="je-finance-calc" style="background:#f9f8f5;border:1px solid #e8e8e8;border-radius:8px;padding:24px;max-width:520px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Vehicle Price (₦)</label>
                        <input type="number" id="fcPrice" value="<?= (int)($item['price'] ?? 0) ?>" min="0" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Down Payment (%)</label>
                        <input type="number" id="fcDown" value="20" min="0" max="100" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Interest Rate (% / yr)</label>
                        <input type="number" id="fcRate" value="18" min="0" max="100" step="0.1" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">Loan Term (months)</label>
                        <select id="fcTerm" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                            <option value="12">12</option>
                            <option value="24" selected>24</option>
                            <option value="36">36</option>
                            <option value="48">48</option>
                            <option value="60">60</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #e0ddd3;display:flex;justify-content:space-between;align-items:baseline;">
                    <span style="font-size:13px;color:#666;">Estimated monthly payment</span>
                    <span id="fcMonthly" style="font-size:24px;font-weight:700;color:#151515;">₦0</span>
                </div>

                <p style="margin:10px 0 0;font-size:11px;color:#999;">
                    Estimate only. Actual financing terms are set by your chosen lender and subject to approval.
                </p>
            </div>

            <script>
            (function () {
                const price = document.getElementById('fcPrice');
                const down = document.getElementById('fcDown');
                const rate = document.getElementById('fcRate');
                const term = document.getElementById('fcTerm');
                const out = document.getElementById('fcMonthly');

                if (!price || !out) return;

                function fmt(n) {
                    return '₦' + Math.round(n).toLocaleString('en-NG');
                }

                function calc() {
                    const p = Math.max(0, parseFloat(price.value) || 0);
                    const downPct = Math.min(100, Math.max(0, parseFloat(down.value) || 0));
                    const principal = p * (1 - downPct / 100);
                    const annualRate = Math.max(0, parseFloat(rate.value) || 0) / 100;
                    const months = parseInt(term.value, 10) || 24;
                    const monthlyRate = annualRate / 12;

                    let monthly;

                    if (monthlyRate === 0) {
                        monthly = principal / months;
                    } else {
                        monthly = principal * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
                    }

                    out.textContent = fmt(monthly > 0 ? monthly : 0);
                }

                [price, down, rate, term].forEach(el => el.addEventListener('input', calc));

                calc();
            })();
            </script>
        </section>

        <!-- Similar listings -->
        <?php if (!empty($similar)): ?>
            <section class="je-section" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
                <h2>You may also like</h2>

                <?php
                $simCards = array_map(function ($s) {
                    $specParts = array_filter([
                        $s['year'] ?? null,
                        $s['model'] ?? null,
                    ]);

                    return [
                        'id'         => $s['id'],
                        'title'      => trim(($s['brand'] ?? '') . ' ' . ($s['model'] ?? '') . ' ' . ($s['year'] ?? '')),
                        'division'   => 'KINAS AUTOMOBILE',
                        'price'      => $s['price'],
                        'thumbnail'  => $s['thumbnail'] ?: '',
                        'specs'      => implode(' • ', $specParts),
                        'location'   => '',
                        'detail_url' => '/divisions/kinas-automobile/detail.php?id=' . (int)$s['id'],
                        'featured'   => false,
                        'verified'   => false,
                    ];
                }, $similar);

                if (function_exists('je_render_listing_grid')) {
                    je_render_listing_grid($simCards);
                } else {
                    echo '<div class="je-listings-grid" style="grid-template-columns:repeat(4,1fr);">';

                    foreach ($simCards as $c) {
                        if (function_exists('je_render_card')) {
                            je_render_card($c);
                        }
                    }

                    echo '</div>';
                }
                ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
// ============================================================
// SAFE PAGE CONSTANTS
// ============================================================
window.__kinasAgentName = <?= json_encode($agentDisplayName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasListingTitle = <?= json_encode($listingTitleRaw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__kinasAgentVerified = <?= $agentVerified ? 'true' : 'false' ?>;

// ============================================================
// HELPERS
// ============================================================
function isUserLoggedIn() {
    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            return data.loggedIn === true;
        } catch (e) {
            return false;
        }
    }

    return document.querySelector('meta[name="user-id"]')?.content ? true : false;
}

function showLoginRequired() {
    if (typeof window.showSuccessBanner === 'function') {
        window.showSuccessBanner('Please sign in to continue — redirecting you to login…', true);
    } else if (typeof kinasToast === 'function') {
        kinasToast('Please sign in to continue — redirecting you to login…', 'warning');
    }

    setTimeout(function() {
        window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
    }, 1500);
}

function getKinasPublicNameFromMeta() {
    try {
        const meta = document.querySelector('meta[name="user-data"]');

        if (!meta) {
            return '';
        }

        const data = JSON.parse(meta.content);

        if (data.username) {
            return String(data.username).startsWith('@') ? data.username : '@' + data.username;
        }

        return data.name || '';
    } catch (e) {
        return '';
    }
}

// ============================================================
// SCHEDULE VIEWING
// ============================================================
function openScheduleViewing(listingId, listingType, agentId, inspectionFee) {
    inspectionFee = inspectionFee || 0;

    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    const old = document.getElementById('schedule-modal');
    if (old) old.remove();

    if (inspectionFee > 0 && !document.getElementById('paystack-inline-js')) {
        const s = document.createElement('script');
        s.id = 'paystack-inline-js';
        s.src = 'https://js.paystack.co/v2/inline.js';
        document.head.appendChild(s);
    }

    const html = `
    <div id="schedule-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">📅 Schedule Viewing</h3>
                <button onclick="document.getElementById('schedule-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${window.__kinasAgentName}</strong> · ${window.__kinasListingTitle}
            </div>

            ${inspectionFee > 0 ? `
            <div style="padding:12px;background:#FFF8E1;border:1px solid #FFE082;border-radius:8px;margin-bottom:20px;font-size:13px;color:#5d4a00;">
                <i class="fas fa-clipboard-check"></i>
                This listing requires a ₦${Math.round(inspectionFee).toLocaleString('en-NG')} inspection fee, paid online.
                Your appointment is confirmed automatically once payment succeeds.
            </div>
            ` : ''}

            <form id="scheduleForm">
                <input type="hidden" name="listing_id" value="${listingId}">
                <input type="hidden" name="listing_type" value="${listingType}">
                <input type="hidden" name="agent_id" value="${agentId}">
                <input type="hidden" name="inquiry_type" value="viewing">

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Name *</label>
                    <input type="text" name="name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Email *</label>
                    <input type="email" name="email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Phone</label>
                    <input type="tel" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Date *</label>
                        <input type="date" name="preferred_date" id="prefDate" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                    </div>

                    <div>
                        <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Time *</label>
                        <select name="preferred_time" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                            <option value="">Select</option>
                            <option value="09:00">9:00 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="12:00">12:00 PM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="17:00">5:00 PM</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Notes</label>
                    <textarea name="message" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="width:100%;padding:12px;background:#0A0A0A;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-calendar-check"></i>
                    ${inspectionFee > 0 ? 'Pay ₦' + Math.round(inspectionFee).toLocaleString('en-NG') + ' & Request Inspection' : 'Request Viewing'}
                </button>

                <div id="scheduleMsg" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </form>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    const dateInput = document.getElementById('prefDate');

    if (dateInput) {
        dateInput.min = tomorrow.toISOString().split('T')[0];
        dateInput.value = tomorrow.toISOString().split('T')[0];
    }

    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            const form = document.getElementById('scheduleForm');

            if (form) {
                const publicName = getKinasPublicNameFromMeta();

                form.querySelector('input[name="name"]').value = publicName || data.name || '';
                form.querySelector('input[name="email"]').value = data.email || '';
                form.querySelector('input[name="phone"]').value = data.phone || '';
            }
        } catch (e) {}
    }

    document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msg = document.getElementById('scheduleMsg');
        const original = btn.innerHTML;

        btn.disabled = true;
        msg.style.display = 'none';

        const formData = new FormData(this);

        if (inspectionFee > 0) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting payment...';

            try {
                const res = await fetch('/api/inspection/request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        listing_id: listingId,
                        listing_type: listingType,
                        preferred_date: formData.get('preferred_date'),
                        preferred_time: formData.get('preferred_time'),
                    })
                });

                const data = await res.json();

                if (!data.success) {
                    msg.style.display = 'block';
                    msg.style.background = '#f8d7da';
                    msg.style.color = '#721c24';
                    msg.textContent = data.error || 'Unable to start payment.';
                    btn.innerHTML = original;
                    btn.disabled = false;
                    return;
                }

                const popup = new PaystackPop();

                popup.resumeTransaction(data.access_code, {
                    onSuccess: function(transaction) {
                        fetch('/api/inspection/verify.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reference: transaction.reference || data.reference })
                        })
                        .then(r => r.json())
                        .then(v => {
                            const modal = document.getElementById('schedule-modal');
                            if (modal) modal.remove();

                            if (v.success) {
                                if (typeof window.showSuccessBanner === 'function') {
                                    window.showSuccessBanner('✅ Payment received — your inspection is confirmed! Check your email for details.', false);
                                }
                            } else {
                                if (typeof window.showSuccessBanner === 'function') {
                                    window.showSuccessBanner('Payment received, but confirmation is still processing. We will email you shortly.', false);
                                }
                            }
                        });
                    },
                    onCancel: function() {
                        btn.innerHTML = original;
                        btn.disabled = false;
                    },
                    onError: function(error) {
                        msg.style.display = 'block';
                        msg.style.background = '#f8d7da';
                        msg.style.color = '#721c24';
                        msg.textContent = 'Payment error: ' + (error && error.message ? error.message : 'please try again');
                        btn.innerHTML = original;
                        btn.disabled = false;
                    }
                });
            } catch (error) {
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#721c24';
                msg.textContent = 'Network error. Please try again.';
                btn.innerHTML = original;
                btn.disabled = false;
            }

            return;
        }

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        const notes = formData.get('message') || '';

        if (!notes.trim()) {
            formData.set('message', 'I would like to schedule a viewing for this car.');
        }

        try {
            const res = await fetch('/api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                const modal = document.getElementById('schedule-modal');
                if (modal) modal.remove();

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Viewing requested successfully! The agent will confirm within 24 hours.', false);
                }
            } else {
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#721c24';
                msg.textContent = data.error || 'Failed to schedule. Please try again.';
                btn.innerHTML = original;
                btn.disabled = false;
            }
        } catch (error) {
            msg.style.display = 'block';
            msg.style.background = '#f8d7da';
            msg.style.color = '#721c24';
            msg.textContent = 'Network error. Please try again.';
            btn.innerHTML = original;
            btn.disabled = false;
        }
    });
}

// ============================================================
// CONTACT AGENT
// ============================================================
function openContactAgent(agentId, agentName, division) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    const old = document.getElementById('contact-modal');
    if (old) old.remove();

    const verifiedBadge = window.__kinasAgentVerified
        ? '<span style="color:#1B5E20;">✓ Verified</span>'
        : '';

    const html = `
    <div id="contact-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:999998;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:30px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:20px;">✉️ Contact Agent</h3>
                <button onclick="document.getElementById('contact-modal').remove()" style="background:none;border:none;font-size:24px;cursor:pointer;">✕</button>
            </div>

            <div style="padding:12px;background:#f5f5f5;border-radius:8px;margin-bottom:20px;">
                <strong>${agentName}</strong> ${verifiedBadge} · ${division}
            </div>

            <form id="contactForm">
                <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                <input type="hidden" name="listing_type" value="car">
                <input type="hidden" name="agent_id" value="${agentId}">

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Name *</label>
                    <input type="text" name="name" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Email *</label>
                    <input type="email" name="email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Your Phone</label>
                    <input type="tel" name="phone" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Subject</label>
                    <input type="text" name="subject" value="Inquiry about car" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;font-size:13px;margin-bottom:4px;">Message *</label>
                    <textarea name="message" rows="5" required placeholder="Hi, I'm interested in your car..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;resize:vertical;"></textarea>
                </div>

                <button type="submit" style="width:100%;padding:12px;background:#0A0A0A;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-paper-plane"></i> Send Inquiry
                </button>

                <div id="contactMsg" style="margin-top:12px;padding:10px;border-radius:6px;display:none;"></div>
            </form>
        </div>
    </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);

    const meta = document.querySelector('meta[name="user-data"]');

    if (meta) {
        try {
            const data = JSON.parse(meta.content);
            const form = document.getElementById('contactForm');

            if (form) {
                const publicName = getKinasPublicNameFromMeta();

                form.querySelector('input[name="name"]').value = publicName || data.name || '';
                form.querySelector('input[name="email"]').value = data.email || '';
                form.querySelector('input[name="phone"]').value = data.phone || '';
            }
        } catch (e) {}
    }

    document.getElementById('contactForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msg = document.getElementById('contactMsg');
        const original = btn.innerHTML;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        msg.style.display = 'none';

        const formData = new FormData(this);

        try {
            const res = await fetch('/api/messages/send-inquiry.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.success) {
                const modal = document.getElementById('contact-modal');
                if (modal) modal.remove();

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Inquiry sent successfully! The agent will contact you shortly.', false);
                }
            } else {
                msg.style.display = 'block';
                msg.style.background = '#f8d7da';
                msg.style.color = '#721c24';
                msg.textContent = data.error || 'Failed to send. Please try again.';
                btn.innerHTML = original;
                btn.disabled = false;
            }
        } catch (error) {
            msg.style.display = 'block';
            msg.style.background = '#f8d7da';
            msg.style.color = '#721c24';
            msg.textContent = 'Network error. Please try again.';
            btn.innerHTML = original;
            btn.disabled = false;
        }
    });
}

// ============================================================
// SAVE LISTING
// ============================================================
function jeSaveListing(type, id) {
    if (!isUserLoggedIn()) {
        showLoginRequired();
        return;
    }

    const btn = document.getElementById('saveBtn');
    const originalHTML = btn ? btn.innerHTML : '';

    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }

    const formData = new FormData();
    formData.append('listing_type', type);
    formData.append('listing_id', id);

    fetch('/api/listings/favorite.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (data.action === 'added') {
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-heart" style="color:#28a745;"></i> FAVOURITE';
                    btn.style.backgroundColor = '#d4edda';
                    btn.style.color = '#155724';
                    btn.style.border = '1px solid #28a745';
                }

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('✅ Added to favorites!', false);
                }
            } else {
                if (btn) {
                    btn.innerHTML = '<i class="far fa-heart"></i> Save';
                    btn.style.backgroundColor = '';
                    btn.style.color = '';
                    btn.style.border = '';
                }

                if (typeof window.showSuccessBanner === 'function') {
                    window.showSuccessBanner('Removed from favorites', false);
                }
            }
        } else {
            if (btn) {
                btn.innerHTML = originalHTML;
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.style.border = '';
            }

            if (typeof window.showSuccessBanner === 'function') {
                window.showSuccessBanner('❌ ' + (data.error || 'Failed to update favorites'), true);
            }
        }
    })
    .catch(function() {
        if (btn) {
            btn.innerHTML = originalHTML;
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.border = '';
        }

        if (typeof window.showSuccessBanner === 'function') {
            window.showSuccessBanner('❌ Network error. Please try again.', true);
        }
    })
    .finally(function() {
        if (btn) btn.disabled = false;
    });
}

// ============================================================
// CHECK FAVORITE STATE ON LOAD
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('saveBtn');

    if (!btn) return;

    const formData = new FormData();
    formData.append('listing_type', 'car');
    formData.append('listing_id', '<?= $listingId ?>');
    formData.append('check_only', '1');

    fetch('/api/listings/favorite.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.action === 'added') {
            btn.innerHTML = '<i class="fas fa-heart" style="color:#28a745;"></i> FAVOURITE';
            btn.style.backgroundColor = '#d4edda';
            btn.style.color = '#155724';
            btn.style.border = '1px solid #28a745';
        }
    })
    .catch(function(e) {
        console.log('Check favorite error:', e);
    });
});
</script>

<?php include '../../templates/footer.php'; ?>
