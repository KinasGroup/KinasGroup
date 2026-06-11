<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

if (empty($code)) {
    http_response_code(422);
    echo json_encode(['error' => 'Verification code is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Match on the code alone — email verification is a soft signal that
    // sets users.verified + users.email_verified_at. New registrations
    // are 'active' from the start (so the user can log in right away),
    // so the old "AND status='pending'" filter rejected every legitimate
    // code and produced the false "link expired" error.
    $stmt = $db->prepare(
        "SELECT id, name, verification_code_expires, email_verified_at
         FROM users
         WHERE verification_code = ?
         LIMIT 1"
    );
    $stmt->execute([$code]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or already-used verification code']);
        exit;
    }
    if (!empty($user['email_verified_at'])) {
        // Idempotent — already verified, treat as success.
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Email already verified. You can log in.']);
        exit;
    }
    if (!empty($user['verification_code_expires']) && strtotime((string)$user['verification_code_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Verification code expired. Please request a new one.']);
        exit;
    }

    $stmt = $db->prepare(
        "UPDATE users
            SET verified=1,
                verification_code=NULL,
                verification_code_expires=NULL,
                email_verified_at=NOW()
          WHERE id = ?"
    );
    $stmt->execute([$user['id']]);

    Security::logActivity($user['id'], 'email_verified', 'Email verified successfully');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully. You can now log in.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed']);
}
?>