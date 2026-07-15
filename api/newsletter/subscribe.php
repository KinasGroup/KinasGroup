<?php
/**
 * KINAS GROUP — Newsletter subscribe endpoint
 *
 * Backs the "Subscribe" forms in the site footer and on the blog.
 * Accepts { email, source? } as JSON or form-encoded POST and
 * upserts into newsletter_subscribers.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ip = Security::getClientIP();
Security::rateLimitDB('newsletter_subscribe_' . $ip, 10, 3600);

// Accept both JSON body and classic form submissions.
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$email  = trim(strtolower($data['email'] ?? ''));
$source = trim($data['source'] ?? 'unknown');
$source = $source !== '' ? substr($source, 0, 50) : 'unknown';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

$deliverableError = Security::checkEmailDeliverable($email);
if ($deliverableError !== null) {
    http_response_code(422);
    echo json_encode(['error' => $deliverableError]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $isNew = false;

    if ($existing) {
        if ($existing['status'] === 'active') {
            // Already subscribed — treat as success, no duplicate email.
            echo json_encode(['success' => true, 'message' => "You're already subscribed — thank you!"]);
            exit;
        }
        // Re-activate a previous unsubscribe.
        $db->prepare(
            "UPDATE newsletter_subscribers
                SET status = 'active', source = ?, ip_address = ?, subscribed_at = NOW(), unsubscribed_at = NULL
              WHERE id = ?"
        )->execute([$source, $ip, $existing['id']]);
        $isNew = true;
    } else {
        $token = bin2hex(random_bytes(24));
        $db->prepare(
            "INSERT INTO newsletter_subscribers (email, status, source, ip_address, unsubscribe_token)
             VALUES (?, 'active', ?, ?, ?)"
        )->execute([$email, $source, $ip, $token]);
        $isNew = true;
    }

    if ($isNew) {
        try {
            $stmt = $db->prepare("SELECT unsubscribe_token FROM newsletter_subscribers WHERE email = ?");
            $stmt->execute([$email]);
            $token = $stmt->fetchColumn();
            $unsubscribeLink = SITE_URL . '/api/newsletter/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode((string)$token);

            $html = '<div style="font-family:Arial,sans-serif;">'
                  . '<h2 style="color:#0A0A0A !important;">You\'re subscribed!</h2>'
                  . '<p style="color:#0A0A0A !important;">Thanks for subscribing to the KINAS GROUP newsletter. '
                  . 'You\'ll be the first to hear about new listings, launches, and offers across all our divisions.</p>'
                  . '<p style="font-size:12px; color:#666666 !important; margin-top:24px;">Didn\'t sign up for this? '
                  . '<a href="' . htmlspecialchars($unsubscribeLink) . '" style="color:#C6A43F !important;">Unsubscribe here</a>.</p>'
                  . '</div>';
            $plain = "You're subscribed to the KINAS GROUP newsletter!\n\nDidn't sign up? Unsubscribe: {$unsubscribeLink}";

            $svc = new EmailService();
            $svc->send($email, '', 'Welcome to the KINAS GROUP newsletter', $html, $plain);
        } catch (Throwable $e) {
            error_log('Newsletter welcome email failed: ' . $e->getMessage());
            // Don't fail the subscription just because the confirmation email failed.
        }
    }

    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing! Please check your inbox.']);

} catch (Throwable $e) {
    error_log('Newsletter subscribe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong. Please try again in a moment.']);
}
