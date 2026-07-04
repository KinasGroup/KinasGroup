<?php
/**
 * KINAS GROUP — Start a Didit KYB (business) verification.
 *
 * POST /api/agent/didit-kyb-start.php
 *   Body: { csrf_token }
 *   Returns: { success, sessionId, url }
 *
 * This is a separate Didit workflow from KYC (a different workflow_id
 * configured in the Didit Console for business verification —
 * registry lookup, UBOs, company AML). An agent can run KYB
 * independently of KYC; we don't require KYC to be done first, since
 * a business's registration and an individual's ID are unrelated
 * checks, but the admin approval step still looks at both.
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

    if (!$didit->isKybEnabled()) {
        http_response_code(503);
        echo json_encode(['error' => 'Business verification is temporarily unavailable. Please contact support@kinasgroup.com if this persists.']);
        exit;
    }

    $profile = $db->prepare("SELECT kyb_status FROM agent_profiles WHERE user_id = ?");
    $profile->execute([$userId]);
    if ($profile->fetchColumn() === 'approved') {
        http_response_code(409);
        echo json_encode(['error' => 'Your business is already verified.']);
        exit;
    }

    $userRow = $db->prepare("
        SELECT u.email, u.name, u.phone, ap.company_name, ap.company_legal_name, ap.cac_number
        FROM users u JOIN agent_profiles ap ON ap.user_id = u.id
        WHERE u.id = ?
    ");
    $userRow->execute([$userId]);
    $u = $userRow->fetch(PDO::FETCH_ASSOC) ?: [];

    // Didit's KYB workflow collects company details (name, registration
    // number, country) itself inside the hosted flow — we pass what we
    // already have as correlation metadata only, not as authoritative
    // pre-fill (there's no documented pre-fill parameter for company
    // registry fields, unlike contact_details for KYC).
    $result = $didit->createSession(
        $didit->getKybWorkflowId(),
        'kyb:' . $userId,
        url('/agent/verification.php?didit_kyb=1'),
        [
            'user_id'            => $userId,
            'platform'           => 'kinas-group',
            'company_name_hint'  => $u['company_legal_name'] ?? $u['company_name'] ?? '',
            'cac_number_hint'    => $u['cac_number'] ?? '',
        ],
        array_filter(['email' => $u['email'] ?? '', 'phone' => $u['phone'] ?? ''])
    );

    if (!$result['success']) {
        http_response_code(502);
        echo json_encode(['error' => $result['error'] ?? 'Could not start business verification. Please try again.']);
        exit;
    }

    $sessionId = $result['session_id'];

    $db->prepare("
        INSERT INTO didit_verifications
            (user_id, session_type, session_id, session_number, workflow_id, vendor_data, status, didit_status)
        VALUES (?, 'kyb', ?, ?, ?, ?, 'created', ?)
        ON DUPLICATE KEY UPDATE
            didit_status = VALUES(didit_status),
            updated_at   = NOW()
    ")->execute([
        $userId, $sessionId, $result['session_number'] ?? null, $result['workflow_id'] ?? null,
        'kyb:' . $userId, $result['status'] ?? 'Not Started',
    ]);

    $db->prepare("
        UPDATE agent_profiles
        SET kyb_status          = 'in_progress',
            kyb_verification_id = ?,
            kyb_submitted_at    = COALESCE(kyb_submitted_at, NOW())
        WHERE user_id = ?
    ")->execute([$sessionId, $userId]);

    Security::logActivity($userId, 'kyb_started', "Didit KYB session $sessionId started");

    echo json_encode([
        'success'   => true,
        'sessionId' => $sessionId,
        'url'       => $result['url'],
    ]);

} catch (Exception $e) {
    error_log('didit-kyb-start error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start business verification. Please try again.']);
}
