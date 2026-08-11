<?php
/**
* KINAS GROUP — Start a Didit KYC (personal identity) verification.
*
* POST /api/agent/didit-kyc-start.php
*   Body: { csrf_token }
*   Returns: { success, sessionId, url }
*
* AMENDED FOR KYC NAME-MATCH ENFORCEMENT:
* - Blocks KYC start if the account has no usable registered name.
* - Captures the registered account name as expected_name.
* - Sends expected_name in Didit metadata for audit/correlation.
* - Resets name-match/rejection state when a fresh KYC attempt starts.
* - Keeps existing idempotent session behaviour.
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

/**
 * Check whether a table exists.
 */
function didit_kyc_start_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");

        $stmt->execute([$table]);

        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Check whether a column exists.
 */
function didit_kyc_start_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");

        $stmt->execute([$table, $column]);

        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

try {
    $db    = Database::getInstance()->getConnection();
    $didit = new DiditService();

    if (!$didit->isKycEnabled()) {
        http_response_code(503);
        echo json_encode(['error' => 'Identity verification is temporarily unavailable. Please contact support@kinas-group.com if this persists.']);
        exit;
    }

    if (!didit_kyc_start_table_exists($db, 'didit_verifications')) {
        http_response_code(500);
        echo json_encode(['error' => 'The KYC verification system is not installed correctly.']);
        exit;
    }

    // ------------------------------------------------------------
    // Current verification state
    // ------------------------------------------------------------
    $stmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);

    $profileStatus = (string)($stmt->fetchColumn() ?: '');

    // Do not allow KYC to restart if identity has already been confirmed.
    // - approved   = fully approved individual
    // - kyc_passed = identity confirmed for a business account, awaiting KYB
    if (in_array($profileStatus, ['approved', 'kyc_passed'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'You are already verified.']);
        exit;
    }

    // ------------------------------------------------------------
    // Registered account name
    //
    // This is the name the customer/agent used when creating the account.
    // KYC must be compared against this name. If it is missing, KYC
    // must not be allowed to start.
    // ------------------------------------------------------------
    $userRow = $db->prepare("SELECT email, name, phone FROM users WHERE id = ?");
    $userRow->execute([$userId]);
    $u = $userRow->fetch(PDO::FETCH_ASSOC) ?: [];

    $registeredName = trim((string)($u['name'] ?? ''));
    $registeredName = trim(preg_replace('/\s+/', ' ', $registeredName));

    if (function_exists('mb_strlen')) {
        $registeredNameLength = mb_strlen($registeredName);
    } else {
        $registeredNameLength = strlen($registeredName);
    }

    if ($registeredName === '' || $registeredNameLength < 3) {
        http_response_code(422);
        echo json_encode([
            'error' => 'Your account does not have a valid registered name. Please update your account name before starting identity verification.',
        ]);
        exit;
    }

    // Safety limit for DB column and metadata.
    if (function_exists('mb_substr')) {
        $registeredName = mb_substr($registeredName, 0, 255);
    } else {
        $registeredName = substr($registeredName, 0, 255);
    }

    // ------------------------------------------------------------
    // Create Didit session
    // ------------------------------------------------------------
    $contactDetails = array_filter([
        'email' => $u['email'] ?? '',
        'phone' => $u['phone'] ?? '',
    ]);

    $metadata = [
        'user_id'          => $userId,
        'platform'         => 'kinas-group',
        'expected_name'    => $registeredName,
        'kyc_name_source'  => 'users.name',
    ];

    $result = $didit->createSession(
        $didit->getKycWorkflowId(),
        'kyc:' . $userId,
        url('/agent/verification.php?didit_kyc=1'),
        $metadata,
        $contactDetails
    );

    if (!$result['success']) {
        http_response_code(502);
        echo json_encode(['error' => $result['error'] ?? 'Could not start verification. Please try again.']);
        exit;
    }

    if (empty($result['session_id'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Could not create a Didit verification session.']);
        exit;
    }

    if (empty($result['url'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Could not get the Didit verification URL.']);
        exit;
    }

    $sessionId = $result['session_id'];

    // ------------------------------------------------------------
    // Store Didit session and expected registered name
    // ------------------------------------------------------------
    $hasExpectedNameColumn = didit_kyc_start_column_exists($db, 'didit_verifications', 'expected_name');

    $fields = [
        'user_id',
        'session_type',
        'session_id',
        'session_number',
        'workflow_id',
        'vendor_data',
        'status',
        'didit_status',
    ];

    $values = [
        $userId,
        'kyc',
        $sessionId,
        $result['session_number'] ?? null,
        $result['workflow_id'] ?? null,
        'kyc:' . $userId,
        'created',
        $result['status'] ?? 'Not Started',
    ];

    if ($hasExpectedNameColumn) {
        $fields[] = 'expected_name';
        $values[] = $registeredName;
    }

    $placeholders = implode(', ', array_fill(0, count($fields), '?'));

    $duplicateUpdates = [
        'didit_status = VALUES(didit_status)',
        'updated_at = NOW()',
    ];

    if ($hasExpectedNameColumn) {
        $duplicateUpdates[] = 'expected_name = VALUES(expected_name)';
    }

    $sql = "
        INSERT INTO didit_verifications
        (" . implode(', ', $fields) . ")
        VALUES
        ({$placeholders})
        ON DUPLICATE KEY UPDATE
        " . implode(', ', $duplicateUpdates) . "
    ";

    $db->prepare($sql)->execute($values);

    // ------------------------------------------------------------
    // Update agent profile state
    // ------------------------------------------------------------
    $profileSets = [
        "verification_status = 'in_progress'",
    ];

    $profileParams = [];

    if (didit_kyc_start_column_exists($db, 'agent_profiles', 'kyc_provider')) {
        $profileSets[] = "kyc_provider = 'didit'";
    }

    if (didit_kyc_start_column_exists($db, 'agent_profiles', 'kyc_verification_id')) {
        $profileSets[] = 'kyc_verification_id = ?';
        $profileParams[] = $sessionId;
    }

    if (didit_kyc_start_column_exists($db, 'agent_profiles', 'kyc_submitted_at')) {
        $profileSets[] = 'kyc_submitted_at = COALESCE(kyc_submitted_at, NOW())';
    }

    $profileParams[] = $userId;

    $db->prepare("
        UPDATE agent_profiles
        SET " . implode(', ', $profileSets) . "
        WHERE user_id = ?
    ")->execute($profileParams);

    // Reset name-match enforcement fields for the fresh attempt.
    if (didit_kyc_start_column_exists($db, 'agent_profiles', 'kyc_name_match')) {
        $db->prepare("
            UPDATE agent_profiles
            SET kyc_name_match = 'not_checked'
            WHERE user_id = ?
        ")->execute([$userId]);
    }

    if (didit_kyc_start_column_exists($db, 'agent_profiles', 'kyc_rejection_reason')) {
        $db->prepare("
            UPDATE agent_profiles
            SET kyc_rejection_reason = NULL
            WHERE user_id = ?
        ")->execute([$userId]);
    }

    Security::logActivity(
        $userId,
        'kyc_started',
        "Didit KYC session $sessionId started for registered name \"$registeredName\""
    );

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
