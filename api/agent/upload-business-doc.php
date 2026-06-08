<?php
/**
 * KINAS GROUP — Agent uploads business documents (CAC, TIN, utility bill)
 *
 * POST /api/agent/upload-business-doc.php (multipart/form-data)
 *   Fields:
 *     csrf_token
 *     document_type: 'cac_certificate' | 'tin_certificate' | 'utility_bill' | 'other'
 *     file: the PDF or image
 *     cac_number?  (optional, only relevant for cac_certificate)
 *     tin_number?  (optional, only relevant for tin_certificate)
 *     company_legal_name? (optional)
 *
 * Effect:
 *   - Stores the file (R2 or local)
 *   - Inserts a row in business_documents with status='pending'
 *   - Bumps agent_profiles.verification_status to 'documents_submitted' if currently 'kyc_passed'
 *   - Sends an SMS to the agent confirming submission
 *   - Logs the activity
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/security.php';
require_once '../../includes/file-upload.php';
require_once '../../includes/notify.php';

SessionManager::requireAgent();
$userId = (int)$_SESSION['user_id'];

$token = $_POST['csrf_token'] ?? '';
if (!Security::verifyCSRFToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$allowed = ['cac_certificate', 'tin_certificate', 'utility_bill', 'other'];
$docType = $_POST['document_type'] ?? '';
if (!in_array($docType, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid document type.']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Please attach a file. PDF or image accepted.']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Agent must have passed personal KYC before submitting business docs
$st = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
$st->execute([$userId]);
$status = $st->fetchColumn();
if (!in_array($status, ['kyc_passed', 'documents_submitted', 'rejected'], true)) {
    http_response_code(403);
    echo json_encode([
        'error'  => 'You must complete identity verification before uploading business documents.',
        'redirect' => '/agent/verification.php',
    ]);
    exit;
}

try {
    $uploader = new FileUpload('business-documents');
    $result = $uploader->upload($_FILES['file']);

    if (empty($result['success'])) {
        http_response_code(422);
        echo json_encode(['error' => $result['error'] ?? 'Upload failed']);
        exit;
    }

    $storedUrl = $result['url'] ?? $result['filepath'] ?? $result['filename'] ?? null;
    if (!$storedUrl) {
        throw new RuntimeException('Upload succeeded but no URL was returned');
    }

    $db->beginTransaction();
    $ins = $db->prepare("
        INSERT INTO business_documents
            (user_id, document_type, document_url, file_name, file_size, mime_type, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $ins->execute([
        $userId,
        $docType,
        $storedUrl,
        $result['filename'] ?? ($_FILES['file']['name'] ?? null),
        $result['size'] ?? ($_FILES['file']['size'] ?? null),
        $result['mime_type'] ?? ($_FILES['file']['type'] ?? null),
    ]);

    // Persist CAC/TIN numbers to the profile
    $cac = trim($_POST['cac_number'] ?? '');
    $tin = trim($_POST['tin_number'] ?? '');
    $legalName = trim($_POST['company_legal_name'] ?? '');
    if ($cac || $tin || $legalName) {
        $sets = []; $vals = [];
        if ($cac)        { $sets[] = 'cac_number = ?';         $vals[] = $cac; }
        if ($tin)        { $sets[] = 'tin = ?';                 $vals[] = $tin; }
        if ($legalName)  { $sets[] = 'company_legal_name = ?'; $vals[] = $legalName; }
        $vals[] = $userId;
        $db->prepare("UPDATE agent_profiles SET " . implode(', ', $sets) . " WHERE user_id = ?")
            ->execute($vals);
    }

    // Bump status to documents_submitted (only if currently kyc_passed)
    if ($status === 'kyc_passed') {
        $db->prepare("UPDATE agent_profiles SET verification_status = 'documents_submitted' WHERE user_id = ?")
            ->execute([$userId]);
    }

    Security::logActivity($userId, 'business_doc_uploaded', "Uploaded $docType");

    $db->commit();

    // Notify the agent
    $stU = $db->prepare("SELECT name, phone, phone_verified_at FROM users WHERE id = ?");
    $stU->execute([$userId]);
    $u = $stU->fetch(PDO::FETCH_ASSOC);
    if ($u && !empty($u['phone']) && !empty($u['phone_verified_at'])) {
        Notify::sms(
            $u['phone'],
            "Hi {$u['name']}, your {$docType} has been received and is now under review. We'll notify you by SMS once a decision is made.",
            'KYC_DECISION'
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Document submitted for review.',
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('business-doc upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed. Please try again.']);
}
