<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../api/config/database.php';
require_once '../../includes/email.php';

// Create a log file for debugging
$logFile = __DIR__ . '/solar_debug.log';

function debug_log($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND);
}

debug_log("=== Script started ===");

$db = Database::getInstance()->getConnection();

// Get POST data
$input = file_get_contents('php://input');
debug_log("Raw input: " . $input);

$data = json_decode($input, true);
debug_log("Decoded data: " . print_r($data, true));

if (!$data) {
    debug_log("Failed to decode JSON");
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$monthly_bill = floatval($data['monthly_bill'] ?? 0);
$system_size = floatval($data['system_size'] ?? 0);
$annual_savings = floatval($data['annual_savings'] ?? 0);
$payback_years = floatval($data['payback_years'] ?? 0);

debug_log("Parsed values - Name: $full_name, Email: $email, Phone: $phone, Bill: $monthly_bill");

// Validate
if (empty($full_name) || empty($email) || empty($phone)) {
    debug_log("Validation failed - missing fields");
    echo json_encode(['success' => false, 'message' => 'Please fill in your name, email, and phone number']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    debug_log("Validation failed - invalid email");
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Create table if not exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS solar_enquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            monthly_bill DECIMAL(10,2) NOT NULL,
            system_size DECIMAL(10,2) NOT NULL,
            annual_savings DECIMAL(12,2) NOT NULL,
            payback_years DECIMAL(5,2) NOT NULL,
            status ENUM('new', 'contacted', 'converted') DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    debug_log("Table verified/created");
} catch (PDOException $e) {
    debug_log("Table error: " . $e->getMessage());
}

// Save to database
try {
    $stmt = $db->prepare("INSERT INTO solar_enquiries (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$full_name, $email, $phone, $monthly_bill, $system_size, $annual_savings, $payback_years]);
    $insertId = $db->lastInsertId();
    debug_log("Database insert result: " . ($result ? "SUCCESS (ID: $insertId)" : "FAILED"));
} catch (PDOException $e) {
    debug_log("Database insert error: " . $e->getMessage());
}

// Initialize EmailService
try {
    $emailService = new EmailService();
    debug_log("EmailService initialized");
    
    // Check if Resend is configured
    $reflection = new ReflectionClass($emailService);
    $prop = $reflection->getProperty('useResend');
    $prop->setAccessible(true);
    $useResend = $prop->getValue($emailService);
    debug_log("useResend = " . ($useResend ? 'true' : 'false'));
    
    $prop2 = $reflection->getProperty('resendApiKey');
    $prop2->setAccessible(true);
    $apiKey = $prop2->getValue($emailService);
    debug_log("API Key present: " . (empty($apiKey) ? 'NO' : 'YES (first 10 chars: ' . substr($apiKey, 0, 10) . '...)'));
    
} catch (Exception $e) {
    debug_log("EmailService error: " . $e->getMessage());
}

$subject = "New Solar Enquiry from {$full_name}";
$html_content = "
<!DOCTYPE html>
<html>
<head><title>New Solar Enquiry</title></head>
<body style='font-family: Arial, sans-serif; padding: 20px;'>
    <h2 style='color: #2c7a47;'>☀️ New Solar Enquiry - KINAS Volt</h2>
    <p><strong>Customer Name:</strong> " . htmlspecialchars($full_name) . "</p>
    <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
    <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
    <hr>
    <h3>📊 Solar Savings Estimate</h3>
    <p><strong>Monthly Bill:</strong> ₦" . number_format($monthly_bill, 2) . "</p>
    <p><strong>Recommended System Size:</strong> {$system_size} kWp</p>
    <p><strong>Estimated Annual Savings:</strong> ₦" . number_format($annual_savings, 2) . "</p>
    <p><strong>Payback Period:</strong> {$payback_years} years</p>
    <hr>
    <p style='color: #666;'>Customer requested a follow-up quote. Please contact them within 24 hours.</p>
    <p><small>Enquiry ID: " . ($insertId ?? 'N/A') . "</small></p>
</body>
</html>
";

// Send emails
$emailSent = false;
try {
    // Send to customer
    $toCustomer = $emailService->send($email, $subject, $html_content);
    debug_log("Email to customer ($email): " . ($toCustomer ? "SUCCESS" : "FAILED"));
    
    // Send to admin - CHANGE THIS TO YOUR EMAIL
    $adminEmail = "info@kinasauto.com";
    $toAdmin = $emailService->send($adminEmail, $subject, $html_content);
    debug_log("Email to admin ($adminEmail): " . ($toAdmin ? "SUCCESS" : "FAILED"));
    
    $emailSent = ($toCustomer || $toAdmin);
} catch (Exception $e) {
    debug_log("Email exception: " . $e->getMessage());
    $emailSent = false;
}

// Return response
$response = [
    'success' => true,
    'message' => $emailSent ? 'Enquiry sent! We will contact you within 24 hours.' : 'Enquiry saved! We will contact you soon.',
    'debug' => [
        'db_saved' => true,
        'email_sent' => $emailSent
    ]
];

debug_log("Sending response: " . json_encode($response));
echo json_encode($response);
