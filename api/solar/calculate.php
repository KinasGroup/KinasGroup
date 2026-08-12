<?php
/**
 * KINAS GROUP — Solar Calculator API (REBUILT)
 *
 * POST /api/solar/calculate.php
 *
 * Replaces the old hardcoded estimator with a product-driven quotation
 * engine:
 *   - Uses REAL appliance hours (no fixed 8h assumption).
 *   - Reads admin-controlled settings from solar_calculator_settings.
 *   - Matches REAL KINAS VOLT products (power stations / panels /
 *     inverters / batteries) with LIVE listing prices.
 *   - Falls back to clearly-labelled reference pricing only when a
 *     product type is not configured, and emits a warning.
 *   - Saves the quotation to solar_proposals + solar_proposal_items.
 *   - Generates the PDF and sends customer + admin emails.
 *
 * Requires FILE 1 (SQL tables). Self-contained — no other rebuild file
 * is required for this endpoint to work.
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ============================================================
// HELPERS
// ============================================================

/**
 * Load admin-controlled calculator settings with safe defaults.
 */
function solar_calc_settings(PDO $db): array
{
    $defaults = [
        'sun_hours'               => 5.0,
        'load_margin_pct'         => 10.0,
        'pv_eff'                  => 0.80,
        'battery_dod_pct'         => 90.0,
        'battery_eff_pct'         => 95.0,
        'inverter_factor'         => 1.25,
        'default_panel_wattage'   => 550.0,
        'default_panel_price'     => 450000.0,
        'default_inverter_price'  => 3500000.0,
        'default_battery_price'   => 2800000.0,
        'installation_cost'       => 1500000.0,
        'cabling_cost'            => 500000.0,
        'mounting_cost'           => 400000.0,
        'transport_cost'          => 200000.0,
        'tariff'                  => 225.0,
        'co2_factor'              => 0.85,
    ];

    $map = [];
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM solar_calculator_settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $map[$r['setting_key']] = (float)$r['setting_value'];
        }
    } catch (Throwable $e) {
        $map = [];
    }

    $keymap = [
        'sun_hours_default'      => 'sun_hours',
        'load_margin_pct'        => 'load_margin_pct',
        'pv_performance_ratio'   => 'pv_eff',
        'battery_dod_pct'        => 'battery_dod_pct',
        'battery_efficiency_pct' => 'battery_eff_pct',
        'inverter_safety_factor' => 'inverter_factor',
        'default_panel_wattage'  => 'default_panel_wattage',
        'default_panel_price'    => 'default_panel_price',
        'default_inverter_price' => 'default_inverter_price',
        'default_battery_price'  => 'default_battery_price',
        'installation_cost'      => 'installation_cost',
        'cabling_cost'           => 'cabling_cost',
        'mounting_cost'          => 'mounting_cost',
        'transport_cost'         => 'transport_cost',
        'electricity_tariff_ngn' => 'tariff',
        'co2_kg_per_kwh'         => 'co2_factor',
    ];

    foreach ($keymap as $dbKey => $outKey) {
        if (isset($map[$dbKey]) && $map[$dbKey] > 0) {
            $defaults[$outKey] = $map[$dbKey];
        }
    }

    return $defaults;
}

/**
 * Load calculator-enabled products with LIVE listing prices.
 */
function solar_calc_products(PDO $db): array
{
    try {
        $stmt = $db->query("
            SELECT p.product_type, p.panel_wattage_w, p.inverter_capacity_kva, p.continuous_kw,
                   p.battery_capacity_kwh, p.usable_battery_kwh, p.battery_voltage_v, p.priority,
                   l.id AS listing_id, l.title, l.price
            FROM solar_calculator_products p
            JOIN solar_listings l ON l.id = p.listing_id
            WHERE p.active = 1
              AND l.status = 'active'
              AND l.price IS NOT NULL
            ORDER BY p.priority ASC, l.price ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function solar_pick_by_type(array $products, string $type): array
{
    return array_values(array_filter($products, fn($p) => ($p['product_type'] ?? '') === $type));
}

function solar_standard_inverter_label(float $requiredKw): string
{
    foreach ([1, 2, 3, 5, 8, 10, 12, 15, 20] as $s) {
        if ($s >= $requiredKw) return $s . 'kVA Hybrid Inverter';
    }
    return '20kVA+ Hybrid Inverter';
}

// ============================================================
// MAIN
// ============================================================
try {
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    $fullName     = trim($_POST['full_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $cityState    = trim($_POST['city_state'] ?? '');
    $propertyType = trim($_POST['property_type'] ?? '');
    $backupHours  = (int)($_POST['backup_hours'] ?? 24);
    $appliances   = json_decode($_POST['appliances'] ?? '[]', true);

    if (empty($fullName) || empty($phone) || empty($email) || empty($cityState) || empty($propertyType)) {
        throw new Exception('Please fill in all required fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }
    if (empty($appliances) || !is_array($appliances)) {
        throw new Exception('Please add at least one appliance.');
    }

    $backupHours = max(1, min(120, $backupHours));

    $db = Database::getInstance()->getConnection();
    $S  = solar_calc_settings($db);
    $products = solar_calc_products($db);

    // --------------------------------------------------------
    // 1. LOAD CALCULATION (real hours per appliance)
    // --------------------------------------------------------
    $totalLoadW = 0.0;
    $dailyWh    = 0.0;
    foreach ($appliances as $ap) {
        $qty   = max(1, (int)($ap['quantity'] ?? $ap['qty'] ?? 1));
        $watts = (float)($ap['watts'] ?? 0);
        $hours = (float)($ap['hours'] ?? 0);
        if ($watts <= 0) continue;
        $totalLoadW += $qty * $watts;
        $dailyWh    += $qty * $watts * max(0, $hours);
    }

    if ($totalLoadW <= 0 || $dailyWh <= 0) {
        throw new Exception('Total load calculation failed. Please check your appliance wattages and hours.');
    }

    $dailyKwh = $dailyWh / 1000;

    // --------------------------------------------------------
    // 2. TECHNICAL SIZING (admin-controlled assumptions)
    // --------------------------------------------------------
    $sunHours = max(0.5, $S['sun_hours']);
    $pvEff    = max(0.1, min(1, $S['pv_eff']));
    $dod      = max(0.1, min(1, $S['battery_dod_pct'] / 100));
    $battEff  = max(0.1, min(1, $S['battery_eff_pct'] / 100));

    $designDailyKwh   = $dailyKwh * (1 + ($S['load_margin_pct'] / 100));
    $requiredPvKw     = $designDailyKwh / ($sunHours * $pvEff);
    $requiredInvKw    = ($totalLoadW * $S['inverter_factor']) / 1000;
    $requiredBattKwh  = ($dailyKwh * ($backupHours / 24)) / ($dod * $battEff);

    $warnings = [];
    $items    = [];

    // --------------------------------------------------------
    // 3. PRODUCT MATCHING (live prices)
    // --------------------------------------------------------
    $generators = solar_pick_by_type($products, 'generator');
    $inverters  = solar_pick_by_type($products, 'inverter');
    $batteries  = solar_pick_by_type($products, 'battery');
    $panels     = solar_pick_by_type($products, 'panel');

    // --- Panels ---
    $panelProduct = null;
    foreach ($panels as $p) {
        if ((float)$p['panel_wattage_w'] > 0) { $panelProduct = $p; break; }
    }

    if ($panelProduct) {
        $panelW         = (float)$panelProduct['panel_wattage_w'];
        $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
        $panelUnitPrice = (float)$panelProduct['price'];
        $panelDesc      = (string)$panelProduct['title'];
        $panelListingId = (int)$panelProduct['listing_id'];
    } else {
        $panelW         = max(1, $S['default_panel_wattage']);
        $panelQty       = (int)max(1, ceil(($requiredPvKw * 1000) / $panelW));
        $panelUnitPrice = $S['default_panel_price'];
        $panelDesc      = ((int)$panelW) . 'W Monocrystalline Solar Panel (reference)';
        $panelListingId = null;
        $warnings[]     = 'No solar panel product is configured — a reference panel price was used.';
    }
    $actualPvKw = ($panelQty * $panelW) / 1000;

    $items[] = [
        'type' => 'panel', 'listing_id' => $panelListingId, 'description' => $panelDesc,
        'qty' => $panelQty, 'unit_price' => $panelUnitPrice, 'line_total' => $panelQty * $panelUnitPrice,
    ];

    // --- Power system: prefer an all-in-one power station ---
    $chosenGenerator = null;
    foreach ($generators as $g) {
        if ((float)$g['continuous_kw'] >= $requiredInvKw && (float)$g['usable_battery_kwh'] >= $requiredBattKwh) {
            $chosenGenerator = $g;
            break;
        }
    }

    $inverterLabel = '';
    $batteryLabel  = '';
    $batteryUnits  = 1;
    $recommendedInvKw   = 0.0;
    $recommendedBattKwh = 0.0;

    if ($chosenGenerator) {
        $items[] = [
            'type' => 'generator', 'listing_id' => (int)$chosenGenerator['listing_id'],
            'description' => (string)$chosenGenerator['title'],
            'qty' => 1, 'unit_price' => (float)$chosenGenerator['price'], 'line_total' => (float)$chosenGenerator['price'],
        ];
        $inverterLabel = (string)$chosenGenerator['title'];
        $batteryLabel  = (string)$chosenGenerator['title'] . ' (integrated battery)';
        $batteryUnits  = 1;
        $recommendedInvKw   = (float)$chosenGenerator['continuous_kw'];
        $recommendedBattKwh = (float)$chosenGenerator['usable_battery_kwh'];
    } else {
        // Separate inverter + battery
        $chosenInverter = null;
        foreach ($inverters as $inv) {
            if ((float)$inv['continuous_kw'] >= $requiredInvKw) { $chosenInverter = $inv; break; }
        }

        if ($chosenInverter) {
            $items[] = [
                'type' => 'inverter', 'listing_id' => (int)$chosenInverter['listing_id'],
                'description' => (string)$chosenInverter['title'],
                'qty' => 1, 'unit_price' => (float)$chosenInverter['price'], 'line_total' => (float)$chosenInverter['price'],
            ];
            $inverterLabel = (string)$chosenInverter['title'];
            $recommendedInvKw = (float)$chosenInverter['continuous_kw'];

            if (!empty($batteries)) {
                $b = $batteries[0];
                $perUnit = max(0.1, (float)($b['usable_battery_kwh'] ?: $b['battery_capacity_kwh']));
                $batteryUnits = (int)max(1, ceil($requiredBattKwh / $perUnit));
                $items[] = [
                    'type' => 'battery', 'listing_id' => (int)$b['listing_id'],
                    'description' => (string)$b['title'],
                    'qty' => $batteryUnits, 'unit_price' => (float)$b['price'], 'line_total' => $batteryUnits * (float)$b['price'],
                ];
                $batteryLabel = (string)$b['title'];
                $recommendedBattKwh = $perUnit * $batteryUnits;
            } else {
                $warnings[] = 'No battery product configured — battery bank must be quoted manually.';
            }
        } else {
            // Reference fallback for the whole power system
            $refInvPrice  = $S['default_inverter_price'];
            $refBattPrice = $S['default_battery_price'];
            $inverterLabel = solar_standard_inverter_label($requiredInvKw) . ' (reference)';
            $batteryLabel  = '48V 100Ah LiFePO4 Battery (reference)';
            $batteryUnits  = (int)max(1, ceil($requiredBattKwh / 4.8));
            $recommendedInvKw   = $requiredInvKw;
            $recommendedBattKwh = 4.8 * $batteryUnits;

            $items[] = ['type' => 'inverter', 'listing_id' => null, 'description' => $inverterLabel, 'qty' => 1, 'unit_price' => $refInvPrice, 'line_total' => $refInvPrice];
            $items[] = ['type' => 'battery', 'listing_id' => null, 'description' => $batteryLabel, 'qty' => $batteryUnits, 'unit_price' => $refBattPrice, 'line_total' => $batteryUnits * $refBattPrice];
            $warnings[] = 'No matching power product configured — reference inverter/battery pricing used. Configure KINAS VOLT products in admin for live pricing.';
        }
    }

    // --- Balance-of-system costs (admin controlled) ---
    foreach ([
        ['installation_cost', 'Installation'],
        ['cabling_cost', 'Cabling & Protection'],
        ['mounting_cost', 'Mounting Structure'],
        ['transport_cost', 'Transport & Logistics'],
    ] as [$key, $label]) {
        $cost = (float)$S[$key];
        if ($cost > 0) {
            $items[] = ['type' => 'cost', 'listing_id' => null, 'description' => $label, 'qty' => 1, 'unit_price' => $cost, 'line_total' => $cost];
        }
    }

    $grandTotal = 0.0;
    foreach ($items as $it) $grandTotal += $it['line_total'];

    // --------------------------------------------------------
    // 4. SAVINGS / PAYBACK / CO2
    // --------------------------------------------------------
    $monthlyGeneration  = $actualPvKw * $sunHours * 30 * $pvEff;
    $monthlyConsumption = $dailyKwh * 30;
    $billableKwh        = min($monthlyGeneration, $monthlyConsumption);
    $monthlySavings     = $billableKwh * max(0, $S['tariff']);
    $paybackYears       = $monthlySavings > 0 ? $grandTotal / ($monthlySavings * 12) : 0;
    $roi                = $grandTotal > 0 ? (($monthlySavings * 12 * 20) / $grandTotal) * 100 : 0;
    $co2TonsYear        = ($dailyKwh * 365 * max(0, $S['co2_factor'])) / 1000;

    // --------------------------------------------------------
    // 5. SAVE PROPOSAL (audit trail)
    // --------------------------------------------------------
    $reference = 'SOL-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $userId = (class_exists('SessionManager') && SessionManager::isLoggedIn()) ? (int)SessionManager::getUserId() : null;

    try {
        $db->prepare("
            INSERT INTO solar_proposals
            (reference, full_name, phone, email, city_state, property_type, backup_hours, user_id,
             total_load_w, daily_kwh, required_pv_kw, panels_recommended, required_inverter_kw,
             required_battery_kwh, total_cost, monthly_savings, payback_years, co2_tons_year, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'new', NOW())
        ")->execute([
            $reference, $fullName, $phone, $email, $cityState, $propertyType, $backupHours, $userId,
            (int)round($totalLoadW), round($dailyKwh, 2), round($requiredPvKw, 2), $panelQty,
            round($requiredInvKw, 2), round($requiredBattKwh, 2), round($grandTotal), round($monthlySavings),
            round($paybackYears, 2), round($co2TonsYear, 2),
        ]);
        $proposalId = (int)$db->lastInsertId();

        $insItem = $db->prepare("
            INSERT INTO solar_proposal_items (proposal_id, item_type, listing_id, description, qty, unit_price, line_total)
            VALUES (?,?,?,?,?,?,?)
        ");
        foreach ($items as $it) {
            $insItem->execute([$proposalId, $it['type'], $it['listing_id'], $it['description'], $it['qty'], $it['unit_price'], $it['line_total']]);
        }
    } catch (Throwable $e) {
        error_log('solar calculate: proposal save skipped: ' . $e->getMessage());
    }

    // --------------------------------------------------------
    // 6. PDF
    // --------------------------------------------------------
    $pdfUrl = null;
    try {
        $pdfData = [
            'full_name' => $fullName, 'email' => $email, 'phone' => $phone,
            'city_state' => $cityState, 'property_type' => $propertyType,
            'total_load_watts' => (int)round($totalLoadW), 'daily_kwh' => round($dailyKwh, 2),
            'backup_hours' => $backupHours, 'system_size' => round($actualPvKw, 2),
            'recommended_panels' => $panelQty, 'recommended_inverter' => $inverterLabel,
            'recommended_battery' => $batteryLabel, 'battery_units' => $batteryUnits,
            'estimated_cost' => round($grandTotal), 'monthly_savings' => round($monthlySavings),
            'payback_years' => round($paybackYears, 2), 'roi' => round($roi, 2),
            'co2_saved' => round($co2TonsYear, 2), 'appliances' => $appliances,
        ];
        $pdfPath = generateSolarRecommendationPDF($pdfData, $reference);
        $pdfUrl  = 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/solar-reports/' . $reference . '.pdf';
    } catch (Throwable $e) {
        error_log('solar calculate: PDF failed: ' . $e->getMessage());
    }

    // --------------------------------------------------------
    // 7. EMAILS
    // --------------------------------------------------------
    $emailService = new EmailService();

    $itemsHtml = '';
    foreach ($items as $it) {
        $itemsHtml .= '<tr><td style="padding:6px 10px;border:1px solid #E0E0E0;">' . htmlspecialchars($it['description']) . '</td>'
            . '<td style="padding:6px 10px;border:1px solid #E0E0E0;text-align:center;">' . $it['qty'] . '</td>'
            . '<td style="padding:6px 10px;border:1px solid #E0E0E0;text-align:right;">₦' . number_format($it['line_total']) . '</td></tr>';
    }

    $summaryBox = '
    <div style="background:#F8F6F1;padding:15px;border-radius:4px;margin:20px 0;border-left:4px solid #C6A43F;font-size:14px;">
        <strong>Reference:</strong> ' . $reference . '<br>
        <strong>System Size:</strong> ' . round($actualPvKw, 2) . ' kWp<br>
        <strong>Panels:</strong> ' . $panelQty . ' × ' . ((int)$panelW) . 'W<br>
        <strong>Power System:</strong> ' . htmlspecialchars($inverterLabel ?: '—') . '<br>
        <strong>Estimated Investment:</strong> ₦' . number_format($grandTotal) . '<br>
        <strong>Monthly Savings:</strong> ₦' . number_format($monthlySavings) . '
    </div>';

    $customerBody = '
    <!DOCTYPE html><html><head></head><body style="font-family:Arial,sans-serif;color:#2C2C2C;">
    <div style="background:#0A0A0A;padding:20px;text-align:center;">
        <h1 style="color:#C6A43F;margin:0;">KINAS GROUP</h1>
        <p style="color:rgba(255,255,255,0.5);margin:4px 0 0;">KINAS VOLT - Solar Division</p>
    </div>
    <div style="padding:30px;">
        <h2>Your Solar Proposal is Ready!</h2>
        <p>Dear ' . htmlspecialchars($fullName) . ',</p>
        <p>Thank you for using the KINAS VOLT Solar Calculator. Based on your inputs, here is your quotation:</p>
        ' . $summaryBox . '
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin:20px 0;">
            <tr style="background:#F5F5F5;"><th style="padding:6px 10px;border:1px solid #E0E0E0;text-align:left;">Item</th><th style="padding:6px 10px;border:1px solid #E0E0E0;">Qty</th><th style="padding:6px 10px;border:1px solid #E0E0E0;text-align:right;">Total</th></tr>
            ' . $itemsHtml . '
        </table>
        ' . ($pdfUrl ? '<p style="text-align:center;"><a href="' . $pdfUrl . '" style="display:inline-block;padding:12px 30px;background:#C6A43F;color:#0A0A0A;text-decoration:none;border-radius:4px;font-weight:bold;">View / Download PDF Proposal</a></p>' : '') . '
        <p>Our team will contact you within 24 hours. Call <strong>+234 913 717 5523</strong> for questions.</p>
    </div>
    </body></html>';

    $customerSent = $emailService->send($email, $fullName, 'Your Solar Proposal from KINAS VOLT - ' . $reference, $customerBody, strip_tags($customerBody));

    $adminBody = $customerBody;
    $adminSent = $emailService->send('admin@kinas-group.com', 'Admin', '🔔 NEW Solar Enquiry - ' . $reference . ' - ' . $fullName, $adminBody, strip_tags($adminBody));

    // --------------------------------------------------------
    // 8. RESPONSE
    // --------------------------------------------------------
    echo json_encode([
        'success' => true,
        'message' => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url' => $pdfUrl,
        'data' => [
            'system_size' => round($actualPvKw, 2),
            'panels' => $panelQty,
            'panel_wattage' => (int)$panelW,
            'inverter' => $inverterLabel,
            'battery' => $batteryLabel,
            'battery_units' => $batteryUnits,
            'battery_capacity' => round($recommendedBattKwh, 2),
            'estimated_cost' => round($grandTotal),
            'monthly_savings' => round($monthlySavings),
            'payback_years' => round($paybackYears, 2),
            'roi' => round($roi, 2),
            'co2_saved' => round($co2TonsYear, 2),
            'items' => $items,
            'warnings' => $warnings,
        ],
        'emails_sent' => [
            'customer' => $customerSent ? 'sent' : 'failed',
            'admin' => $adminSent ? 'sent' : 'failed',
        ],
    ]);
} catch (Exception $e) {
    error_log('Solar Calculator Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
