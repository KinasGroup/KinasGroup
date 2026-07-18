<?php
/**
 * Admin: Create a newsletter campaign
 *
 * Saves the composed subject/body and snapshots the current count of
 * active subscribers. Does NOT send anything itself — the browser
 * calls newsletter-send-batch.php repeatedly afterward to actually
 * send, so one big list never risks a PHP execution time limit.
 *
 * Accepts JSON POST { subject, body_html, action: 'draft'|'send' }.
 * Returns { success, campaign_id, total_recipients }.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

SessionManager::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!Security::verifyCSRFToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Please refresh the page and try again.']);
    exit;
}

$subject  = trim($data['subject'] ?? '');
$bodyHtml = trim($data['body_html'] ?? '');
$action   = $data['action'] ?? 'draft';

if ($subject === '' || $bodyHtml === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter both a subject and a message.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $countStmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'");
    $totalRecipients = (int)$countStmt->fetchColumn();

    $status = ($action === 'send') ? 'sending' : 'draft';

    $stmt = $db->prepare(
        "INSERT INTO newsletter_campaigns (subject, body_html, status, total_recipients, created_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$subject, $bodyHtml, $status, $totalRecipients, SessionManager::getUserId()]);
    $campaignId = (int)$db->lastInsertId();

    Security::logActivity(
        SessionManager::getUserId(),
        'newsletter_campaign_created',
        "Created newsletter campaign #{$campaignId}: \"{$subject}\" ({$totalRecipients} recipients, {$status})"
    );

    echo json_encode([
        'success'          => true,
        'campaign_id'      => $campaignId,
        'total_recipients' => $totalRecipients,
    ]);

} catch (Throwable $e) {
    error_log('newsletter-create.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save the campaign. Please try again.']);
}
