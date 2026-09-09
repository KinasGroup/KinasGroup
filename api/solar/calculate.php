<?php
// api/solar/calculate.php — DUAL-OPTION (Option B) rebuild
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

    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
    if (class_exists('Security', false) && method_exists('Security', 'rateLimitDB')) {
        Security::rateLimitDB('solar_calc_' . Security::getClientIP(), 10, 600);
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

    $db = Database::getInstance()->getConnection();

    $calc = kinas_solar_calculate($db, [
        'appliances'   => $appliances,
        'backup_hours' => $backupHours,
    ]);

    if (empty($calc['success'])) {
        throw new Exception($calc['error'] ?? 'Calculation failed. Please check your inputs.');
    }

    $reference = kinas_solar_make_reference();
    $optionA = $calc['options']['generator'];
    $optionB = $calc['options']['custom'];

    // ---------------- PDF payload (both options + legacy keys) ----------------
    $pdfData = [
        'full_name'        => $fullName,
        'email'            => $email,
        'phone'            => $phone,
        'city_state'       => $cityState,
        'property_type'    => $propertyType,
        'total_load_watts' => $calc['total_load_w'],
        'daily_kwh'        => $calc['daily_kwh'],
        'backup_hours'     => $calc['backup_hours'],
        'required_pv_kw'   => $calc['required_pv_kw'],
        'required_inverter_kw' => $calc['required_inverter_kw'],
        'required_battery_kwh' => $calc['required_battery_kwh'],
        'options'          => ['generator' => $optionA, 'custom' => $optionB],
        // legacy keys for the current PDF until Part 2 replaces it:
        'system_size'          => $calc['recommended_pv_kw'],
        'recommended_panels'   => $calc['panels_qty'],
        'panel_wattage_w'      => (float)$calc['panel_wattage_w'],
        'panel_description'    => $calc['panel_description'],
        'recommended_inverter' => $calc['power_source_label'],
        'recommended_battery'  => $calc['power_source_label'] . ' (integrated battery)',
        'battery_units'        => 1,
        'estimated_cost'       => $calc['grand_total'],
        'monthly_savings'      => $calc['monthly_savings'],
        'payback_years'        => $calc['payback_years'],
        'roi'                  => $calc['roi_20_years'],
        'co2_saved'            => $calc['co2_tons_year'],
        'appliances'           => $appliances,
        'items'                => $calc['items'],
        'warnings'             => $calc['warnings'],
    ];

    $pdfUrl = null;
    try {
        generateSolarRecommendationPDF($pdfData, $reference);
        $pdfUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/solar-reports/' . $reference . '.pdf';
    } catch (Throwable $e) {
        error_log('Solar PDF error: ' . $e->getMessage());
    }

    kinas_solar_save_proposal($db, $calc, [
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'city_state' => $cityState,
        'property_type' => $propertyType,
        'user_id' => SessionManager::isLoggedIn() ? (int)SessionManager::getUserId() : null,
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
    }

    // ---------------- Emails: BOTH options ----------------
    $optionBlock = function (array $opt) {
        if (empty($opt['available'])) {
            return '<p style="font-size:12px;color:#888;"><strong>' . htmlspecialchars($opt['label'])
                . ':</strong> Not available for this load — ' . htmlspecialchars($opt['reason'] ?? 'requirements not met.') . '</p>';
        }
        $rows = '';
        foreach ($opt['items'] as $it) {
            $rows .= '<tr>'
                . '<td style="padding:6px 8px;border:1px solid #E0E0E0;">' . htmlspecialchars($it['description']) . '</td>'
                . '<td style="padding:6px 8px;border:1px solid #E0E0E0;text-align:center;">' . (int)$it['qty'] . '</td>'
                . '<td style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">₦' . number_format($it['line_total']) . '</td>'
                . '</tr>';
        }
        return '<h3 style="margin:18px 0 6px;color:#0A0A0A;">' . htmlspecialchars($opt['label']) . '</h3>'
            . '<p style="font-size:12px;color:#555;margin:0 0 6px;">'
            . 'Panels: ' . (int)$opt['panels_qty'] . ' × ' . (int)$opt['panel_wattage_w'] . 'W · '
            . 'Power: ' . htmlspecialchars($opt['power_source_label']) . ' · '
            . 'Monthly savings: ₦' . number_format($opt['monthly_savings']) . '</p>'
            . '<table class="items" style="width:100%;border-collapse:collapse;font-size:12px;margin:6px 0 4px;">'
            . '<tr style="background:#F5F5F5;"><th style="padding:6px 8px;border:1px solid #E0E0E0;text-align:left;">Item</th>'
            . '<th style="padding:6px 8px;border:1px solid #E0E0E0;">Qty</th>'
            . '<th style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">Total</th></tr>'
            . $rows
            . '<tr style="background:#C6A43F;color:#0A0A0A;font-weight:bold;"><td colspan="2" style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">TOTAL</td>'
            . '<td style="padding:6px 8px;border:1px solid #E0E0E0;text-align:right;">₦' . number_format($opt['grand_total']) . '</td></tr>'
            . '</table>';
    };

    $emailService = new EmailService();
    $customerSubject = 'Your Solar Proposal from KINAS VOLT - ' . $reference;

    $customerBody = '
    <!DOCTYPE html><html><head><style>
    body{font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#2C2C2C;}
    .content{background:#FFF;padding:30px;}
    .btn{display:inline-block;padding:12px 30px;background:#C6A43F;color:#0A0A0A;text-decoration:none;border-radius:4px;font-weight:bold;margin:10px 0;}
    .info-box{background:#F8F6F1;padding:15px;border-radius:4px;margin:20px 0;border-left:4px solid #C6A43F;}
    table.items{width:100%;border-collapse:collapse;font-size:12px;}
    </style></head><body>
    <div style="background:#0A0A0A;padding:20px;text-align:center;">
    <h1 style="color:#C6A43F;font-family:Prata,serif;margin:0;">KINAS GROUP</h1>
    <p style="color:rgba(255,255,255,0.5);margin:4px 0 0;">KINAS VOLT - Solar Division</p>
    </div>
    <div class="content">
    <h2 style="color:#0A0A0A;font-family:Prata,serif;">Your Solar Proposal is Ready!</h2>
    <p>Dear ' . htmlspecialchars($fullName) . ',</p>
    <p>Based on your appliances and backup needs we prepared <strong>two real-product options</strong> for you.</p>
    <div class="info-box">
    <strong>📄 Reference:</strong> ' . $reference . '<br>
    <strong>Total Load:</strong> ' . number_format($calc['total_load_w']) . ' W · '
    . '<strong>Daily Use:</strong> ' . $calc['daily_kwh'] . ' kWh · '
    . '<strong>Backup:</strong> ' . $calc['backup_hours'] . 'h<br>
    <strong>Required:</strong> PV ' . $calc['required_pv_kw'] . ' kW · Inverter '
    . $calc['required_inverter_kw'] . ' kW · Battery ' . $calc['required_battery_kwh'] . ' kWh
    </div>
    ' . $optionBlock($optionA) . '
    ' . $optionBlock($optionB) . '
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

    // ---------------- Response ----------------
    $optionSummary = function (array $opt) {
        return [
            'available' => (bool)$opt['available'],
            'reason' => $opt['reason'],
            'label' => $opt['label'],
            'items' => $opt['items'],
            'grand_total' => $opt['grand_total'],
            'panels_qty' => $opt['panels_qty'],
            'panel_wattage_w' => $opt['panel_wattage_w'],
            'panel_description' => $opt['panel_description'],
            'power_source_label' => $opt['power_source_label'],
            'max_pv_input_w' => $opt['max_pv_input_w'],
            'recommended_pv_kw' => $opt['recommended_pv_kw'],
            'recommended_inverter_kw' => $opt['recommended_inverter_kw'],
            'recommended_battery_kwh' => $opt['recommended_battery_kwh'],
            'monthly_savings' => $opt['monthly_savings'],
            'payback_years' => $opt['payback_years'],
            'roi' => $opt['roi_20_years'],
            'co2_saved' => $opt['co2_tons_year'],
        ];
    };

    echo json_encode([
        'success'   => true,
        'message'   => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url'   => $pdfUrl,
        'data'      => [
            // legacy keys (primary option) for the current front-end:
            'system_size'      => $calc['recommended_pv_kw'],
            'panels'           => $calc['panels_qty'],
            'battery_capacity' => $calc['recommended_battery_kwh'],
            'estimated_cost'   => $calc['grand_total'],
            'monthly_savings'  => $calc['monthly_savings'],
            'payback_years'    => number_format($calc['payback_years'], 2),
            'roi'              => number_format($calc['roi_20_years'], 2),
            'co2_saved'        => number_format($calc['co2_tons_year'], 2),
            'power_system'     => $calc['power_source_label'],
            'items'            => $calc['items'],
            'warnings'         => $calc['warnings'],
            // new dual-option payload for the Part-2 front-end:
            'requirements' => [
                'total_load_w' => $calc['total_load_w'],
                'daily_kwh' => $calc['daily_kwh'],
                'required_pv_kw' => $calc['required_pv_kw'],
                'required_inverter_kw' => $calc['required_inverter_kw'],
                'required_battery_kwh' => $calc['required_battery_kwh'],
            ],
            'options' => [
                'generator' => $optionSummary($optionA),
                'custom'    => $optionSummary($optionB),
            ],
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
