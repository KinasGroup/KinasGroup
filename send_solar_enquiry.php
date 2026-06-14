<?php
header('Content-Type: application/json');
require_once '../../api/config/database.php';
require_once '../../includes/email.php'; // Your existing EmailService class

$db = Database::getInstance()->getConnection();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

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

// Create solar_enquiries table if not exists
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
} catch (PDOException $e) {
    // Table probably already exists
}

// Save to database
$stmt = $db->prepare("INSERT INTO solar_enquiries (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$full_name, $email, $phone, $monthly_bill, $system_size, $annual_savings, $payback_years]);

// Use your existing EmailService class
$emailService = new EmailService();

// Prepare the email content
$subject = "New Solar Enquiry from {$full_name}";
$html_content = "
<!DOCTYPE html>
<html>
<head><title>New Solar Enquiry</title></head>
<body style='font-family: Arial, sans-serif; padding: 20px;'>
    <h2 style='color: #1f3b2c;'>☀️ New Solar Enquiry - KINAS Automobile</h2>
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

// Send using your EmailService - it will auto-detect Resend if API key exists
$result = $emailService->send($email, $subject, $html_content);

// Also send a copy to admin (change to your business email)
$emailService->send('info@kinasauto.com', $subject, $html_content);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Enquiry sent! We will contact you within 24 hours.']);
} else {
    echo json_encode(['success' => true, 'message' => 'Enquiry saved! We will contact you soon.']);
}
