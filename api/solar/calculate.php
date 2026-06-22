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

    if (!$pdfPath || !file_exists($pdfPath)) {
        throw new Exception('Failed to generate PDF. Please try again.');
    }

    $pdfUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/uploads/solar-reports/' . $reference . '.pdf';

    // ============================================================
    // SEND EMAILS - USING PUBLIC sendEmail() METHOD
    // ============================================================
    $emailService = new EmailService();

    // Customer email
    $customerSubject = 'Your Solar Proposal from KINAS VOLT - ' . $reference;
    $customerBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #2C2C2C; }
            .header { background: #0A0A0A; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { color: #C6A43F; font-family: "Prata", serif; margin: 0; font-size: 24px; }
            .content { background: #FFFFFF; padding: 30px; border: 1px solid #E0E0E0; border-top: none; border-radius: 0 0 8px 8px; }
            .btn { display: inline-block; padding: 12px 30px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: bold; }
            .footer { text-align: center; padding-top: 20px; font-size: 11px; color: #999; border-top: 1px solid #E0E0E0; margin-top: 20px; }
            .info-box { background: #F8F6F1; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #C6A43F; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>☀️ KINAS VOLT</h1>
            <p>Premium Solar Energy Solutions</p>
        </div>
        <div class="content">
            <h2>Your Solar Proposal is Ready!</h2>
            <p>Dear ' . htmlspecialchars($fullName) . ',</p>
            <p>Thank you for using the KINAS VOLT Solar Calculator.</p>
            
            <div class="info-box">
                <strong>📄 Proposal Details:</strong><br>
                <strong>Reference:</strong> ' . $reference . '<br>
                <strong>System Size:</strong> ' . $systemSize . ' kWp<br>
                <strong>Investment:</strong> ₦' . number_format($estimatedCost) . '<br>
                <strong>Monthly Savings:</strong> ₦' . number_format($monthlySavings) . '
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $pdfUrl . '" class="btn">📄 View Your Proposal</a>
            </p>
            
            <div class="footer">
                KINAS GROUP • Gwarimpa, Abuja • +234 913 717 5523
            </div>
        </div>
    </body>
    </html>';

    // Send email to customer using the public sendEmail method
    $customerSent = $emailService->sendEmail($email, $customerSubject, $customerBody, 'listings@kinas-group.com', 'KINAS VOLT Solar Division');

    // Admin email
    $adminSubject = 'New Solar Enquiry - ' . $reference . ' - ' . $fullName;
    $adminBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #2C2C2C; }
            .header { background: #0A0A0A; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { color: #C6A43F; font-family: "Prata", serif; margin: 0; font-size: 24px; }
            .content { background: #FFFFFF; padding: 30px; border: 1px solid #E0E0E0; border-top: none; border-radius: 0 0 8px 8px; }
            .btn { display: inline-block; padding: 12px 30px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: bold; }
            .footer { text-align: center; padding-top: 20px; font-size: 11px; color: #999; border-top: 1px solid #E0E0E0; margin-top: 20px; }
            .info-box { background: #F8F6F1; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #C6A43F; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>☀️ KINAS VOLT</h1>
            <p>New Solar Enquiry Received</p>
        </div>
        <div class="content">
            <h2>New Solar Enquiry</h2>
            
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
                <strong>Estimated Cost:</strong> ₦' . number_format($estimatedCost) . '
            </div>
            
            <p style="text-align: center; margin: 20px 0;">
                <a href="' . $pdfUrl . '" class="btn">📄 View Proposal PDF</a>
            </p>
            
            <div class="footer">
                KINAS GROUP • Gwarimpa, Abuja • +234 913 717 5523
            </div>
        </div>
    </body>
    </html>';

    // Send email to admin using the public sendEmail method
    $adminSent = $emailService->sendEmail('admin@kinas-group.com', $adminSubject, $adminBody, 'listings@kinas-group.com', 'KINAS VOLT Solar Division');

    // Save to database
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO solar_enquiries 
        (full_name, email, phone, city_state, property_type, monthly_bill, system_size, 
         annual_savings, payback_years, status, reference, appliances_json, total_load_watts, 
         daily_kwh, backup_hours, estimated_cost, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $cityState,
        $propertyType,
        $monthlySavings,
        $systemSize,
        $monthlySavings * 12,
        $paybackYears,
        $reference,
        json_encode($appliances),
        $totalLoad,
        $dailyKwh,
        $backupHours,
        $estimatedCost
    ]);

    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url' => $pdfUrl,
        'emails_sent' => [
            'customer' => $customerSent,
            'admin' => $adminSent
        ],
        'data' => [
            'system_size' => $systemSize,
            'panels' => $recommendedPanels,
            'battery_capacity' => $batteryCapacity,
            'estimated_cost' => $estimatedCost,
            'monthly_savings' => $monthlySavings,
            'payback_years' => $paybackYears,
            'roi' => (($monthlySavings * 12 * 20) / $estimatedCost) * 100,
            'co2_saved' => $co2Saved
        ]
    ]);

} catch (Exception $e) {
    error_log('Solar Calculator Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
