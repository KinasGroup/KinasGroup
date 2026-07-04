<?php
/**
 * KINAS GROUP — Start a Didit KYC (personal identity) verification.
 *
 * POST /api/agent/didit-kyc-start.php
 *   Body: { csrf_token }
 *   Returns: { success, sessionId, url }
 *
 * Idempotent: re-uses an existing session that's still 'created' or
 * 'in_progress' rather than spawning a duplicate. Blocked once already
 * approved.
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/helpers.php';
require_once '../../includes/didit.php';

SessionManager::requireAgent();

$userId = (int)$_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$token  = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

try {
    $db    = Database::getInstance()->getConnection();
    $didit = new DiditService();

    if (!$didit->isKycEnabled()) {
        http_response_code(503);
        echo json_encode(['error' => 'Identity verification is temporarily unavailable. Please contact support@kinasgroup.com if this persists.']);
        exit;
    }

    $approved = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $approved->execute([$userId]);
    if ($approved->fetchColumn() === 'approved') {
        http_response_code(409);
        echo json_encode(['error' => 'You are already verified.']);
        exit;
    }

    // Didit's own idempotency rule (same vendor_data + an unfinished
    // session already existing) returns that same session instead of
    // creating a duplicate, so we don't need to track "in-flight"
    // sessions ourselves before calling createSession — we just always
    // ask, using a stable vendor_data ('kyc:{userId}').
    $userRow = $db->prepare("SELECT email, name, phone FROM users WHERE id = ?");
    $userRow->execute([$userId]);
    $u = $userRow->fetch(PDO::FETCH_ASSOC) ?: [];

    $result = $didit->createSession(
        $didit->getKycWorkflowId(),
        'kyc:' . $userId,
        url('/agent/verification.php?didit_kyc=1'),
        ['user_id' => $userId, 'platform' => 'kinas-group'],
        array_filter(['email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? ''])
    );

    if (!$result['success']) {
        http_response_code(502);
        echo json_encode(['error' => $result['error'] ?? 'Could not start verification. Please try again.']);
        exit;
    }

    $sessionId = $result['session_id'];

    $db->prepare("
        INSERT INTO didit_verifications
            (user_id, session_type, session_id, session_number, workflow_id, vendor_data, status, didit_status)
        VALUES (?, 'kyc', ?, ?, ?, ?, 'created', ?)
        ON DUPLICATE KEY UPDATE
            didit_status = VALUES(didit_status),
            updated_at   = NOW()
    ")->execute([
        $userId, $sessionId, $result['session_number'] ?? null, $result['workflow_id'] ?? null,
        'kyc:' . $userId, $result['status'] ?? 'Not Started',
    ]);

    $db->prepare("
        UPDATE agent_profiles
        SET kyc_provider        = 'didit',
            kyc_verification_id = ?,
            verification_status = 'in_progress'
        WHERE user_id = ?
    ")->execute([$sessionId, $userId]);

    Security::logActivity($userId, 'kyc_started', "Didit KYC session $sessionId started");

    echo json_encode([
        'success'   => true,
        'sessionId' => $sessionId,
        'url'       => $result['url'],
    ]);

} catch (Exception $e) {
    error_log('didit-kyc-start error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start verification. Please try again.']);
}
