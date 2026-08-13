<?php
/**
 * KINAS GROUP — Didit KYC status (for the agent verification page)
 *
 * GET /api/agent/didit-kyc-status.php
 *
 * REVAMP: runs the on-demand Didit sync FIRST (self-heal) so a missed
 * webhook can never report a verified agent as unverified.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/didit-sync.php';
SessionManager::requireAgent();
$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();

// SELF-HEAL before reading anything.
try { didit_sync_kyc($db, $userId); } catch (Throwable $e) { error_log('didit-kyc-status sync error: ' . $e->getMessage()); }

// ============================================================
// SCHEMA HELPERS
// ============================================================
function didit_kyc_status_table_exists(PDO $db, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$table]);
        $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}
function didit_kyc_status_column_exists(PDO $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}
// ============================================================
// AGENT PROFILE / VERIFICATION STATE
// ============================================================
$status      = 'pending';
$submittedAt = null;
$decisionAt  = null;
try {
    $apSelects = ['ap.verification_status'];
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_submitted_at')) {
        $apSelects[] = 'ap.kyc_submitted_at';
    } else {
        $apSelects[] = 'NULL AS kyc_submitted_at';
    }
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_decision_at')) {
        $apSelects[] = 'ap.kyc_decision_at';
    } else {
        $apSelects[] = 'NULL AS kyc_decision_at';
    }
    $stmt = $db->prepare("
        SELECT " . implode(', ', $apSelects) . "
        FROM agent_profiles ap
        WHERE ap.user_id = ?
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $status      = $profile['verification_status'] ?? 'pending';
    $submittedAt = $profile['kyc_submitted_at'] ?? null;
    $decisionAt  = $profile['kyc_decision_at'] ?? null;
} catch (Throwable $e) {
    error_log('didit-kyc-status.php agent_profiles error: ' . $e->getMessage());
}
// ============================================================
// DIDIT SESSION STATE
// ============================================================
$diditStatus       = null;
$sessionId         = null;
$startedAt         = null;
$completedAt       = null;
$diditNameMatch    = null;
$diditDocumentName = null;
$diditExpectedName = null;
if (didit_kyc_status_table_exists($db, 'didit_verifications')) {
    $dvSelects = [
        'dv.session_id',
        'dv.didit_status',
        'dv.created_at',
        'dv.completed_at',
    ];
    if (didit_kyc_status_column_exists($db, 'didit_verifications', 'name_match')) {
        $dvSelects[] = 'dv.name_match';
    }
    if (didit_kyc_status_column_exists($db, 'didit_verifications', 'document_name')) {
        $dvSelects[] = 'dv.document_name';
    }
    if (didit_kyc_status_column_exists($db, 'didit_verifications', 'expected_name')) {
        $dvSelects[] = 'dv.expected_name';
    }
    try {
        $stmt = $db->prepare("
            SELECT " . implode(', ', $dvSelects) . "
            FROM didit_verifications dv
            WHERE dv.user_id = ?
              AND dv.session_type = 'kyc'
            ORDER BY dv.id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $dv = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sessionId         = $dv['session_id'] ?? null;
        $diditStatus       = $dv['didit_status'] ?? null;
        $startedAt         = $dv['created_at'] ?? null;
        $completedAt       = $dv['completed_at'] ?? null;
        $diditNameMatch    = $dv['name_match'] ?? null;
        $diditDocumentName = $dv['document_name'] ?? null;
        $diditExpectedName = $dv['expected_name'] ?? null;
    } catch (Throwable $e) {
        error_log('didit-kyc-status.php didit_verifications error: ' . $e->getMessage());
    }
}
// ============================================================
// NAME-MATCH FIELDS STORED ON agent_profiles
// ============================================================
$profileNameMatch     = null;
$profileMismatchFlag  = null;
$profileDocumentName  = null;
$rejectionReason      = null;
try {
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_name_match')) {
        $stmt = $db->prepare("
            SELECT kyc_name_match
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        if (in_array($value, ['matched', 'mismatched', 'unreadable'], true)) {
            $profileNameMatch = $value;
        }
    }
} catch (Throwable $e) {
    error_log('didit-kyc-status.php kyc_name_match error: ' . $e->getMessage());
}
try {
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_name_mismatch')) {
        $stmt = $db->prepare("
            SELECT kyc_name_mismatch
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $profileMismatchFlag = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('didit-kyc-status.php kyc_name_mismatch error: ' . $e->getMessage());
}
try {
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_document_name')) {
        $stmt = $db->prepare("
            SELECT kyc_document_name
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        if (is_string($value) && trim($value) !== '') {
            $profileDocumentName = trim($value);
        }
    }
} catch (Throwable $e) {
    error_log('didit-kyc-status.php kyc_document_name error: ' . $e->getMessage());
}
try {
    if (didit_kyc_status_column_exists($db, 'agent_profiles', 'kyc_rejection_reason')) {
        $stmt = $db->prepare("
            SELECT kyc_rejection_reason
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        if (is_string($value) && trim($value) !== '') {
            $rejectionReason = trim($value);
        }
    }
} catch (Throwable $e) {
    error_log('didit-kyc-status.php kyc_rejection_reason error: ' . $e->getMessage());
}
// ============================================================
// REGISTERED / EXPECTED NAME
// ============================================================
$expectedName = $diditExpectedName;
if ($expectedName === null || trim((string)$expectedName) === '') {
    try {
        $stmt = $db->prepare("
            SELECT name
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        if (is_string($value) && trim($value) !== '') {
            $expectedName = trim($value);
        }
    } catch (Throwable $e) {
        error_log('didit-kyc-status.php users.name error: ' . $e->getMessage());
    }
}
// ============================================================
// FINAL NAME-MATCH RESULT
// ============================================================
$nameMatch = $profileNameMatch ?? $diditNameMatch;
if ($nameMatch === 'not_checked') {
    $nameMatch = null;
}
if ($nameMatch === null && $profileMismatchFlag === 1) {
    $nameMatch = 'mismatched';
}
$nameMismatch = ($nameMatch === 'mismatched') || ($profileMismatchFlag === 1);
$documentName = $profileDocumentName ?? $diditDocumentName;
// ============================================================
// USER-FACING MESSAGE
// ============================================================
$messages = [
    'pending'             => 'You have not started verification yet.',
    'in_progress'         => 'Your verification is in progress. Complete the Didit flow to continue.',
    'review_needed'       => 'Your verification needs additional review by our team.',
    'approved'            => 'You are a verified agent.',
    'kyc_passed'          => 'Your personal identity is verified.',
    'documents_submitted' => 'Your documents have been submitted and are awaiting review.',
    'rejected'            => 'Your verification was declined. Please contact support.',
    'expired'             => 'Your previous verification expired. Please start a new one.',
    'suspended'           => 'Your verification is suspended. Please contact support.',
];
$message = $messages[$status] ?? 'Unknown state.';
if ($status === 'rejected' && ($nameMismatch || stripos((string)$rejectionReason, 'name') !== false)) {
    $message = 'Your identity verification was declined because the name on the submitted identity document does not match the registered account name. Please contact support.';
} elseif ($status === 'review_needed' && $nameMatch === 'unreadable') {
    $message = 'Your verification needs additional review because the name on the identity document could not be read clearly.';
} elseif ($status === 'review_needed' && $nameMismatch) {
    $message = 'Your verification needs additional review due to a possible name mismatch.';
}
// ============================================================
// RESPONSE
// ============================================================
echo json_encode([
    'success'         => true,
    'status'          => $status,
    'diditStatus'     => $diditStatus,
    'sessionId'       => $sessionId,
    'startedAt'       => $startedAt,
    'submittedAt'     => $submittedAt,
    'completedAt'     => $decisionAt ?? ($completedAt ?? null),
    'message'         => $message,
    'nameMatch'       => $nameMatch,
    'nameMismatch'    => $nameMismatch,
    'documentName'    => $documentName,
    'expectedName'    => $expectedName,
    'rejectionReason' => $rejectionReason,
]);
