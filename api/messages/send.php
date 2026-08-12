<?php
/**
 * KINAS GROUP — Chat Send Endpoint (REBUILT MESSENGER)
 *
 * POST /api/messages/send.php   (multipart/form-data)
 *
 * Fields:
 *   csrf_token      required
 *   receiver_id     required — the other party
 *   listing_id      required — every message belongs to a listing
 *   listing_type    required — car | property | solar | marketplace
 *   body            optional text (max 2000 chars; required if no media)
 *   images[]        optional, up to 4 files (jpg/jpeg/png/webp, ≤10MB each)
 *   audio           optional, ONE voice note (webm/mp4/m4a/ogg/mp3, ≤4MB)
 *   audio_duration  optional seconds (client-enforced 180s max)
 *
 * Rules enforced here:
 *   1. Only User ↔ Agent pairs may chat (admin oversight stays read-only).
 *   2. A conversation must reference an existing listing.
 *   3. A NEW thread can only be started with the listing's agent
 *      (or with someone you already have a thread with about that listing)
 *      — this is what makes "no compose button" safe server-side too.
 *   4. One media kind per message (images OR voice note, never both).
 *
 * Files are stored locally under /uploads/chat/ (the web root already
 * serves /uploads/, same as /uploads/solar-reports/).
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

function chat_send_fail(int $code, string $error): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

/** Normalize $_FILES entries (single file or array-of-files) into a list. */
function chat_send_normalize_files(?array $entry): array
{
    if (!$entry || !isset($entry['name'])) {
        return [];
    }

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

/** Validate + store one uploaded file. Returns public URL or null. */
function chat_send_store_file(array $file, string $kind, array $allowedExt, array $allowedMime, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_CANT_WRITE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime === false || !in_array($mime, $allowedMime, true)) return null;

    $dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fname = $kind . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) {
        return null;
    }

    return '/uploads/chat/' . $fname;
}

// ------------------------------------------------------------
// 1. Auth + CSRF + rate limit
// ------------------------------------------------------------
if (!SessionManager::isLoggedIn()) {
    chat_send_fail(401, 'Please log in to send messages.');
}

$senderId   = (int)SessionManager::getUserId();
$senderRole = (string)($_SESSION['user_role'] ?? '');

$csrf = (string)($_POST['csrf_token'] ?? '');
if (!Security::verifyCSRFToken($csrf)) {
    chat_send_fail(403, 'Invalid security token. Please refresh and try again.');
}

Security::rateLimitDB('chat_send_u' . $senderId, 30, 600);

// ------------------------------------------------------------
// 2. Basic input validation
// ------------------------------------------------------------
$receiverId  = (int)($_POST['receiver_id'] ?? 0);
$listingId   = (int)($_POST['listing_id'] ?? 0);
$listingType = strtolower(trim((string)($_POST['listing_type'] ?? '')));
$body        = trim(Security::sanitizeInput((string)($_POST['body'] ?? '')));

if ($receiverId <= 0 || $receiverId === $senderId) {
    chat_send_fail(422, 'Invalid recipient.');
}

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
if (!isset($tableMap[$listingType]) || $listingId <= 0) {
    chat_send_fail(422, 'Every message must be about a product listing.');
}

if (function_exists('mb_strlen') ? mb_strlen($body) > 2000 : strlen($body) > 2000) {
    chat_send_fail(422, 'Message is too long (max 2000 characters).');
}

// ------------------------------------------------------------
// 3. Media validation (images XOR voice note)
// ------------------------------------------------------------
$imageFiles = chat_send_normalize_files($_FILES['images'] ?? null);
$audioFiles = chat_send_normalize_files($_FILES['audio'] ?? null);

if (count($imageFiles) > 4) {
    chat_send_fail(422, 'You can attach up to 4 images per message.');
}
if (count($audioFiles) > 1) {
    chat_send_fail(422, 'Only one voice note per message.');
}
if (!empty($imageFiles) && !empty($audioFiles)) {
    chat_send_fail(422, 'Send images and voice notes in separate messages.');
}
if ($body === '' && empty($imageFiles) && empty($audioFiles)) {
    chat_send_fail(422, 'Message is empty.');
}

// ------------------------------------------------------------
// 4. Database checks: roles, listing, connection
// ------------------------------------------------------------
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('api/messages/send.php db error: ' . $e->getMessage());
    chat_send_fail(500, 'Messaging is temporarily unavailable. Please try again.');
}

// Roles: only user <-> agent pairs.
$roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$receiverId]);
$receiverRole = $roleStmt->fetchColumn();

$pairValid =
    ($senderRole === 'user'  && $receiverRole === 'agent') ||
    ($senderRole === 'agent' && $receiverRole === 'user');

if (!$pairValid) {
    chat_send_fail(403, 'Messaging is only available between customers and agents.');
}

// Listing must exist (any status — conversations survive sold listings).
$table = $tableMap[$listingType];
$listStmt = $db->prepare("SELECT id, agent_id FROM {$table} WHERE id = ?");
$listStmt->execute([$listingId]);
$listing = $listStmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    chat_send_fail(404, 'Listing not found. Messages must be about a product listing.');
}

$listingAgentId = (int)$listing['agent_id'];

// Existing thread between this pair about this listing?
$threadStmt = $db->prepare("
    SELECT COUNT(*) FROM messages
    WHERE listing_id = ? AND listing_type = ?
      AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
");
$threadStmt->execute([$listingId, $listingType, $senderId, $receiverId, $receiverId, $senderId]);
$hasThread = ((int)$threadStmt->fetchColumn()) > 0;

// Starting a NEW thread is only allowed with the listing's agent.
if ($senderRole === 'user') {
    $mayStart = ($receiverId === $listingAgentId);
} else {
    $mayStart = ($senderId === $listingAgentId);
}

if (!$hasThread && !$mayStart) {
    chat_send_fail(403, 'You can only message the agent of this listing, or someone you already have a conversation with about it.');
}

// ------------------------------------------------------------
// 5. Store uploads
// ------------------------------------------------------------
$imageUrls = [];
foreach ($imageFiles as $img) {
    $url = chat_send_store_file(
        $img,
        'img',
        ['jpg', 'jpeg', 'png', 'webp'],
        ['image/jpeg', 'image/png', 'image/webp'],
        10 * 1024 * 1024 // 10MB
    );
    if ($url === null) {
        chat_send_fail(422, 'One of the images was rejected (type, size or upload error).');
    }
    $imageUrls[] = $url;
}

$audioUrl = null;
$audioDuration = 0;
if (!empty($audioFiles)) {
    $audioUrl = chat_send_store_file(
        $audioFiles[0],
        'voice',
        ['webm', 'mp4', 'm4a', 'ogg', 'oga', 'mp3'],
        ['audio/webm', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/mpeg'],
        4 * 1024 * 1024 // ~3-minute voice note
    );
    if ($audioUrl === null) {
        chat_send_fail(422, 'Voice note was rejected (type, size or upload error).');
    }
    $audioDuration = max(0, min(3600, (int)($_POST['audio_duration'] ?? 0)));
}

// ------------------------------------------------------------
// 6. Build + insert the message
// ------------------------------------------------------------
$messageType = !empty($imageUrls) ? 'image' : ($audioUrl !== null ? 'audio' : 'text');

$mediaUrlStored = null;
if ($messageType === 'image') {
    $mediaUrlStored = count($imageUrls) === 1 ? $imageUrls[0] : json_encode($imageUrls);
} elseif ($messageType === 'audio') {
    $mediaUrlStored = $audioUrl;
}

try {
    $ins = $db->prepare("
        INSERT INTO messages
        (sender_id, receiver_id, listing_id, listing_type, subject, body,
         message_type, media_url, media_duration_sec, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $ins->execute([
        $senderId,
        $receiverId,
        $listingId,
        $listingType,
        $messageType === 'text' ? 'Chat' : ucfirst($messageType),
        $body,
        $messageType,
        $mediaUrlStored,
        $messageType === 'audio' ? $audioDuration : null,
    ]);
    $messageId = (int)$db->lastInsertId();
} catch (Throwable $e) {
    error_log('api/messages/send.php insert error: ' . $e->getMessage());
    chat_send_fail(500, 'Could not send the message. If this persists, the chat upgrade SQL may not have been run yet.');
}

$now = date('Y-m-d H:i:s');

echo json_encode([
    'success' => true,
    'message' => [
        'id'                 => $messageId,
        'sender_id'          => $senderId,
        'receiver_id'        => $receiverId,
        'listing_id'         => $listingId,
        'listing_type'       => $listingType,
        'body'               => $body,
        'message_type'       => $messageType,
        'media_urls'         => $messageType === 'image' ? $imageUrls : ($audioUrl !== null ? [$audioUrl] : []),
        'media_duration_sec' => $messageType === 'audio' ? $audioDuration : null,
        'is_read'            => 0,
        'created_at'         => $now,
        'time_formatted'     => date('g:i A', strtotime($now)),
    ],
]);
