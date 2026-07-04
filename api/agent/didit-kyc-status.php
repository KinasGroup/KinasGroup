<?php
/**
 * KINAS GROUP — Didit KYC status (for the agent verification page)
 * GET /api/agent/didit-kyc-status.php
 *   Returns: { status, diditStatus, startedAt, completedAt, message }
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireAgent();

$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();

$row = $db->prepare("
    SELECT ap.verification_status, ap.kyc_submitted_at, ap.kyc_decision_at,
           dv.session_id, dv.didit_status, dv.created_at, dv.completed_at
    FROM agent_profiles ap
    LEFT JOIN didit_verifications dv ON dv.user_id = ap.user_id AND dv.session_type = 'kyc'
    WHERE ap.user_id = ?
    ORDER BY dv.id DESC LIMIT 1
");
$row->execute([$userId]);
$row = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$status = $row['verification_status'] ?? 'pending';

$messages = [
    'pending'       => 'You have not started verification yet.',
    'in_progress'   => 'Your verification is in progress. Complete the Didit flow to continue.',
    'review_needed' => 'Your verification needs additional review by our team.',
    'approved'      => 'You are a verified agent.',
    'rejected'      => 'Your verification was declined. Please contact support.',
    'expired'       => 'Your previous verification expired. Please start a new one.',
];

echo json_encode([
    'success'      => true,
    'status'       => $status,
    'diditStatus'  => $row['didit_status']   ?? null,
    'sessionId'    => $row['session_id']     ?? null,
    'startedAt'    => $row['created_at']     ?? null,
    'submittedAt'  => $row['kyc_submitted_at'] ?? null,
    'completedAt'  => $row['kyc_decision_at']  ?? ($row['completed_at'] ?? null),
    'message'      => $messages[$status]     ?? 'Unknown state.',
]);
