<?php
/**
 * Admin: Send the next batch of a newsletter campaign
 *
 * The browser calls this repeatedly (see admin/newsletter.php) with the
 * same campaign_id until { done: true } comes back. Each call sends to
 * up to BATCH_SIZE subscribers, using last_subscriber_id as a resume
 * cursor so re-calling never double-sends and a page refresh mid-send
 * can safely pick back up where it left off.
 *
 * Accepts JSON POST { campaign_id, csrf_token }.
 * Returns { success, done, sent_count, failed_count, total_recipients }.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../config/constants.php';

SessionManager::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

const BATCH_SIZE = 20;

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!Security::verifyCSRFToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Please refresh the page and try again.']);
    exit;
}

$campaignId = (int)($data['campaign_id'] ?? 0);
if (!$campaignId) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing campaign_id.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found.']);
        exit;
    }

    if ($campaign['status'] === 'sent') {
        echo json_encode([
            'success' => true, 'done' => true,
            'sent_count' => (int)$campaign['sent_count'],
            'failed_count' => (int)$campaign['failed_count'],
            'total_recipients' => (int)$campaign['total_recipients'],
        ]);
        exit;
    }

    // Next batch: active subscribers with id greater than the resume
    // cursor, oldest-id-first, capped at BATCH_SIZE.
    $stmt = $db->prepare(
        "SELECT id, email, unsubscribe_token FROM newsletter_subscribers
         WHERE status = 'active' AND id > ?
         ORDER BY id ASC LIMIT " . BATCH_SIZE
    );
    $stmt->execute([$campaign['last_subscriber_id']]);
    $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sentThisBatch = 0;
    $failedThisBatch = 0;
    $lastId = (int)$campaign['last_subscriber_id'];

    if (!empty($batch)) {
        $svc = new EmailService();

        foreach ($batch as $sub) {
            $lastId = (int)$sub['id'];
            try {
                $unsubscribeLink = SITE_URL . '/api/newsletter/unsubscribe.php?email='
                    . urlencode($sub['email']) . '&token=' . urlencode($sub['unsubscribe_token']);

                $html = $campaign['body_html']
                    . '<hr style="border:none;border-top:1px solid #E0E0E0;margin:32px 0 16px;">'
                    . '<p style="font-size:11px;color:#999999 !important;text-align:center;">'
                    . 'You\'re receiving this because you subscribed to the KINAS GROUP newsletter. '
                    . '<a href="' . htmlspecialchars($unsubscribeLink) . '" style="color:#C6A43F !important;">Unsubscribe</a>'
                    . '</p>';

                $ok = $svc->send($sub['email'], '', $campaign['subject'], $html, strip_tags($campaign['body_html']));
                if ($ok) {
                    $sentThisBatch++;
                } else {
                    $failedThisBatch++;
                }
            } catch (Throwable $e) {
                error_log('Newsletter send failed for ' . $sub['email'] . ': ' . $e->getMessage());
                $failedThisBatch++;
            }
        }
    }

    $newSentCount = (int)$campaign['sent_count'] + $sentThisBatch;
    $newFailedCount = (int)$campaign['failed_count'] + $failedThisBatch;
    $done = count($batch) < BATCH_SIZE; // fewer than a full batch means we reached the end

    $newStatus = $done ? 'sent' : 'sending';
    $sentAtSql = $done ? ', sent_at = NOW()' : '';

    $db->prepare(
        "UPDATE newsletter_campaigns
         SET sent_count = ?, failed_count = ?, last_subscriber_id = ?, status = ?{$sentAtSql}
         WHERE id = ?"
    )->execute([$newSentCount, $newFailedCount, $lastId, $newStatus, $campaignId]);

    if ($done) {
        Security::logActivity(
            SessionManager::getUserId(),
            'newsletter_campaign_sent',
            "Newsletter campaign #{$campaignId} finished sending: {$newSentCount} sent, {$newFailedCount} failed"
        );
    }

    echo json_encode([
        'success'          => true,
        'done'             => $done,
        'sent_count'       => $newSentCount,
        'failed_count'     => $newFailedCount,
        'total_recipients' => (int)$campaign['total_recipients'],
    ]);

} catch (Throwable $e) {
    error_log('newsletter-send-batch.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong while sending. You can safely retry — already-sent subscribers will not be emailed twice.']);
}
