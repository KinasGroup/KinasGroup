<?php
/**
 * KINAS GROUP — Notification helper
 *
 * Centralized hooks for transactional notifications (email + SMS).
 * Always wraps in try/catch so a notify failure can never break a
 * primary user action (e.g. a listing submit).
 */
require_once __DIR__ . '/termii.php';
require_once __DIR__ . '/email.php';

class Notify
{
    /**
     * Send an SMS if Termii is enabled and the event is opted in.
     */
    public static function sms(string $phone, string $message, string $eventKey = null): bool
    {
        if ($eventKey && !self::isSmsEventEnabled($eventKey)) return false;
        try {
            $svc = new TermiiService();
            if (!$svc->isEnabled() || empty($phone)) return false;
            $res = $svc->sendSms($phone, $message);
            return (bool)($res['success'] ?? false);
        } catch (Throwable $e) {
            error_log('Notify::sms failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an email if the email service is configured.
     */
    public static function email(string $to, string $subject, string $body, ?string $altBody = null): bool
    {
        try {
            $svc = new EmailService();
            // EmailService::send() expects (to, name, subject, htmlBody, plainText).
            // $body here is plain text assembled by callers, so it needs to be
            // turned into a safe HTML fragment before being used as htmlBody.
            $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES));
            $plainText = $altBody ?? $body;
            return $svc->send($to, '', $subject, $htmlBody, $plainText);
        } catch (Throwable $e) {
            error_log('Notify::email failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * High-level: notify a user on a specific event. Reads the user's
     * phone + email from the DB.
     */
    public static function userEvent(int $userId, string $eventKey, string $message, ?string $emailSubject = null, ?string $emailBody = null): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT name, email, phone, phone_verified_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) return;

            // SMS (only if phone verified)
            if (!empty($u['phone']) && !empty($u['phone_verified_at'])) {
                self::sms($u['phone'], "Hi {$u['name']}, {$message}", $eventKey);
            }

            // Email
            if (!empty($u['email']) && $emailSubject && $emailBody) {
                self::email($u['email'], $emailSubject, $emailBody);
            }
        } catch (Throwable $e) {
            error_log('Notify::userEvent failed: ' . $e->getMessage());
        }
    }

    private static function isSmsEventEnabled(string $eventKey): bool
    {
        $envKey = 'SMS_NOTIFY_' . strtoupper($eventKey);
        $val = getenv($envKey);
        if ($val === false || $val === '') $val = $_ENV[$envKey] ?? '';
        return strtolower((string)$val) !== 'false';
    }
}
