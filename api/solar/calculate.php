<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';
require_once __DIR__ . '/../../includes/notify.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Calculate values (simplified - expand as needed)
$totalLoad = (int)($_POST['total_load_watts'] ?? 0);
$dailyKwh = (float)($_POST['daily_kwh'] ?? 0);
$reference = 'KV-SOL-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));

$pdfPath = generateSolarRecommendationPDF($_POST, $reference);

// Save lead
$stmt = $db->prepare("INSERT INTO solar_leads 
    (reference_number, full_name, email, phone, city_state, property_type, 
     total_load_watts, daily_kwh, backup_hours, pdf_path)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $reference,
    $_POST['full_name'] ?? '',
    $_POST['email'] ?? '',
    $_POST['phone'] ?? '',
    $_POST['city_state'] ?? '',
    $_POST['property_type'] ?? '',
    $totalLoad,
    $dailyKwh,
    (int)($_POST['backup_hours'] ?? 24),
    $pdfPath
]);

// Send emails
$clientEmail = $_POST['email'] ?? '';
$clientName = $_POST['full_name'] ?? 'Valued Client';

if (!empty($clientEmail)) {
    // To Client
    Notify::email(
        $clientEmail, 
        "Your Kinas Volt Solar Proposal - Ref: $reference", 
        "<p>Dear $clientName,</p><p>Thank you for using our Solar Calculator. Please find your personalized proposal attached.</p><p>Our team will contact you shortly.</p>",
        $pdfPath
    );

    // To Back Office
    Notify::email(
        'listing@kinas-group.com', 
        "New Solar Lead: $reference - $clientName", 
        "New lead from calculator.<br>Client: $clientName ($clientEmail)",
        $pdfPath
    );
}

echo json_encode([
    'success' => true,
    'reference' => $reference,
    'message' => 'Proposal generated and emailed successfully!'
]);
