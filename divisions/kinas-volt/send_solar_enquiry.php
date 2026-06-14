<?php
// NO extra output, NO spaces before <?php
header('Content-Type: application/json');

error_log("=== send_solar_enquiry.php called ===");

require_once '../../api/config/database.php';

$db = Database::getInstance()->getConnection();

// Get POST data
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received']);
    exit;
}

$full_name = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$monthly_bill = floatval($data['monthly_bill'] ?? 0);
$system_size = floatval($data['system_size'] ?? 0);
$annual_savings = floatval($data['annual_savings'] ?? 0);
$payback_years = floatval($data['payback_years'] ?? 0);

error_log("Parsed: name=$full_name, email=$email, phone=$phone");

// Validate
if (empty($full_name) || empty($email) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in your name, email, and phone number']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    error_log("Table verified");
} catch (PDOException $e) {
    error_log("Table error: " . $e->getMessage());
}

// Save to database
try {
    $stmt = $db->prepare("INSERT INTO solar_enquiries (full_name, email, phone, monthly_bill, system_size, annual_savings, payback_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $email, $phone, $monthly_bill, $system_size, $annual_savings, $payback_years]);
    $insertId = $db->lastInsertId();
    error_log("Saved to DB with ID: $insertId");
} catch (PDOException $e) {
    error_log("DB insert error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error, please try again']);
    exit;
}

// Try to send email using your EmailService (don't fail if it doesn't work)
try {
    require_once '../../includes/email.php';
    $emailService = new EmailService();
    
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
        <p><strong>Monthly Bill:</strong> ₦" . number_format($monthly_bill, 2) . "</p>
        <p><strong>System Size:</strong> {$system_size} kWp</p>
        <p><strong>Annual Savings:</strong> ₦" . number_format($annual_savings, 2) . "</p>
        <p><strong>Payback:</strong> {$payback_years} years</p>
        <hr>
        <p>Customer requested a follow-up quote.</p>
    </body>
    </html>
    ";
    
    $emailService->send($email, $subject, $html_content);
    $emailService->send('info@kinasauto.com', $subject, $html_content);
    error_log("Emails sent");
} catch (Exception $e) {
    error_log("Email error: " . $e->getMessage());
    // Don't exit - still return success for the database save
}

// Return success
echo json_encode(['success' => true, 'message' => 'Enquiry sent! We will contact you within 24 hours.']);
error_log("Response sent successfully");
exit;
?>
