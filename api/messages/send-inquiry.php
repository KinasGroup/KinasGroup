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

// Rate-limit by IP
Security::rateLimitDB('inquiry_' . Security::getClientIP(), 5, 600);

// Accept both form-POST and JSON bodies
$data = $_SERVER['CONTENT_TYPE'] && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

// Honeypot field
if (!empty($data['website'])) {
    echo json_encode(['success' => true, 'message' => 'Inquiry sent']);
    exit;
}

// Sanitise all inputs
$listingId   = (int)($data['listing_id'] ?? 0);
$listingType = $data['listing_type'] ?? 'car';
$name        = Security::sanitizeInput($data['name'] ?? '');
$email       = trim($data['email'] ?? '');
$phone       = Security::sanitizeInput($data['phone'] ?? '');
$message     = Security::sanitizeInput($data['message'] ?? '');
$inquiryType = $data['inquiry_type'] ?? 'general';
$preferredDate = $data['preferred_date'] ?? '';
$preferredTime = $data['preferred_time'] ?? '';

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

// Validate date if viewing request
if ($inquiryType === 'viewing') {
    if (empty($preferredDate) || empty($preferredTime)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please select a date and time for viewing']);
        exit;
    }
}

$tableMap = ['car' => 'car_listings', 'property' => 'property_listings', 'marketplace' => 'marketplace_listings', 'solar' => 'solar_listings'];
if (!array_key_exists($listingType, $tableMap)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}
$table = $tableMap[$listingType];

try {
    $db = Database::getInstance()->getConnection();

    // Get listing details
    $stmt = $db->prepare("SELECT agent_id, title FROM $table WHERE id = ? AND status = 'active'");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        http_response_code(404);
        echo json_encode(['error' => 'Listing not found']);
        exit;
    }

    // ============================================================
    // GET LISTING AGENT
    // ============================================================
    $stmt = $db->prepare("SELECT id, email, name, phone, phone_verified_at FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$listing['agent_id']]);
    $listingAgent = $stmt->fetch();

    // ============================================================
    // GET SUPER AGENT (is_super_agent = 1)
    // ============================================================
    $stmt = $db->prepare("
        SELECT u.id, u.email, u.name, u.phone, u.phone_verified_at 
        FROM users u
        JOIN agent_profiles ap ON u.id = ap.user_id
        WHERE ap.is_super_agent = 1 AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute();
    $superAgent = $stmt->fetch();

    // ============================================================
    // BUILD MESSAGE CONTENT
    // ============================================================
    $isViewing = ($inquiryType === 'viewing');
    $subject = $isViewing 
        ? "New Viewing Request for {$listing['title']}"
        : "New Inquiry for {$listing['title']}";

    $emailBody = "
    <html>
    <head><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;}</style></head>
    <body>
        <h2 style='color:#C6A43F;'>" . ($isViewing ? "📅 New Viewing Request" : "📧 New Inquiry") . "</h2>
        <p><strong>Listing:</strong> {$listing['title']}</p>
        <p><strong>From:</strong> " . htmlspecialchars($name) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Phone:</strong> " . htmlspecialchars($phone ?: 'Not provided') . "</p>
        " . ($isViewing ? "
        <p><strong>Preferred Date:</strong> " . htmlspecialchars($preferredDate) . "</p>
        <p><strong>Preferred Time:</strong> " . htmlspecialchars($preferredTime) . "</p>
        " : "") . "
        <hr>
        <p><strong>Message:</strong></p>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
        <hr>
        <p style='font-size:12px;color:#888;'>This inquiry was sent via KINAS GROUP Platform</p>
    </body>
    </html>
    ";

    $plainMessage = strip_tags(str_replace(['<br>','</p>'], ["\n","\n"], $emailBody));

    // ============================================================
    // SEND TO LISTING AGENT
    // ============================================================
    if ($listingAgent) {
        // sendNewInquiry() was never a real method on EmailService — this
        // call fatal-errored (Error, not Exception, so the catch below
        // never even caught it) before the message was saved, on every
        // single inquiry/viewing request across all 4 divisions.
        Notify::email(
            $listingAgent['email'],
            $subject,
            strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n"], $emailBody)),
            null,
            SUPPORT_EMAIL,
            'KINAS GROUP Notifications'
        );

        // Also send the detailed email with date/time if viewing
        if ($isViewing) {
            send_email(
                $listingAgent['email'],
                $subject . " - Viewing Request",
                $emailBody,
                'no-reply@kinas-group.com'
            );
        }

        // SMS notify the listing agent
        if (!empty($listingAgent['phone']) && !empty($listingAgent['phone_verified_at'])) {
            $smsMsg = $isViewing
                ? "New viewing request for {$listing['title']} from {$name} on {$preferredDate} at {$preferredTime}. Check your dashboard."
                : "New inquiry on {$listing['title']} from {$name}. Open your dashboard to reply.";
            Notify::sms($listingAgent['phone'], $smsMsg, 'NEW_INQUIRY');
        }
    }

    // ============================================================
    // CONFIRMATION EMAIL TO THE REQUESTER
    // ============================================================
    // Previously nothing was ever sent back to the person who submitted
    // the inquiry/viewing request — they had no way of knowing it went
    // through at all beyond the on-page success message.
    $confirmSubject = $isViewing
        ? "Your viewing request for {$listing['title']} has been received"
        : "Your inquiry about {$listing['title']} has been received";
    $confirmBody = $isViewing
        ? "Hi {$name},\n\nYour request to view \"{$listing['title']}\" on {$preferredDate} at {$preferredTime} has been sent to the listing agent.\n\nThey'll be in touch shortly to confirm the appointment. If the requested time doesn't work for them, they may suggest an alternative.\n\nYour message:\n{$message}"
        : "Hi {$name},\n\nYour inquiry about \"{$listing['title']}\" has been sent to the listing agent, who will respond directly to this email address.\n\nYour message:\n{$message}";
    Notify::email($email, $confirmSubject, $confirmBody, null, INFO_EMAIL, 'KINAS GROUP');

    // ============================================================
    // SEND TO SUPER AGENT (if exists and is different from listing agent)
    // ============================================================
    if ($superAgent && ($superAgent['id'] != ($listingAgent['id'] ?? 0))) {
        $superSubject = $isViewing 
            ? "🔔 Super Agent: New Viewing Request for {$listing['title']}"
            : "🔔 Super Agent: New Inquiry for {$listing['title']}";

        send_email(
            $superAgent['email'],
            $superSubject,
            $emailBody,
            'no-reply@kinas-group.com'
        );

        // SMS to super agent
        if (!empty($superAgent['phone']) && !empty($superAgent['phone_verified_at'])) {
            $smsMsg = $isViewing
                ? "Super Agent: New viewing request for {$listing['title']} from {$name} on {$preferredDate} at {$preferredTime}."
                : "Super Agent: New inquiry on {$listing['title']} from {$name}.";
            Notify::sms($superAgent['phone'], $smsMsg, 'NEW_INQUIRY');
        }
    }

    // ============================================================
    // SAVE TO DATABASE (for both agents)
    // ============================================================
    // Save to messages table
    $stmt = $db->prepare("
        INSERT INTO messages (
            sender_id, 
            receiver_id, 
            listing_id, 
            listing_type, 
            subject, 
            body, 
            is_viewing_request,
            preferred_date,
            preferred_time,
            is_read,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    
    // Try to get sender user ID (if logged in)
    $senderId = SessionManager::getUserId() ?: null;
    
    $stmt->execute([
        $senderId,
        $listing['agent_id'],
        $listingId,
        $listingType,
        $subject,
        $plainMessage,
        $isViewing ? 1 : 0,
        $preferredDate ?: null,
        $preferredTime ?: null
    ]);

    // Also save a copy for super agent if different
    if ($superAgent && ($superAgent['id'] != ($listingAgent['id'] ?? 0))) {
        $stmt = $db->prepare("
            INSERT INTO messages (
                sender_id, 
                receiver_id, 
                listing_id, 
                listing_type, 
                subject, 
                body, 
                is_viewing_request,
                preferred_date,
                preferred_time,
                is_read,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $senderId,
            $superAgent['id'],
            $listingId,
            $listingType,
            $subject . " (Super Agent)",
            $plainMessage,
            $isViewing ? 1 : 0,
            $preferredDate ?: null,
            $preferredTime ?: null
        ]);
    }

    // Log the inquiry
    Security::logActivity(
        SessionManager::getUserId(),
        $isViewing ? 'viewing_requested' : 'inquiry_sent',
        $isViewing 
            ? "Viewing request for {$listingType} #{$listingId} from {$email} on {$preferredDate}"
            : "Inquiry for {$listingType} #{$listingId} from {$email}"
    );

    echo json_encode(['success' => true, 'message' => $isViewing ? 'Viewing request sent successfully!' : 'Inquiry sent successfully']);

} catch (Exception $e) {
    error_log('Inquiry error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send inquiry: ' . $e->getMessage()]);
}

/**
 * Helper function to send email (fallback if EmailService not available)
 */
function send_email($to, $subject, $message, $from = null) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . ($from ?? 'noreply@kinas-group.com') . "\r\n";
    $headers .= 'Reply-To: ' . ($from ?? 'noreply@kinas-group.com') . "\r\n";
    return mail($to, $subject, $message, $headers);
}
?>
