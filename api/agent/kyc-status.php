<?php
/**
 * KINAS GROUP — KYC status (for the agent verification page)
 *
 * GET /api/agent/kyc-status.php
 *   Returns: { status, matiStatus, startedAt, completedAt, message }
 */
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/metamap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireAgent();

$userId = (int)$_SESSION['user_id'];
$db     = Database::getInstance()->getConnection();

$row = $db->prepare("
    SELECT ap.verification_status, ap.kyc_submitted_at, ap.kyc_decision_at,
           mv.verification_id, mv.mati_status, mv.created_at, mv.completed_at
    FROM agent_profiles ap
    LEFT JOIN metamap_verifications mv ON mv.user_id = ap.user_id
    WHERE ap.user_id = ?
    ORDER BY mv.id DESC LIMIT 1
");
$row->execute([$userId]);
$row = $row->fetch(PDO::FETCH_ASSOC) ?: [];

$status = $row['verification_status'] ?? 'pending';

$messages = [
    'pending'       => 'You have not started verification yet.',
    'in_progress'   => 'Your verification is in progress. Complete the MetaMap flow to continue.',
    'review_needed' => 'Your verification needs additional review by our team.',
    'approved'      => 'You are a verified agent.',
    'rejected'      => 'Your verification was rejected. Please contact support.',
    'expired'       => 'Your previous verification expired. Please start a new one.',
];

echo json_encode([
    'success'      => true,
    'status'       => $status,
    'matiStatus'   => $row['mati_status']  ?? null,
    'verificationId' => $row['verification_id'] ?? null,
    'startedAt'    => $row['created_at']   ?? null,
    'submittedAt'  => $row['kyc_submitted_at'] ?? null,
    'completedAt'  => $row['kyc_decision_at']  ?? ($row['completed_at'] ?? null),
    'message'      => $messages[$status]   ?? 'Unknown state.',
]);
