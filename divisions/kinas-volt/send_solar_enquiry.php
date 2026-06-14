<?php
header('Content-Type: application/json');

require_once '../../api/config/database.php';

$db = Database::getInstance()->getConnection();

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$monthly_bill = floatval($data['monthly_bill'] ?? 0);
$system_size = floatval($data['system_size'] ?? 0);
$annual_savings = floatval($data['annual_savings'] ?? 0);
$payback_years = floatval($data['payback_years'] ?? 0);

if (empty($full_name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all fields']);
    exit;
}

// Create table if needed
$db->exec("CREATE TABLE IF NOT EXISTS solar_enquiries (
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
)");

// Save to database
$stmt = $db->prepare("INSERT INTO solar_enquiries (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$full_name, $email, $phone, $monthly_bill, $system_size, $annual_savings, $payback_years]);

echo json_encode(['success' => true, 'message' => 'Enquiry sent! We will contact you within 24 hours.']);
