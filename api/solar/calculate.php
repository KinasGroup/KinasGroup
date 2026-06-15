<?php
// api/solar/calculate.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';
require_once __DIR__ . '/../../includes/notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh and try again.']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // ====================== INPUT DATA ======================
    $full_name     = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $city_state    = trim($_POST['city_state'] ?? '');
    $property_type = trim($_POST['property_type'] ?? '');
    $backup_hours  = (int)($_POST['backup_hours'] ?? 24);

    // Appliance data will come as arrays from JS
    $appliance_names  = $_POST['appliance_name'] ?? [];
    $appliance_watts  = $_POST['appliance_watt'] ?? [];
    $appliance_qty    = $_POST['appliance_qty'] ?? [];
    $appliance_hours  = $_POST['appliance_hours'] ?? [];

    // ====================== CALCULATIONS ======================
    $total_load_watts = 0;
    $daily_energy_wh  = 0;

    for ($i = 0; $i < count($appliance_names); $i++) {
        $name = trim($appliance_names[$i] ?? '');
        $watt = (int)($appliance_watts[$i] ?? 0);
        $qty  = (int)($appliance_qty[$i] ?? 1);
        $hrs  = (float)($appliance_hours[$i] ?? 4);

        if ($name && $watt > 0) {
            $total_load_watts += $watt * $qty;
            $daily_energy_wh  += $watt * $qty * $hrs;
        }
    }

    $daily_kwh = round($daily_energy_wh / 1000, 2);

    // Smart Recommendations
    $recommended_inverter = $total_load_watts > 8000 ? '15kVA Hybrid' : 
                           ($total_load_watts > 5000 ? '10kVA Hybrid' : '8kVA Hybrid');

    $recommended_battery = $backup_hours >= 24 ? '48V 200Ah Lithium (x2)' : '48V 200Ah Lithium (x1)';

    $recommended_panels = max(8, ceil(($daily_kwh * 1000) / (550 * 5.2 * 0.78))); // 5.2 sun hours, 78% efficiency

    $estimated_cost = $total_load_watts * 1800 + ($daily_kwh * 450000); // Rough formula

    $reference = 'KV-SOL-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));

    // ====================== GENERATE PDF ======================
    $pdfPath = generateSolarRecommendationPDF($_POST + [
        'total_load_watts' => $total_load_watts,
        'daily_kwh'        => $daily_kwh,
        'recommended_inverter' => $recommended_inverter,
        'recommended_battery'  => $recommended_battery,
        'recommended_panels'   => $recommended_panels,
        'estimated_cost'       => $estimated_cost
    ], $reference);

    // ====================== SAVE LEAD ======================
    $stmt = $db->prepare("INSERT INTO solar_leads 
        (reference_number, full_name, email, phone, city_state, property_type, 
         total_load_watts, daily_kwh, backup_hours, recommended_inverter, 
         recommended_battery, recommended_panels, estimated_cost, pdf_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $reference, $full_name, $email, $phone, $city_state, $property_type,
        $total_load_watts, $daily_kwh, $backup_hours, $recommended_inverter,
        $recommended_battery, $recommended_panels, $estimated_cost, $pdfPath
    ]);

    // ====================== SEND EMAILS ======================
    if (!empty($email)) {
        // To Client
        Notify::email(
            $email,
            "Your Kinas Volt Solar Proposal - Ref: $reference",
            "
            <p>Dear <strong>$full_name</strong>,</p>
            <p>Thank you for using our Solar Calculator. Your personalized professional proposal is attached.</p>
            <p>Our solar technical team will contact you within 24 hours to arrange a free site assessment.</p>
            <p><strong>Reference:</strong> $reference</p>
            ",
            $pdfPath
        );

        // To Back Office
        Notify::email(
            'listing@kinas-group.com',
            "New Solar Lead: $reference - $full_name",
            "
            <p><strong>New lead from Solar Calculator</strong></p>
            <p><strong>Name:</strong> $full_name<br>
               <strong>Email:</strong> $email<br>
               <strong>Phone:</strong> $phone<br>
               <strong>Location:</strong> $city_state</p>
            <p><strong>Total Load:</strong> " . number_format($total_load_watts) . "W</p>
            ",
            $pdfPath
        );
    }

    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'message' => 'Proposal generated successfully!',
        'pdf_url' => '/uploads/solar-reports/' . basename($pdfPath)
    ]);

} catch (Exception $e) {
    error_log("Solar Calculator Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while generating your proposal. Please try again.'
    ]);
}
