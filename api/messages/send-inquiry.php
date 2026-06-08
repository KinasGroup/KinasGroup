<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/email.php';
require_once '../../includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// FIX: Rate-limit by IP — max 5 inquiries per 10 minutes
Security::rateLimitDB('inquiry_' . Security::getClientIP(), 5, 600);

// Accept both form-POST and JSON bodies
$data = $_SERVER['CONTENT_TYPE'] && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

// FIX: Honeypot field — bots fill it, humans leave it empty
if (!empty($data['website'])) {
    // Silently succeed to not tip off bots
    echo json_encode(['success' => true, 'message' => 'Inquiry sent']);
    exit;
}

// FIX: Sanitise all inputs
$listingId   = (int)($data['listing_id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';
$name        = Security::sanitizeInput($data['name'] ?? '');
$email       = trim($data['email'] ?? '');
$phone       = Security::sanitizeInput($data['phone'] ?? '');
$message     = Security::sanitizeInput($data['message'] ?? '');

// Validate required fields
if (!$listingId || !$name || !$message) {
    http_response_code(422);
    echo json_encode(['error' => 'Name, listing, and message are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide a valid email address']);
    exit;
}

if (strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode(['error' => 'Message is too long (max 2000 characters)']);
    exit;
}

$tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings'];
if (!array_key_exists($listingType, $tableMap)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}
$table = $tableMap[$listingType];

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT agent_id, title FROM $table WHERE id = ? AND status = 'active'");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
        exit;
    }

    $stmt = $db->prepare("SELECT email, name, phone, phone_verified_at FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$listing['agent_id']]);
    $agent = $stmt->fetch();

    if ($agent) {
        $emailService = new EmailService();
        $emailService->sendNewInquiry(
            $agent['email'], $agent['name'],
            $listing['title'], $name, $email, $message
        );

        // SMS notify the agent (only if their phone is verified)
        if (!empty($agent['phone']) && !empty($agent['phone_verified_at'])) {
            Notify::sms(
                $agent['phone'],
                "New inquiry on KINAS GROUP for \"{$listing['title']}\" from {$name}. Open your dashboard to reply.",
                'NEW_INQUIRY'
            );
        }
    }

    // Log the inquiry
    Security::logActivity(
        SessionManager::getUserId(),
        'inquiry_sent',
        "Inquiry for {$listingType} #{$listingId} from {$email}"
    );

    echo json_encode(['success' => true, 'message' => 'Inquiry sent successfully']);

} catch (Exception $e) {
    error_log('Inquiry error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send inquiry']);
}
