<?php
/**
 * KINAS GROUP — Mark conversation as read (rebuilt messenger)
 *
 * POST /api/messages/mark-read.php
 *   csrf_token, other_user, listing (optional listing_id)
 *
 * Marks every incoming message from the other user (within the given
 * listing thread, when provided) as read and stamps read_at — this is
 * what flips the sender's ✓ to ✓✓.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in.']);
    exit;
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if (!Security::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$userId    = (int)SessionManager::getUserId();
$otherId   = (int)($_POST['other_user'] ?? 0);
$listingId = (int)($_POST['listing'] ?? 0);

if ($otherId <= 0 || $otherId === $userId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid conversation.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    if ($listingId > 0) {
        $stmt = $db->prepare("
            UPDATE messages
            SET is_read = 1, read_at = NOW()
            WHERE sender_id = ? AND receiver_id = ? AND listing_id = ? AND is_read = 0
        ");
        $stmt->execute([$otherId, $userId, $listingId]);
    } else {
        $stmt = $db->prepare("
            UPDATE messages
            SET is_read = 1, read_at = NOW()
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$otherId, $userId]);
    }

    echo json_encode([
        'success' => true,
        'marked'  => (int)$stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    error_log('mark-read.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not mark messages as read.']);
}
