<?php
// api/solar/calculate.php
// Solar calculator API endpoint

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON response header
header('Content-Type: application/json');

try {
    // Include required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../../includes/session.php';
    require_once __DIR__ . '/../../includes/security.php';
    require_once __DIR__ . '/../../includes/email.php';
    require_once __DIR__ . '/../../includes/solar-pdf.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Security::verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    // Get and validate input
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cityState = trim($_POST['city_state'] ?? '');
    $propertyType = trim($_POST['property_type'] ?? '');
    $backupHours = (int)($_POST['backup_hours'] ?? 24);
    $appliancesJson = $_POST['appliances'] ?? '[]';
    $appliances = json_decode($appliancesJson, true);

    // Validate required fields
    if (empty($fullName) || empty($phone) || empty($email) || empty($cityState) || empty($propertyType)) {
        throw new Exception('Please fill in all required fields.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }

    if (empty($appliances) || !is_array($appliances)) {
        throw new Exception('Please add at least one appliance.');
    }

    // Calculate total load
    $totalLoad = 0;
    foreach ($appliances as $appliance) {
        $totalLoad += ($appliance['quantity'] ?? 1) * ($appliance['watts'] ?? 0);
    }

    if ($totalLoad <= 0) {
        throw new Exception('Total load calculation failed. Please check your appliance wattages.');
    }

    // Calculate daily consumption
    $dailyKwh = ($totalLoad * 8) / 1000;

    // Calculate system size
    $sunHours = 4.5;
    $systemSize = ceil(($dailyKwh / $sunHours) * 1.2);

    // Calculate recommended components
    $recommendedPanels = max(8, ceil($systemSize * 1000 / 550));
    $recommendedInverter = $systemSize <= 5 ? '8kVA Hybrid Inverter' : ($systemSize <= 10 ? '12kVA Hybrid Inverter' : '20kVA Hybrid Inverter');
    $batteryCapacity = max(100, ceil($dailyKwh * 1.2 * 1000 / 48 / 100) * 100);
    $recommendedBattery = '48V ' . $batteryCapacity . 'Ah Lithium LiFePO4';
    $batteryUnits = max(2, ceil($dailyKwh * 1.5 / 10));

    // Calculate costs
    $panelPrice = 450000;
    $inverterPrice = 3500000;
    $batteryPrice = 2800000;
    $installationCost = 1500000;
    $cablingCost = 500000;
    $mountingCost = 400000;
    $transportCost = 200000;

    $panelTotal = $panelPrice * $recommendedPanels;
    $batteryTotal = $batteryPrice * $batteryUnits;
    $hardwareSubtotal = $panelTotal + $inverterPrice + $batteryTotal;
    $otherCosts = $installationCost + $cablingCost + $mountingCost + $transportCost;
    $estimatedCost = $hardwareSubtotal + $otherCosts;

    // Calculate savings
    $monthlyConsumptionKwh = $dailyKwh * 30;
    $tariffPerKwh = 225;
    $monthlySavings = $monthlyConsumptionKwh * $tariffPerKwh;
    $paybackYears = $estimatedCost / ($monthlySavings * 12);
    $co2Saved = ($dailyKwh * 0.85 / 2204.62) * 365;

    // Prepare data for PDF
    $pdfData = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'city_state' => $cityState,
        'property_type' => $propertyType,
        'total_load_watts' => $totalLoad,
        'daily_kwh' => $dailyKwh,
        'backup_hours' => $backupHours,
        'system_size' => $systemSize,
        'recommended_panels' => $recommendedPanels,
        'recommended_inverter' => $recommendedInverter,
        'recommended_battery' => $recommendedBattery,
        'battery_units' => $batteryUnits,
        'estimated_cost' => $estimatedCost,
        'monthly_savings' => $monthlySavings,
        'payback_years' => $paybackYears,
        'roi' => (($monthlySavings * 12 * 20) / $estimatedCost) * 100,
        'co2_saved' => $co2Saved,
        'appliances' => $appliances
    ];

    // Generate reference number
    $reference = 'SOL-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));

    // Generate PDF
    $pdfPath = generateSolarRecommendationPDF($pdfData, $reference);
    $pdfUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/solar-reports/' . $reference . '.pdf';

    // ============================================================
    // SEND EMAILS - BOTH CUSTOMER AND ADMIN
    // ============================================================
    $emailService = new EmailService();

    // ----- CUSTOMER EMAIL -----
    $customerSubject = 'Your Solar Proposal from KINAS VOLT - ' . $reference;
    $customerBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #2C2C2C; }
            .content { background: #FFFFFF; padding: 30px; }
            .btn { display: inline-block; padding: 12px 30px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 10px 0; }
            .info-box { background: #F8F6F1; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #C6A43F; }
            .highlight { color: #C6A43F; font-weight: bold; }
        </style>
    </head>
    <body>
        <div style="background: #0A0A0A; padding: 20px; text-align: center;">
            <h1 style="color: #C6A43F; font-family: Prata, serif; margin: 0;">KINAS GROUP</h1>
            <p style="color: rgba(255,255,255,0.5); margin: 4px 0 0;">KINAS VOLT - Solar Division</p>
        </div>
        <div class="content">
            <h2 style="color: #0A0A0A; font-family: Prata, serif;">Your Solar Proposal is Ready!</h2>
            <p>Dear ' . htmlspecialchars($fullName) . ',</p>
            <p>Thank you for using the KINAS VOLT Solar Calculator. Based on your inputs, we have prepared a professional solar proposal for you.</p>
            
            <div class="info-box">
                <strong>📄 Proposal Details:</strong><br>
                <strong>Reference Number:</strong> ' . $reference . '<br>
                <strong>System Size:</strong> ' . $systemSize . ' kWp<br>
                <strong>Estimated Investment:</strong> ₦' . number_format($estimatedCost) . '<br>
                <strong>Monthly Savings:</strong> ₦' . number_format($monthlySavings) . '
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $pdfUrl . '" class="btn">📄 View/Download Your Proposal</a>
            </p>
            
            <p><strong>What happens next?</strong></p>
            <ol>
                <li>Review your proposal</li>
                <li>Our team will contact you within 24 hours</li>
                <li>Schedule a free site assessment</li>
                <li>Receive your final quotation</li>
            </ol>
            
            <p>If you have any questions, feel free to reply to this email or call us at <strong>+234 913 717 5523</strong>.</p>
            
            <hr style="border: none; border-top: 2px solid #C6A43F; margin: 20px 0;">
            
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="font-size: 12px; color: #666; line-height: 1.6;">
                        <strong>KINAS GROUP OF COMPANIES LIMITED</strong><br>
                        RC Number: 7997266<br>
                        Gwarinpa, 900108, Federal Capital Territory, Nigeria<br>
                        Phone: <a href="tel:+2349137175523" style="color: #C6A43F; text-decoration: none;">+234 913 717 5523</a><br>
                        Email: <a href="mailto:support@kinas-group.com" style="color: #C6A43F; text-decoration: none;">support@kinas-group.com</a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding-top: 10px; font-size: 10px; color: #999;">
                        &copy; ' . date('Y') . ' KINAS GROUP OF COMPANIES LIMITED. All rights reserved.
                    </td>
                </tr>
            </table>
        </div>
    </body>
    </html>';

    // Send to CUSTOMER
    $customerSent = $emailService->send($email, $fullName, $customerSubject, $customerBody, strip_tags($customerBody));

    // ----- ADMIN EMAIL -----
    $adminEmail = 'admin@kinas-group.com';
    $adminSubject = '🔔 NEW Solar Enquiry - ' . $reference . ' - ' . $fullName;
    $adminBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #2C2C2C; }
            .content { background: #FFFFFF; padding: 30px; }
            .info-box { background: #F8F6F1; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #C6A43F; }
            .highlight { color: #C6A43F; font-weight: bold; }
        </style>
    </head>
    <body>
        <div style="background: #0A0A0A; padding: 20px; text-align: center;">
            <h1 style="color: #C6A43F; font-family: Prata, serif; margin: 0;">KINAS GROUP</h1>
            <p style="color: rgba(255,255,255,0.5); margin: 4px 0 0;">KINAS VOLT - Solar Division</p>
        </div>
        <div class="content">
            <h2 style="color: #0A0A0A; font-family: Prata, serif;">🔔 New Solar Enquiry Received</h2>
            <p>A new solar enquiry has been submitted through the calculator.</p>
            
            <div class="info-box">
                <strong>👤 Customer Details:</strong><br>
                <strong>Name:</strong> ' . htmlspecialchars($fullName) . '<br>
                <strong>Email:</strong> ' . htmlspecialchars($email) . '<br>
                <strong>Phone:</strong> ' . htmlspecialchars($phone) . '<br>
                <strong>Location:</strong> ' . htmlspecialchars($cityState) . '<br>
                <strong>Property Type:</strong> ' . htmlspecialchars($propertyType) . '
            </div>
            
            <div class="info-box">
                <strong>📊 System Details:</strong><br>
                <strong>Reference:</strong> ' . $reference . '<br>
                <strong>System Size:</strong> ' . $systemSize . ' kWp<br>
                <strong>Daily Consumption:</strong> ' . number_format($dailyKwh, 2) . ' kWh<br>
                <strong>Backup Hours:</strong> ' . $backupHours . ' hours<br>
                <strong>Estimated Cost:</strong> ₦' . number_format($estimatedCost) . '<br>
                <strong>Monthly Savings:</strong> ₦' . number_format($monthlySavings) . '
            </div>
            
            <p style="text-align: center; margin: 20px 0;">
                <a href="' . $pdfUrl . '" style="display: inline-block; padding: 12px 30px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: bold;">📄 View PDF Proposal</a>
            </p>
            
            <hr style="border: none; border-top: 2px solid #C6A43F; margin: 20px 0;">
            
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="font-size: 12px; color: #666; line-height: 1.6;">
                        <strong>KINAS GROUP OF COMPANIES LIMITED</strong><br>
                        RC Number: 7997266<br>
                        Gwarinpa, 900108, Federal Capital Territory, Nigeria<br>
                        Phone: <a href="tel:+2349137175523" style="color: #C6A43F; text-decoration: none;">+234 913 717 5523</a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding-top: 10px; font-size: 10px; color: #999;">
                        &copy; ' . date('Y') . ' KINAS GROUP OF COMPANIES LIMITED. All rights reserved.
                    </td>
                </tr>
            </table>
        </div>
    </body>
    </html>';

    // Send to ADMIN
    $adminSent = $emailService->send($adminEmail, 'Admin', $adminSubject, $adminBody, strip_tags($adminBody));

    // ============================================================
    // SAVE TO DATABASE
    // ============================================================
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists, create if not
    try {
        $db->query("SELECT 1 FROM solar_enquiries LIMIT 1");
    } catch (PDOException $e) {
        // Table doesn't exist, create it
        $db->exec("
            CREATE TABLE IF NOT EXISTS solar_enquiries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                monthly_bill DECIMAL(15,2),
                system_size DECIMAL(5,2),
                annual_savings DECIMAL(15,2),
                payback_years DECIMAL(5,2),
                status VARCHAR(20) DEFAULT 'new',
                created_at DATETIME,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    $stmt = $db->prepare("
        INSERT INTO solar_enquiries 
        (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW())
    ");

    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $monthlySavings,
        $systemSize,
        $monthlySavings * 12,
        $paybackYears
    ]);

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url' => $pdfUrl,
        'data' => [
            'system_size' => $systemSize,
            'panels' => $recommendedPanels,
            'battery_capacity' => $batteryCapacity,
            'estimated_cost' => $estimatedCost,
            'monthly_savings' => $monthlySavings,
            'payback_years' => number_format($paybackYears, 2),
            'roi' => number_format((($monthlySavings * 12 * 20) / $estimatedCost) * 100, 2),
            'co2_saved' => number_format($co2Saved, 2)
        ],
        'emails_sent' => [
            'customer' => $customerSent ? 'sent' : 'failed',
            'admin' => $adminSent ? 'sent' : 'failed'
        ]
    ]);

} catch (Exception $e) {
    error_log('Solar Calculator Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
