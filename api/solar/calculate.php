<?php
// api/solar/calculate.php  (REBUILT — bundle-aware, engine-driven)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';
require_once __DIR__ . '/../../includes/solar-engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // ------------------------------------------------------------
    // 1. CSRF + rate limit
    // ------------------------------------------------------------
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
    if (class_exists('Security', false) && method_exists('Security', 'rateLimitDB')) {
        Security::rateLimitDB('solar_calc_' . Security::getClientIP(), 10, 600);
    }

    // ------------------------------------------------------------
    // 2. Read + validate input (same contract as the existing form)
    // ------------------------------------------------------------
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

    $db = Database::getInstance()->getConnection();

    // ------------------------------------------------------------
    // 3. Run the bundle-aware calculation via the engine
    // ------------------------------------------------------------
    $calc = kinas_solar_calculate($db, [
        'appliances'   => $appliances,
        'backup_hours' => $backupHours,
    ]);
    if (empty($calc['success'])) {
        throw new Exception($calc['error'] ?? 'Calculation failed. Please check your inputs.');
    }

    $reference = kinas_solar_make_reference();

    // ------------------------------------------------------------
    // 4. Map engine output -> PDF / response / email shapes
    // ------------------------------------------------------------
    $powerLabel = $calc['power_source_label'] ?: 'Custom Power System (contact us)';

    $pdfData = [
        'full_name'          => $fullName,
        'email'              => $email,
        'phone'              => $phone,
        'city_state'         => $cityState,
        'property_type'      => $propertyType,
        'total_load_watts'   => $calc['total_load_w'],
        'daily_kwh'          => $calc['daily_kwh'],
        'backup_hours'       => $calc['backup_hours'],
        'system_size'        => $calc['recommended_pv_kw'],
        'recommended_panels' => $calc['panels_qty'],
        'recommended_inverter' => $powerLabel,
        'recommended_battery'  => $powerLabel . ' (integrated battery)',
        'battery_units'      => 1,
        'estimated_cost'     => $calc['grand_total'],
        'monthly_savings'    => $calc['monthly_savings'],
        'payback_years'      => $calc['payback_years'],
        'roi'                => $calc['roi_20_years'],
        'co2_saved'          => $calc['co2_tons_year'],
        'appliances'         => $appliances,
        // Bundle-aware extras (rendered by the aligned solar-pdf.php):
        'items'              => $calc['items'],
        'warnings'           => $calc['warnings'],
    ];

    // ------------------------------------------------------------
    // 5. Generate PDF (best-effort)
    // ------------------------------------------------------------
    $pdfUrl = null;
    try {
        generateSolarRecommendationPDF($pdfData, $reference);
        $pdfUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/solar-reports/' . $reference . '.pdf';
    } catch (Throwable $e) {
        error_log('Solar PDF error: ' . $e->getMessage());
    }

    // ------------------------------------------------------------
    // 6. Save auditable proposal (+ legacy solar_enquiries mirror)
    // ------------------------------------------------------------
    kinas_solar_save_proposal($db, $calc, [
        'full_name'     => $fullName,
        'phone'         => $phone,
        'email'         => $email,
        'city_state'    => $cityState,
        'property_type' => $propertyType,
        'user_id'       => SessionManager::isLoggedIn() ? (int)SessionManager::getUserId() : null,
    ]);

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS solar_enquiries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                monthly_bill DECIMAL(15,2),
                system_size DECIMAL(5,2),
                annual_savings DECIMAL(12,2),
                payback_years DECIMAL(5,2),
                status VARCHAR(20) DEFAULT 'new',
                created_at DATETIME,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->prepare("
            INSERT INTO solar_enquiries
            (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years, status, created_at)
            VALUES (?,?,?,?,?,?,?,'new',NOW())
        ")->execute([
            $fullName, $email, $phone,
            $calc['monthly_savings'], $calc['recommended_pv_kw'],
            $calc['annual_savings'], $calc['payback_years'],
        ]);
    } catch (Throwable $e) {
        // Legacy mirror is optional — never block the main flow.
    }

    // ------------------------------------------------------------
    // 7. Emails (customer + admin) — hardware-only quotation
    // ------------------------------------------------------------
    $itemsRows = '';
    foreach ($calc['items'] as $it) {
        $itemsRows .= '<tr>'
            . '<td style="padding:6px 8px;border:1px solid #E0E0E0;">' . htmlspecialchars($it['description']) . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #E0E0E0;text-align:center;">' . (int)$it['qty'] . '</td>'
            . '<td style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">₦' . number_format($it['line_total']) . '</td>'
            . '</tr>';
    }
    $warningsHtml = '';
    if (!empty($calc['warnings'])) {
        $warningsHtml = '<p style="font-size:12px;color:#8D6E00;background:#FFF8E1;padding:8px 10px;border-radius:6px;">'
            . htmlspecialchars(implode(' ', $calc['warnings'])) . '</p>';
    }

    $emailService = new EmailService();

    $customerSubject = 'Your Solar Proposal from KINAS VOLT - ' . $reference;
    $customerBody = '
<!DOCTYPE html><html><head><style>
body{font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#2C2C2C;}
.content{background:#FFF;padding:30px;}
.btn{display:inline-block;padding:12px 30px;background:#C6A43F;color:#0A0A0A;text-decoration:none;border-radius:4px;font-weight:bold;margin:10px 0;}
.info-box{background:#F8F6F1;padding:15px;border-radius:4px;margin:20px 0;border-left:4px solid #C6A43F;}
table.items{width:100%;border-collapse:collapse;font-size:12px;margin:12px 0;}
</style></head><body>
<div style="background:#0A0A0A;padding:20px;text-align:center;">
<h1 style="color:#C6A43F;font-family:Prata,serif;margin:0;">KINAS GROUP</h1>
<p style="color:rgba(255,255,255,0.5);margin:4px 0 0;">KINAS VOLT - Solar Division</p>
</div>
<div class="content">
<h2 style="color:#0A0A0A;font-family:Prata,serif;">Your Solar Proposal is Ready!</h2>
<p>Dear ' . htmlspecialchars($fullName) . ',</p>
<p>Thank you for using the KINAS VOLT Solar Calculator. Based on your inputs we have matched real KINAS VOLT products for you.</p>
<div class="info-box">
<strong>📄 Proposal Details:</strong><br>
<strong>Reference:</strong> ' . $reference . '<br>
<strong>System Size:</strong> ' . $calc['recommended_pv_kw'] . ' kWp<br>
<strong>Solar Panels:</strong> ' . $calc['panels_qty'] . ' × ' . (int)$calc['panel_wattage_w'] . 'W<br>
<strong>Power System:</strong> ' . htmlspecialchars($powerLabel) . '<br>
<strong>Estimated Investment (Hardware):</strong> ₦' . number_format($calc['grand_total']) . '<br>
<strong>Monthly Savings:</strong> ₦' . number_format($calc['monthly_savings']) . '
</div>
<table class="items">
<tr style="background:#F5F5F5;"><th style="padding:6px 8px;border:1px solid #E0E0E0;text-align:left;">Item</th><th style="padding:6px 8px;border:1px solid #E0E0E0;">Qty</th><th style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">Total</th></tr>
' . $itemsRows . '
</table>
' . $warningsHtml . '
<p style="font-size:11px;color:#888;">Quotation covers solar hardware only. Installation, cabling, mounting and transport are not included, as these services are not currently offered.</p>
<p style="text-align:center;margin:30px 0;"><a href="' . ($pdfUrl ?? '#') . '" class="btn">📄 View/Download Your Proposal</a></p>
<p>Our team will contact you within 24 hours. Call <strong>+234 913 717 5523</strong> for questions.</p>
</div></body></html>';

    $customerSent = $emailService->send($email, $fullName, $customerSubject, $customerBody, strip_tags($customerBody));

    $adminSubject = '🔔 NEW Solar Enquiry - ' . $reference . ' - ' . $fullName;
    $adminBody = $customerBody
        . '<p style="font-size:12px;color:#666;">Customer: ' . htmlspecialchars($fullName)
        . ' | ' . htmlspecialchars($email) . ' | ' . htmlspecialchars($phone)
        . ' | ' . htmlspecialchars($cityState) . ' | ' . htmlspecialchars($propertyType) . '</p>';
    $adminSent = $emailService->send('admin@kinas-group.com', 'Admin', $adminSubject, $adminBody, strip_tags($adminBody));

    // ------------------------------------------------------------
    // 8. Response (keeps the existing front-end contract + extras)
    // ------------------------------------------------------------
    echo json_encode([
        'success'   => true,
        'message'   => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url'   => $pdfUrl,
        'data'      => [
            // Legacy keys the current front-end reads:
            'system_size'     => $calc['recommended_pv_kw'],
            'panels'          => $calc['panels_qty'],
            'battery_capacity'=> $calc['recommended_battery_kwh'],
            'estimated_cost'  => $calc['grand_total'],
            'monthly_savings' => $calc['monthly_savings'],
            'payback_years'   => number_format($calc['payback_years'], 2),
            'roi'             => number_format($calc['roi_20_years'], 2),
            'co2_saved'       => number_format($calc['co2_tons_year'], 2),
            // Bundle-aware extras for the upgraded front-end:
            'power_system'    => $powerLabel,
            'items'           => $calc['items'],
            'warnings'        => $calc['warnings'],
        ],
        'emails_sent' => [
            'customer' => $customerSent ? 'sent' : 'failed',
            'admin'    => $adminSent ? 'sent' : 'failed',
        ],
    ]);

} catch (Exception $e) {
    error_log('Solar Calculator Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
