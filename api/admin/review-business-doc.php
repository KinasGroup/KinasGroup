<?php
/**
 * KINAS GROUP — Admin reviews a business document
 *
 * POST /api/admin/review-business-doc.php
 *   Fields: csrf_token, document_id, action ('approve'|'reject'), notes?
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/notify.php';

SessionManager::requireAdmin();

$body  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$token = $body['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$docId   = (int)($body['document_id'] ?? $_POST['document_id'] ?? 0);
$action  = $body['action'] ?? $_POST['action'] ?? '';
$notes   = trim((string)($body['notes'] ?? $_POST['notes'] ?? ''));

if (!$docId || !in_array($action, ['approve','reject'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$db = Database::getInstance()->getConnection();
$db->beginTransaction();
try {
    $stmt = $db->prepare("SELECT id, user_id, document_type, status FROM business_documents WHERE id = ? FOR UPDATE");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Document not found.']);
        exit;
    }
    if ($doc['status'] !== 'pending') {
        $db->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'This document has already been reviewed.']);
        exit;
    }

    $newDocStatus = $action === 'approve' ? 'approved' : 'rejected';
    $db->prepare("UPDATE business_documents SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
        ->execute([$newDocStatus, $notes, $_SESSION['user_id'], $docId]);

    $agentUserId = (int)$doc['user_id'];

    if ($action === 'approve') {
        $db->prepare("UPDATE agent_profiles
                      SET verification_status = 'approved',
                          business_doc_reviewed_by = ?,
                          business_doc_reviewed_at = NOW(),
                          business_doc_notes = ?
                      WHERE user_id = ?")
            ->execute([$_SESSION['user_id'], $notes, $agentUserId]);
        $db->prepare("UPDATE users SET verified = 1, status = 'active' WHERE id = ?")
            ->execute([$agentUserId]);
        $_SESSION['user_verified'] = true; // in case admin is also the agent
        Security::logActivity($agentUserId, 'business_doc_approved', "Approved by admin");
    } else {
        $db->prepare("UPDATE agent_profiles
                      SET verification_status = 'rejected',
                          business_doc_reviewed_by = ?,
                          business_doc_reviewed_at = NOW(),
                          business_doc_notes = ?
                      WHERE user_id = ?")
            ->execute([$_SESSION['user_id'], $notes ?: 'Did not meet requirements', $agentUserId]);
        $db->prepare("UPDATE users SET verified = 0 WHERE id = ?")->execute([$agentUserId]);
        Security::logActivity($agentUserId, 'business_doc_rejected', "Rejected by admin: $notes");
    }

    Security::logActivity($_SESSION['user_id'], 'admin_review_business_doc', "Doc $docId: $action");

    $db->commit();

    // Notify the agent by SMS
    $stU = $db->prepare("SELECT name, phone, phone_verified_at FROM users WHERE id = ?");
    $stU->execute([$agentUserId]);
    $u = $stU->fetch(PDO::FETCH_ASSOC);
    if ($u && !empty($u['phone']) && !empty($u['phone_verified_at'])) {
        $msg = $action === 'approve'
            ? "Congratulations {$u['name']}! KINAS GROUP has approved your business verification. You can now create listings."
            : "Hi {$u['name']}, your business document review was not approved. Please re-submit with corrections at kinas-group.com/agent/verification.php";
        Notify::sms($u['phone'], $msg, 'KYC_DECISION');
    }

    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Agent approved.' : 'Document rejected.',
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('admin review business doc error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Review failed. Please try again.']);
}
