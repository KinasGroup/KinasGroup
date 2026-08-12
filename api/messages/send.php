<?php
/**
 * KINAS GROUP — Chat Send Endpoint
 *
 * AMENDED: a delisted/removed/deleted product no longer closes an existing
 * thread. Replies are allowed whenever a conversation already exists between
 * the two users about that listing. The listing only gates STARTING a new
 * thread (and must be active for that).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if (file_exists(__DIR__ . '/../../includes/r2-upload.php')) {
    require_once __DIR__ . '/../../includes/r2-upload.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to send messages.']);
    exit;
}
$userId   = (int)SessionManager::getUserId();
$senderRole = (string)($_SESSION['user_role'] ?? '');

$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!Security::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}
Security::rateLimitDB('chat_send_u' . $userId, 30, 600);

// ------------------------------------------------------------
// Media helpers (unchanged)
// ------------------------------------------------------------
function chat_normalize_files(?array $entry): array
{
    if (!$entry || !isset($entry['name'])) return [];
    $out = [];
    if (is_array($entry['name'])) {
        $count = count($entry['name']);
        for ($i = 0; $i < $count; $i++) {
            $err = $entry['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $out[] = [
                'name'     => (string)($entry['name'][$i] ?? ''),
                'type'     => (string)($entry['type'][$i] ?? ''),
                'tmp_name' => (string)($entry['tmp_name'][$i] ?? ''),
                'error'    => $err,
                'size'     => (int)($entry['size'][$i] ?? 0),
            ];
        }
    } else {
        if (($entry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $out[] = [
                'name'     => (string)($entry['name'] ?? ''),
                'type'     => (string)($entry['type'] ?? ''),
                'tmp_name' => (string)($entry['tmp_name'] ?? ''),
                'error'    => (int)($entry['error'] ?? 0),
                'size'     => (int)($entry['size'] ?? 0),
            ];
        }
    }
    return $out;
}

function chat_store_media(array $file, string $prefix, array $mimeMap, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_CANT_WRITE) !== UPLOAD_ERR_OK) return null;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if ($mime === false || !isset($mimeMap[$mime])) return null;
    $ext = $mimeMap[$mime];

    if (class_exists('R2Upload', false)) {
        try {
            $uploader = new R2Upload('general', $mimeMap, $maxBytes);
            $result = $uploader->upload($file, ['prefix' => $prefix]);
            if (!empty($result['success']) && !empty($result['filepath'])) {
                return (string)$result['filepath'];
            }
            error_log('chat media: R2 upload failed — ' . ($result['error'] ?? 'unknown'));
        } catch (Throwable $e) {
            error_log('chat media: R2 unavailable, using local — ' . $e->getMessage());
        }
    }

    $dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $fname = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) return null;
    return '/uploads/chat/' . $fname;
}

$IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$AUDIO_MIMES = [
    'audio/webm'  => 'webm',
    'audio/mp4'   => 'mp4',
    'audio/x-m4a' => 'm4a',
    'audio/ogg'   => 'ogg',
    'audio/mpeg'  => 'mp3',
];

// ------------------------------------------------------------
// Read + validate inputs
// ------------------------------------------------------------
$receiverId  = (int)($_POST['receiver_id'] ?? 0);
$listingId   = (int)($_POST['listing_id'] ?? 0);
$listingType = strtolower(trim((string)($_POST['listing_type'] ?? '')));
$body        = trim((string)($_POST['body'] ?? ''));

if ($receiverId <= 0 || $receiverId === $userId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid recipient.']);
    exit;
}
$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
if (!isset($tableMap[$listingType]) || $listingId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Every message must be about a product listing.']);
    exit;
}
if (function_exists('mb_strlen') ? mb_strlen($body) > 2000 : strlen($body) > 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Message is too long (max 2000 characters).']);
    exit;
}

$imageFiles = chat_normalize_files($_FILES['images'] ?? null);
$audioFiles = chat_normalize_files($_FILES['audio'] ?? null);
if (count($imageFiles) > 4) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You can attach up to 4 images per message.']);
    exit;
}
if (count($audioFiles) > 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only one voice note per message.']);
    exit;
}
if (!empty($imageFiles) && !empty($audioFiles)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Send images and voice notes in separate messages.']);
    exit;
}
if ($body === '' && empty($imageFiles) && empty($audioFiles)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Message is empty.']);
    exit;
}

// ------------------------------------------------------------
// DB checks: roles, listing, connection
// AMENDED gating — existing threads survive delisting/deletion.
// ------------------------------------------------------------
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Messaging is temporarily unavailable.']);
    exit;
}

$roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$receiverId]);
$receiverRole = (string)$roleStmt->fetchColumn();

$pairValid =
    ($senderRole === 'user'  && $receiverRole === 'agent') ||
    ($senderRole === 'agent' && $receiverRole === 'user');
if (!$pairValid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Messaging is only available between customers and agents.']);
    exit;
}

// Does a conversation already exist between this pair about this listing?
$threadStmt = $db->prepare("
    SELECT COUNT(*) FROM messages
    WHERE listing_id = ? AND listing_type = ?
      AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
");
$threadStmt->execute([$listingId, $listingType, $userId, $receiverId, $receiverId, $userId]);
$hasThread = ((int)$threadStmt->fetchColumn()) > 0;

// Look up the listing WITHOUT a status filter (so removed/delisted still resolve).
$table = $tableMap[$listingType];
$listStmt = $db->prepare("SELECT id, agent_id, status FROM {$table} WHERE id = ?");
$listStmt->execute([$listingId]);
$listing = $listStmt->fetch(PDO::FETCH_ASSOC);

$mayStart = false;
if ($listing) {
    $listingAgentId = (int)$listing['agent_id'];
    // A NEW thread may only start with the listing's agent, and only while active.
    if (($listing['status'] ?? '') === 'active') {
        $mayStart = ($senderRole === 'user')
            ? ($receiverId === $listingAgentId)
            : ($userId === $listingAgentId);
    }
} else {
    // Listing row fully deleted: only an existing thread may continue.
    if (!$hasThread) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Listing not found.']);
        exit;
    }
}

// Final gate: an existing thread ALWAYS stays open; a new thread needs a live listing.
if (!$hasThread && !$mayStart) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only message the agent of this listing, or continue an existing conversation about it.']);
    exit;
}

// ------------------------------------------------------------
// Store media (R2 first, local fallback)
// ------------------------------------------------------------
$imageUrls = [];
foreach ($imageFiles as $img) {
    $url = chat_store_media($img, 'chat_img_', $IMAGE_MIMES, 10 * 1024 * 1024);
    if ($url === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'One of the images was rejected (type, size or upload error).']);
        exit;
    }
    $imageUrls[] = $url;
}

$audioUrl = null;
$audioDuration = 0;
if (!empty($audioFiles)) {
    $audioUrl = chat_store_media($audioFiles[0], 'chat_voice_', $AUDIO_MIMES, 4 * 1024 * 1024);
    if ($audioUrl === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Voice note was rejected (type, size or upload error).']);
        exit;
    }
    $audioDuration = max(0, min(3600, (int)($_POST['audio_duration'] ?? 0)));
}

// ------------------------------------------------------------
// Insert message
// ------------------------------------------------------------
$messageType  = !empty($imageUrls) ? 'image' : ($audioUrl !== null ? 'audio' : 'text');
$mediaStored  = null;
if ($messageType === 'image') {
    $mediaStored = count($imageUrls) === 1 ? $imageUrls[0] : json_encode($imageUrls);
} elseif ($messageType === 'audio') {
    $mediaStored = $audioUrl;
}
$subject = $messageType === 'image' ? 'Photo' : ($messageType === 'audio' ? 'Voice note' : 'Reply');

try {
    $ins = $db->prepare("
        INSERT INTO messages
        (sender_id, receiver_id, listing_id, listing_type, subject, body,
         message_type, media_url, media_duration_sec, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $ins->execute([
        $userId, $receiverId, $listingId, $listingType, $subject, $body,
        $messageType, $mediaStored,
        $messageType === 'audio' ? $audioDuration : null,
    ]);
    $messageId = (int)$db->lastInsertId();
} catch (Throwable $e) {
    error_log('api/messages/send.php insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send the message. Please try again.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => [
        'id'                   => $messageId,
        'sender_id'            => $userId,
        'receiver_id'          => $receiverId,
        'body'                 => $body,
        'message_type'         => $messageType,
        'media_urls'           => $messageType === 'image' ? $imageUrls : ($audioUrl !== null ? [$audioUrl] : []),
        'media_duration_sec'   => $messageType === 'audio' ? $audioDuration : null,
        'is_read'              => 0,
        'created_at_formatted' => date('g:i A'),
    ],
]);
