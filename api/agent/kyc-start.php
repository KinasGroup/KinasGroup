<?php
/**
 * KINAS GROUP — Kick off a MetaMap KYC verification for the
 * currently logged-in agent.
 *
 * POST /api/agent/kyc-start.php
 *   Body: { csrf_token, country? }
 *   Returns: { success, verificationId, hostedUrl }
 *
 * Idempotent: if the agent already has a verification in
 * 'created' or 'in_progress' state, we re-use it rather than
 * spawning a new one. 'approved' verifications are blocked.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/metamap.php';

SessionManager::requireAgent();

$userId = (int)$_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$token  = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$country = strtoupper($body['country'] ?? $_POST['country'] ?? 'NG');

try {
    $db     = Database::getInstance()->getConnection();
    $metamap = new MetaMapService();

    if (!$metamap->isEnabled()) {
        http_response_code(503);
        echo json_encode([
            'error' => 'KYC is temporarily unavailable. Please contact support@kinasgroup.com if this persists.'
        ]);
        exit;
    }

    // Don't create a new one if the user is already approved
    $approved = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $approved->execute([$userId]);
    if ($approved->fetchColumn() === 'approved') {
        http_response_code(409);
        echo json_encode(['error' => 'You are already verified.']);
        exit;
    }

    // Re-use an in-flight verification if one exists
    $existing = $db->prepare("
        SELECT verification_id, status
        FROM metamap_verifications
        WHERE user_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $existing->execute([$userId]);
    $existing = $existing->fetch(PDO::FETCH_ASSOC);

    if ($existing && in_array($existing['status'], ['created', 'in_progress'], true)) {
        $verificationId = $existing['verification_id'];
    } else {
        $userRow = $db->prepare("SELECT email, name FROM users WHERE id = ?");
        $userRow->execute([$userId]);
        $u = $userRow->fetch(PDO::FETCH_ASSOC) ?: [];

        $division = $db->prepare("SELECT division FROM agent_profiles WHERE user_id = ?");
        $division->execute([$userId]);
        $division = $division->fetchColumn() ?: '';

        $result = $metamap->createVerification(
            (string)$userId,
            [
                'email'      => $u['email'] ?? '',
                'name'       => $u['name']  ?? '',
                'division'   => $division,
                'platform'   => 'kinas-group',
            ],
            $country
        );

        $verificationId = $result['id'];

        $ins = $db->prepare("
            INSERT INTO metamap_verifications
                (user_id, verification_id, status, mati_status, country, metadata, created_at)
            VALUES (?, ?, 'created', ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                mati_status = VALUES(mati_status),
                updated_at  = NOW()
        ");
        $ins->execute([
            $userId,
            $verificationId,
            $result['status'] ?? 'created',
            $country,
            json_encode(['division' => $division, 'platform' => 'kinas-group'])
        ]);

        $upd = $db->prepare("
            UPDATE agent_profiles
            SET kyc_provider        = 'metamap',
                kyc_verification_id = ?,
                verification_status = 'in_progress'
            WHERE user_id = ?
        ");
        try {
            $upd->execute([$verificationId, $userId]);
        } catch (PDOException $e) {
            // Migration not yet applied — fall back to a status-only update
            $db->prepare("UPDATE agent_profiles SET verification_status = 'in_progress' WHERE user_id = ?")
               ->execute([$userId]);
        }
    }

    Security::logActivity($userId, 'kyc_started', "MetaMap verification $verificationId started");

    echo json_encode([
        'success'        => true,
        'verificationId' => $verificationId,
        'hostedUrl'      => $metamap->buildHostedUrl($verificationId),
    ]);

} catch (Exception $e) {
    error_log('kyc-start error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start verification. Please try again.']);
}
