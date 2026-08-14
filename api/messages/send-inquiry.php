<?php
/**
 * KINAS GROUP — Listing inquiry / viewing request
 *
 * Option A rules:
 * - Customers can send inquiries and get chat rows.
 * - Agents can also send inquiries to another agent's listing and get a listing-bound chat row.
 * - Agent-to-agent chat is allowed only because it is tied to the listing inquiry.
 * - Guests receive email confirmation only, no chat row.
 * - Self-contact is rejected.
 * - Email/SMS failures are best-effort and never abort the request.
 */

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

Security::rateLimitDB('inquiry_' . Security::getClientIP(), 5, 600);

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

$data = (str_contains($contentType, 'application/json'))
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

// Honeypot field
if (!empty($data['website'])) {
    echo json_encode(['success' => true, 'message' => 'Inquiry sent']);
    exit;
}

$listingId     = (int)($data['listing_id'] ?? 0);
$listingType   = (string)($data['listing_type'] ?? 'car');
$name          = Security::sanitizeInput($data['name'] ?? '');
$email         = trim((string)($data['email'] ?? ''));
$phone         = Security::sanitizeInput($data['phone'] ?? '');
$message       = Security::sanitizeInput($data['message'] ?? '');
$inquiryType   = (string)($data['inquiry_type'] ?? 'general');
$preferredDate = (string)($data['preferred_date'] ?? '');
$preferredTime = (string)($data['preferred_time'] ?? '');

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

if ($inquiryType === 'viewing' && ($preferredDate === '' || $preferredTime === '')) {
    http_response_code(422);
    echo json_encode(['error' => 'Please select a date and time for viewing']);
    exit;
}

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'marketplace' => 'marketplace_listings',
    'solar'       => 'solar_listings',
];

if (!array_key_exists($listingType, $tableMap)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid listing type']);
    exit;
}

$table = $tableMap[$listingType];

function inquiry_notify_safe(callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        error_log('send-inquiry notify error: ' . $e->getMessage());
    }
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT agent_id, title, status FROM $table WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing || in_array((string)($listing['status'] ?? ''), ['removed', 'inactive'], true)) {
        http_response_code(404);
        echo json_encode(['error' => 'This listing is no longer available for inquiries.']);
        exit;
    }

    $senderId   = SessionManager::getUserId() ?: null;
    $senderRole = (string)($_SESSION['user_role'] ?? '');

    if ($senderId !== null && (int)$listing['agent_id'] === (int)$senderId) {
        http_response_code(403);
        echo json_encode(['error' => 'This is your own listing — inquiries from yourself are not allowed.']);
        exit;
    }

    $hasInquiryMeta = false;

    try {
        $cm = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'messages'
              AND column_name = 'inquiry_meta'
        ");
        $cm->execute();
        $hasInquiryMeta = ((int)$cm->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $hasInquiryMeta = false;
    }

    $stmt = $db->prepare("
        SELECT id, email, name, phone, phone_verified_at
        FROM users
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$listing['agent_id']]);
    $listingAgent = $stmt->fetch(PDO::FETCH_ASSOC);

    $superAgent = null;

    try {
        $stmt = $db->prepare("
            SELECT u.id, u.email, u.name, u.phone, u.phone_verified_at
            FROM users u
            JOIN agent_profiles ap ON ap.user_id = u.id
            WHERE ap.is_super_agent = 1 AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        $superAgent = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $superAgent = null;
    }

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

    $inquiryMeta = json_encode([
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'subject'        => $subject,
        'inquiry_type'   => $inquiryType,
        'preferred_date' => $preferredDate ?: null,
        'preferred_time' => $preferredTime ?: null,
        'listing_title'  => (string)$listing['title'],
        'sent_at'        => date('c'),
    ], JSON_UNESCAPED_UNICODE);

    if ($listingAgent) {
        inquiry_notify_safe(function () use ($listingAgent, $subject, $emailBody) {
            Notify::email(
                $listingAgent['email'],
                $subject,
                strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n"], $emailBody)),
                null,
                SUPPORT_EMAIL,
                'KINAS GROUP Notifications'
            );
        });

        if ($isViewing) {
            inquiry_notify_safe(function () use ($listingAgent, $subject, $emailBody) {
                send_email($listingAgent['email'], $subject . " - Viewing Request", $emailBody, 'no-reply@kinas-group.com');
            });
        }

        if (!empty($listingAgent['phone']) && !empty($listingAgent['phone_verified_at'])) {
            $smsMsg = $isViewing
                ? "New viewing request for {$listing['title']} from {$name} on {$preferredDate} at {$preferredTime}. Check your dashboard."
                : "New inquiry on {$listing['title']} from {$name}. Open your dashboard to reply.";

            inquiry_notify_safe(function () use ($listingAgent, $smsMsg) {
                Notify::sms($listingAgent['phone'], $smsMsg, 'NEW_INQUIRY');
            });
        }
    }

    $confirmSubject = $isViewing
        ? "Your viewing request for {$listing['title']} has been received"
        : "Your inquiry about {$listing['title']} has been received";

    $confirmBody = $isViewing
        ? "Hi {$name},\n\nYour request to view \"{$listing['title']}\" on {$preferredDate} at {$preferredTime} has been sent to the listing agent.\n\nThey'll be in touch shortly to confirm the appointment.\n\nYour message:\n{$message}"
        : "Hi {$name},\n\nYour inquiry about \"{$listing['title']}\" has been sent to the listing agent, who will respond directly to this email address.\n\nYour message:\n{$message}";

    inquiry_notify_safe(function () use ($email, $confirmSubject, $confirmBody) {
        Notify::email($email, $confirmSubject, $confirmBody, null, INFO_EMAIL, 'KINAS GROUP');
    });

    if ($superAgent && (int)$superAgent['id'] !== (int)($listingAgent['id'] ?? 0)) {
        $superSubject = $isViewing
            ? "🔔 Super Agent: New Viewing Request for {$listing['title']}"
            : "🔔 Super Agent: New Inquiry for {$listing['title']}";

        inquiry_notify_safe(function () use ($superAgent, $superSubject, $emailBody) {
            send_email($superAgent['email'], $superSubject, $emailBody, 'no-reply@kinas-group.com');
        });

        if (!empty($superAgent['phone']) && !empty($superAgent['phone_verified_at'])) {
            $smsMsg = $isViewing
                ? "Super Agent: New viewing request for {$listing['title']} from {$name} on {$preferredDate} at {$preferredTime}."
                : "Super Agent: New inquiry on {$listing['title']} from {$name}.";

            inquiry_notify_safe(function () use ($superAgent, $smsMsg) {
                Notify::sms($superAgent['phone'], $smsMsg, 'NEW_INQUIRY');
            });
        }
    }

    // ============================================================
    // CHAT ROWS
    // Customers always get chat rows.
    // Agents also get listing-bound chat rows when inquiring on another agent.
    // ============================================================

    $chatWritten = false;

    if ($senderId !== null && in_array($senderRole, ['user', 'agent'], true) && $listingAgent) {
        $baseCols = "sender_id, receiver_id, listing_id, listing_type, subject, body, is_viewing_request, preferred_date, preferred_time, is_read, created_at";
        $basePh   = "?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW()";

        $baseVals = function (int $receiver, string $subj) use ($listingId, $listingType, $message, $isViewing, $preferredDate, $preferredTime, $senderId) {
            return [
                $senderId,
                $receiver,
                $listingId,
                $listingType,
                $subj,
                $message,
                $isViewing ? 1 : 0,
                $preferredDate ?: null,
                $preferredTime ?: null,
            ];
        };

        if ($hasInquiryMeta) {
            $stmt = $db->prepare("INSERT INTO messages ($baseCols, inquiry_meta) VALUES ($basePh, ?)");
            $stmt->execute(array_merge($baseVals($listing['agent_id'], $subject), [$inquiryMeta]));
        } else {
            $stmt = $db->prepare("INSERT INTO messages ($baseCols) VALUES ($basePh)");
            $stmt->execute($baseVals($listing['agent_id'], $subject));
        }

        $chatWritten = true;

        // Keep super-agent chat copy only for customer inquiries.
        if ($senderRole === 'user' && $superAgent && (int)$superAgent['id'] !== (int)($listingAgent['id'] ?? 0)) {
            if ($hasInquiryMeta) {
                $stmt = $db->prepare("INSERT INTO messages ($baseCols, inquiry_meta) VALUES ($basePh, ?)");
                $stmt->execute(array_merge($baseVals($superAgent['id'], $subject . " (Super Agent)"), [$inquiryMeta]));
            } else {
                $stmt = $db->prepare("INSERT INTO messages ($baseCols) VALUES ($basePh)");
                $stmt->execute($baseVals($superAgent['id'], $subject . " (Super Agent)"));
            }
        }
    }

    Security::logActivity(
        $senderId,
        $isViewing ? 'viewing_requested' : 'inquiry_sent',
        $isViewing
            ? "Viewing request for {$listingType} #{$listingId} from {$email} on {$preferredDate}"
            : "Inquiry for {$listingType} #{$listingId} from {$email}"
    );

    $note = '';

    if ($senderId !== null && !in_array($senderRole, ['user', 'agent'], true)) {
        $note = ' (delivered by email — chat threads are available for customer and listing inquiries only)';
    } elseif (!$chatWritten && $senderId === null) {
        $note = ' (create a free account to chat directly with agents)';
    } elseif (!$chatWritten && $senderId !== null) {
        $note = ' (delivered by email only)';
    }

    echo json_encode([
        'success' => true,
        'message' => ($isViewing ? 'Viewing request sent successfully!' : 'Inquiry sent successfully') . $note,
    ]);
} catch (Exception $e) {
    error_log('Inquiry error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send inquiry. Please try again.']);
}

/**
 * Helper function to send email fallback.
 */
function send_email($to, $subject, $message, $from = null)
{
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . ($from ?? 'noreply@kinas-group.com') . "\r\n";
    $headers .= 'Reply-To: ' . ($from ?? 'noreply@kinas-group.com') . "\r\n";

    return mail($to, $subject, $message, $headers);
}
