<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

SessionManager::requireLogin();

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$senderId   = (int)$_SESSION['user_id'];
$receiverId = (int)($data['recipient_id'] ?? $data['receiver_id'] ?? 0);
$listingId  = !empty($data['listing_id']) ? (int)$data['listing_id'] : null;
$listingType = $data['listing_type'] ?? null;
$subject    = trim((string)($data['subject'] ?? ''));
$body       = trim((string)($data['message'] ?? $data['body'] ?? ''));

if (!$receiverId || $body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Recipient and message are required.']);
    exit;
}
if ($receiverId === $senderId) {
    http_response_code(422);
    echo json_encode(['error' => 'You cannot message yourself.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO messages (sender_id, receiver_id, listing_id, listing_type, subject, body, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$senderId, $receiverId, $listingId, $listingType, $subject, $body]);

    // SMS notify the receiver
    $stR = $db->prepare("SELECT name, phone, phone_verified_at FROM users WHERE id = ?");
    $stR->execute([$receiverId]);
    $r = $stR->fetch(PDO::FETCH_ASSOC);
    $stS = $db->prepare("SELECT name FROM users WHERE id = ?");
    $stS->execute([$senderId]);
    $sName = $stS->fetchColumn() ?: 'Someone';

    if ($r && !empty($r['phone']) && !empty($r['phone_verified_at'])) {
        $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 77) . '...' : $body;
        Notify::sms($r['phone'], "New message from {$sName} on KINAS GROUP: \"{$preview}\"", 'NEW_MESSAGE');
    }

    Security::logActivity($senderId, 'message_sent', "To user $receiverId");

    echo json_encode(['success' => true, 'message' => 'Message sent.']);
} catch (Exception $e) {
    error_log('send message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}
