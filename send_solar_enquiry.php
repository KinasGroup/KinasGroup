<?php
header('Content-Type: application/json');
require_once '../../api/config/database.php';
require_once '../../includes/email.php';

// Enable error logging for debugging
error_log("=== send_solar_enquiry.php called ===");

$db = Database::getInstance()->getConnection();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
error_log("Received data: " . json_encode($data));

$full_name = $data['full_name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$monthly_bill = $data['monthly_bill'] ?? 0;
$system_size = $data['system_size'] ?? 0;
$annual_savings = $data['annual_savings'] ?? 0;
$payback_years = $data['payback_years'] ?? 0;

// Validate
if (empty($full_name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Save to database
try {
    $stmt = $db->prepare("INSERT INTO solar_enquiries (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $email, $phone, $monthly_bill, $system_size, $annual_savings, $payback_years]);
    error_log("Database saved successfully, ID: " . $db->lastInsertId());
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Initialize EmailService
$emailService = new EmailService();
error_log("EmailService initialized - useResend: " . ($emailService->useResend ? 'true' : 'false'));
error_log("Resend API Key present: " . (empty($emailService->resendApiKey) ? 'NO' : 'YES (length: ' . strlen($emailService->resendApiKey) . ')'));

$subject = "New Solar Enquiry from {$full_name}";
$html_content = "
<!DOCTYPE html>
<html>
<head><title>New Solar Enquiry</title></head>
<body style='font-family: Arial, sans-serif; padding: 20px;'>
    <h2 style='color: #2c7a47;'>☀️ New Solar Enquiry - KINAS Volt</h2>
    <p><strong>Customer Name:</strong> {$full_name}</p>
    <p><strong>Email:</strong> {$email}</p>
    <p><strong>Phone:</strong> {$phone}</p>
    <hr>
    <h3>📊 Solar Savings Estimate</h3>
    <p><strong>Monthly Bill:</strong> ₦" . number_format($monthly_bill, 2) . "</p>
    <p><strong>Recommended System Size:</strong> {$system_size} kWp</p>
    <p><strong>Estimated Annual Savings:</strong> ₦" . number_format($annual_savings, 2) . "</p>
    <p><strong>Payback Period:</strong> {$payback_years} years</p>
    <hr>
    <p style='color: #666;'>Customer requested a follow-up quote. Please contact them within 24 hours.</p>
</body>
</html>
";

// Send email (this will use Resend if configured, otherwise PHP mail)
$result = $emailService->send($email, $subject, $html_content);
error_log("Email send result (to customer): " . ($result ? 'SUCCESS' : 'FAILED'));

// Also send copy to admin
$adminEmail = "info@kinasauto.com"; // CHANGE THIS TO YOUR EMAIL
$adminResult = $emailService->send($adminEmail, $subject, $html_content);
error_log("Email send result (to admin {$adminEmail}): " . ($adminResult ? 'SUCCESS' : 'FAILED'));

if ($result || $adminResult) {
    echo json_encode(['success' => true, 'message' => 'Enquiry sent! We will contact you within 24 hours.']);
} else {
    echo json_encode(['success' => true, 'message' => 'Enquiry saved! We will contact you soon.']);
}
