<?php
/**
 * KINAS GROUP — Newsletter unsubscribe endpoint
 * Plain link target (from the welcome email), so it renders a simple
 * HTML confirmation page rather than JSON.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

$email = trim(strtolower($_GET['email'] ?? ''));
$token = trim($_GET['token'] ?? '');

$message = 'That unsubscribe link is invalid or has expired.';
$ok = false;

if (filter_var($email, FILTER_VALIDATE_EMAIL) && $token !== '') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? AND unsubscribe_token = ? AND status = 'active'");
        $stmt->execute([$email, $token]);
        $row = $stmt->fetch();

        if ($row) {
            $db->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?")
               ->execute([$row['id']]);
            $ok = true;
            $message = "You've been unsubscribed from the KINAS GROUP newsletter.";
        } else {
            // Either already unsubscribed or token mismatch — check silently.
            $stmt = $db->prepare("SELECT id FROM newsletter_subscribers WHERE email = ? AND unsubscribe_token = ?");
            $stmt->execute([$email, $token]);
            if ($stmt->fetch()) {
                $ok = true;
                $message = "You're already unsubscribed.";
            }
        }
    } catch (Throwable $e) {
        error_log('Newsletter unsubscribe error: ' . $e->getMessage());
        $message = 'Something went wrong. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Newsletter — KINAS GROUP</title>
</head>
<body style="font-family: Arial, sans-serif; background:#F2F2F2; margin:0; padding:0;">
    <div style="max-width:480px; margin:60px auto; background:#FFFFFF; border-radius:6px; padding:40px 32px; text-align:center; box-shadow:0 2px 12px rgba(0,0,0,0.06);">
        <h2 style="color:#0A0A0A; margin-top:0;">KINAS GROUP</h2>
        <p style="color:<?= $ok ? '#0A0A0A' : '#B71C1C' ?>; font-size:15px;"><?= htmlspecialchars($message) ?></p>
        <a href="<?= htmlspecialchars(SITE_URL) ?>/" style="display:inline-block; margin-top:16px; color:#C6A43F; text-decoration:none; font-weight:600;">&larr; Back to kinas-group.com</a>
    </div>
</body>
</html>
