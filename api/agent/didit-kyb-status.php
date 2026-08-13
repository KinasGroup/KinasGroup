<?php
/**
 * KINAS GROUP — Didit KYB status (for the agent verification page)
 * GET /api/agent/didit-kyb-status.php
 *   Returns: { status, diditStatus, startedAt, completedAt, message, registry }
 *
 * REVAMP: runs the on-demand Didit KYB sync FIRST (self-heal) so a
 * missed webhook can never leave an approved business showing pending.
 */
header('Content-Type: application/json');
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
try { didit_sync_kyb($db, $userId); } catch (Throwable $e) { error_log('didit-kyb-status sync error: ' . $e->getMessage()); }

$row = $db->prepare("
    SELECT ap.kyb_status, ap.kyb_submitted_at, ap.kyb_decision_at, ap.kyb_registry_snapshot,
           dv.session_id, dv.didit_status, dv.created_at, dv.completed_at
    FROM agent_profiles ap
    LEFT JOIN didit_verifications dv ON dv.user_id = ap.user_id AND dv.session_type = 'kyb'
    WHERE ap.user_id = ?
    ORDER BY dv.id DESC LIMIT 1
");
$row->execute([$userId]);
$row = $row->fetch(PDO::FETCH_ASSOC) ?: [];
$status = $row['kyb_status'] ?? 'not_started';
$messages = [
    'not_started'   => 'You have not started business verification yet.',
    'in_progress'   => 'Your business verification is in progress. Complete the Didit flow to continue.',
    'review_needed' => 'Your business verification needs additional review by our team.',
    'approved'      => 'Your business is verified.',
    'rejected'      => 'Your business verification was declined. Please contact support.',
    'expired'       => 'Your previous business verification expired. Please start a new one.',
];
$registry = null;
if (!empty($row['kyb_registry_snapshot'])) {
    $registry = json_decode($row['kyb_registry_snapshot'], true);
}
echo json_encode([
    'success'      => true,
    'status'       => $status,
    'diditStatus'  => $row['didit_status']    ?? null,
    'sessionId'    => $row['session_id']      ?? null,
    'startedAt'    => $row['created_at']      ?? null,
    'submittedAt'  => $row['kyb_submitted_at'] ?? null,
    'completedAt'  => $row['kyb_decision_at']  ?? ($row['completed_at'] ?? null),
    'message'      => $messages[$status]      ?? 'Unknown state.',
    'registry'     => $registry,
]);
