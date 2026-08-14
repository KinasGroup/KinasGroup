<?php
/**
 * KINAS GROUP — Chat send endpoint
 *
 * Supports:
 * - text
 * - images
 * - voice notes
 * - uploaded audio files
 * - video files
 * - documents: doc, docx, pdf, ppt, pptx
 *
 * Option A rules:
 * - Customers can message listing agents.
 * - Agents can message customers about their own listings.
 * - Agents can inquire/contact another agent only through that agent's listing.
 * - Random agent-to-agent messaging remains blocked.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

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
    echo json_encode(['success' => false, 'error' => 'Please log in.']);
    exit;
}

$userId     = (int)SessionManager::getUserId();
$senderRole = (string)($_SESSION['user_role'] ?? '');

$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!Security::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

Security::rateLimitDB('chat_send_u' . $userId, 30, 600);

define('CHAT_CLOSED_STATUSES', ['removed', 'inactive']);

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

function chat_attachment_rules(string $kind): ?array
{
    switch ($kind) {
        case 'image':
            return [
                'max'    => 10 * 1024 * 1024,
                'prefix' => 'chat_img_',
                'exts'   => [
                    'jpg'  => 'jpg',
                    'jpeg' => 'jpg',
                    'png'  => 'png',
                    'webp' => 'webp',
                    'gif'  => 'gif',
                ],
                'mimes'  => [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif',
                ],
            ];

        case 'video':
            return [
                'max'    => 25 * 1024 * 1024,
                'prefix' => 'chat_vid_',
                'exts'   => [
                    'mp4'  => 'mp4',
                    'webm' => 'webm',
                    'mov'  => 'mov',
                ],
                'mimes'  => [
                    'video/mp4'       => 'mp4',
                    'video/webm'      => 'webm',
                    'video/quicktime' => 'mov',
                ],
            ];

        case 'audio':
            return [
                'max'    => 10 * 1024 * 1024,
                'prefix' => 'chat_aud_',
                'exts'   => [
                    'mp3'  => 'mp3',
                    'wav'  => 'wav',
                    'm4a'  => 'm4a',
                    'ogg'  => 'ogg',
                    'aac'  => 'aac',
                    'webm' => 'webm',
                ],
                'mimes'  => [
                    'audio/mpeg'      => 'mp3',
                    'audio/mp3'       => 'mp3',
                    'audio/wav'       => 'wav',
                    'audio/x-wav'     => 'wav',
                    'audio/wave'      => 'wav',
                    'audio/mp4'       => 'm4a',
                    'audio/x-m4a'     => 'm4a',
                    'audio/aac'       => 'aac',
                    'audio/ogg'       => 'ogg',
                    'application/ogg' => 'ogg',
                    'audio/webm'      => 'webm',
                ],
            ];

        case 'document':
            return [
                'max'    => 20 * 1024 * 1024,
                'prefix' => 'chat_doc_',
                'exts'   => [
                    'doc'  => 'doc',
                    'docx' => 'docx',
                    'pdf'  => 'pdf',
                    'ppt'  => 'ppt',
                    'pptx' => 'pptx',
                ],
                'mimes'  => [
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/pdf' => 'pdf',
                    'application/vnd.ms-powerpoint' => 'ppt',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                ],
            ];

        default:
            return null;
    }
}

function chat_detect_attachment_kind(array $file): string
{
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

    $imageExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $videoExt = ['mp4', 'webm', 'mov'];
    $audioExt = ['mp3', 'wav', 'm4a', 'ogg', 'aac'];
    $docExt   = ['doc', 'docx', 'pdf', 'ppt', 'pptx'];

    if (in_array($ext, $imageExt, true)) return 'image';
    if (in_array($ext, $videoExt, true)) return 'video';
    if (in_array($ext, $audioExt, true)) return 'audio';
    if (in_array($ext, $docExt, true)) return 'document';

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return '';

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($file['tmp_name']);

    if (strpos($mime, 'image/') === 0) return 'image';
    if (strpos($mime, 'video/') === 0) return 'video';
    if (strpos($mime, 'audio/') === 0 || $mime === 'application/ogg') return 'audio';
    if ($mime === 'application/pdf') return 'document';

    return '';
}

function chat_store_attachment(array $file, string $kind): ?array
{
    $rules = chat_attachment_rules($kind);
    if (!$rules) return null;

    if (($file['error'] ?? UPLOAD_ERR_CANT_WRITE) !== UPLOAD_ERR_OK) return null;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $rules['max']) return null;

    $originalName = basename((string)($file['name'] ?? 'attachment'));
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!isset($rules['exts'][$ext])) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string)$finfo->file($file['tmp_name']);

    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }

    $allowedMimes = array_keys($rules['mimes']);

    // Office files are sometimes detected as zip/octet-stream.
    $officeFallback = in_array($ext, ['docx', 'pptx', 'doc', 'ppt'], true)
        && in_array($mime, ['application/zip', 'application/octet-stream'], true);

    if (!in_array($mime, $allowedMimes, true) && !$officeFallback) {
        return null;
    }

    $safeExt = $rules['exts'][$ext];
    $fnamePrefix = $rules['prefix'];

    // Try R2 first if available.
    if (class_exists('R2Upload', false)) {
        try {
            $uploader = new R2Upload('general', [$mime => $safeExt], $rules['max']);
            $result = $uploader->upload($file, ['prefix' => $fnamePrefix]);

            if (!empty($result['success']) && !empty($result['filepath'])) {
                return [
                    'url'  => (string)$result['filepath'],
                    'name' => $originalName,
                    'mime' => $mime,
                    'size' => (int)$file['size'],
                    'type' => $kind,
                ];
            }

            error_log('chat attachment: R2 failed — ' . ($result['error'] ?? 'unknown'));
        } catch (Throwable $e) {
            error_log('chat attachment: R2 unavailable, using local — ' . $e->getMessage());
        }
    }

    // Local fallback.
    $dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fname = $fnamePrefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $safeExt;

    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) {
        return null;
    }

    return [
        'url'  => '/uploads/chat/' . $fname,
        'name' => $originalName,
        'mime' => $mime,
        'size' => (int)$file['size'],
        'type' => $kind,
    ];
}

// Voice-note storage remains separate because the browser recorder may produce
// audio/webm, video/webm, audio/mp4, etc.
function chat_store_media(array $file, string $prefix, array $mimeMap, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_CANT_WRITE) !== UPLOAD_ERR_OK) return null;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) return null;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if ($mime === false || !isset($mimeMap[$mime])) return null;

    $ext = $mimeMap[$mime];

    $dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $fname = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) return null;

    return '/uploads/chat/' . $fname;
}

$AUDIO_MIMES = [
    'audio/webm'      => 'webm',
    'video/webm'      => 'webm',
    'audio/mp4'       => 'mp4',
    'video/mp4'       => 'mp4',
    'audio/x-m4a'     => 'm4a',
    'audio/ogg'       => 'ogg',
    'application/ogg' => 'ogg',
    'audio/mpeg'      => 'mp3',
];

$receiverId  = (int)($_POST['receiver_id'] ?? 0);
$listingId   = (int)($_POST['listing_id'] ?? 0);
$listingType = strtolower(trim((string)($_POST['listing_type'] ?? '')));
$body        = trim((string)($_POST['body'] ?? ''));

$attachmentKind = strtolower(trim((string)($_POST['attachment_kind'] ?? '')));

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
    echo json_encode(['success' => false, 'error' => 'Message is too long.']);
    exit;
}

$imageFiles      = chat_normalize_files($_FILES['images'] ?? null);
$voiceFiles      = chat_normalize_files($_FILES['audio'] ?? null);
$attachmentFiles = chat_normalize_files($_FILES['attachment'] ?? null);

if (count($imageFiles) > 4) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Max 4 images.']);
    exit;
}

if (count($voiceFiles) > 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only one voice note.']);
    exit;
}

if (count($attachmentFiles) > 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only one attachment file is allowed.']);
    exit;
}

$mediaModes = [];
if (!empty($imageFiles)) $mediaModes[] = 'images';
if (!empty($voiceFiles)) $mediaModes[] = 'voice';
if (!empty($attachmentFiles)) $mediaModes[] = 'attachment';

if (count($mediaModes) > 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please send only one attachment type at a time.']);
    exit;
}

if ($body === '' && empty($imageFiles) && empty($voiceFiles) && empty($attachmentFiles)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Message is empty.']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Messaging unavailable.']);
    exit;
}

$roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$receiverId]);
$receiverRole = (string)$roleStmt->fetchColumn();

if ($receiverRole === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Recipient not found.']);
    exit;
}

// Base pair rules. Agent-agent is provisionally allowed, but later restricted
// to listing-bound inquiry/reply only.
$pairValid =
    $senderRole === 'admin' ||
    ($senderRole === 'user' && $receiverRole === 'agent') ||
    ($senderRole === 'agent' && $receiverRole === 'user') ||
    ($senderRole === 'agent' && $receiverRole === 'agent');

if (!$pairValid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You cannot message this user.']);
    exit;
}

$table = $tableMap[$listingType];

$listStmt = $db->prepare("SELECT id, agent_id, status FROM {$table} WHERE id = ?");
$listStmt->execute([$listingId]);
$listing = $listStmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Listing no longer exists.']);
    exit;
}

if (in_array((string)($listing['status'] ?? ''), CHAT_CLOSED_STATUSES, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Listing is delisted.']);
    exit;
}

$listingAgentId = (int)$listing['agent_id'];

// Agent-to-agent must be listing-bound: one side must be the listing agent.
if ($senderRole === 'agent' && $receiverRole === 'agent') {
    if ($userId !== $listingAgentId && $receiverId !== $listingAgentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Agent-to-agent messaging is only allowed through the listing agent.']);
        exit;
    }
}

$threadStmt = $db->prepare("
    SELECT COUNT(*)
    FROM messages
    WHERE listing_id = ?
      AND listing_type = ?
      AND (
            (sender_id = ? AND receiver_id = ?)
         OR (sender_id = ? AND receiver_id = ?)
      )
");
$threadStmt->execute([$listingId, $listingType, $userId, $receiverId, $receiverId, $userId]);
$hasThread = ((int)$threadStmt->fetchColumn()) > 0;

$mayStart = false;

if ($senderRole === 'admin') {
    $mayStart = true;
} elseif ($senderRole === 'user') {
    $mayStart = ($receiverId === $listingAgentId);
} elseif ($senderRole === 'agent') {
    if ($receiverRole === 'user') {
        // Listing agent can start/reply to customer about own listing.
        $mayStart = ($userId === $listingAgentId);
    } elseif ($receiverRole === 'agent') {
        // Another agent may start an inquiry only to the listing agent.
        $mayStart = ($receiverId === $listingAgentId && $userId !== $listingAgentId);
    }
}

if (!$hasThread && !$mayStart) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You can only message through this listing conversation.']);
    exit;
}

// ------------------------------------------------------------
// Store media
// ------------------------------------------------------------

$imageUrls = [];
foreach ($imageFiles as $img) {
    $stored = chat_store_attachment($img, 'image');
    if ($stored === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Image rejected. Allowed: JPG, PNG, WEBP or GIF up to 10MB.']);
        exit;
    }
    $imageUrls[] = $stored['url'];
}

$audioUrl = null;
$audioDuration = 0;

if (!empty($voiceFiles)) {
    $audioUrl = chat_store_media($voiceFiles[0], 'chat_voice_', $AUDIO_MIMES, 4 * 1024 * 1024);
    if ($audioUrl === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Voice note rejected.']);
        exit;
    }
    $audioDuration = max(0, min(3600, (int)($_POST['audio_duration'] ?? 0)));
}

$attachment = null;

if (!empty($attachmentFiles)) {
    if ($attachmentKind === '') {
        $attachmentKind = chat_detect_attachment_kind($attachmentFiles[0]);
    }

    if ($attachmentKind === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Unsupported attachment type.']);
        exit;
    }

    $attachment = chat_store_attachment($attachmentFiles[0], $attachmentKind);

    if ($attachment === null) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error'   => 'Attachment rejected. Allowed: pictures, videos, audio files, or DOC/DOCX/PDF/PPT/PPTX documents only.',
        ]);
        exit;
    }
}

// ------------------------------------------------------------
// Build message row
// ------------------------------------------------------------

$messageType = 'text';
$mediaStored = null;
$mediaName   = null;
$mediaMime   = null;
$mediaSize   = null;

if (!empty($imageUrls)) {
    $messageType = 'image';
    $mediaStored = count($imageUrls) === 1 ? $imageUrls[0] : json_encode($imageUrls);
} elseif ($attachment !== null) {
    $messageType = $attachment['type']; // image/video/audio/document
    $mediaStored = $attachment['url'];
    $mediaName   = $attachment['name'];
    $mediaMime   = $attachment['mime'];
    $mediaSize   = $attachment['size'];
} elseif ($audioUrl !== null) {
    $messageType = 'audio';
    $mediaStored = $audioUrl;
    $mediaName   = null;
}

$subjectMap = [
    'text'     => 'Reply',
    'image'    => 'Photo',
    'audio'    => 'Voice note',
    'video'    => 'Video',
    'document' => 'Document',
];

$subject = $subjectMap[$messageType] ?? 'Reply';

try {
    $ins = $db->prepare("
        INSERT INTO messages (
            sender_id,
            receiver_id,
            listing_id,
            listing_type,
            subject,
            body,
            message_type,
            media_url,
            media_duration_sec,
            media_name,
            media_mime,
            media_size,
            is_read,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW()
        )
    ");

    $ins->execute([
        $userId,
        $receiverId,
        $listingId,
        $listingType,
        $subject,
        $body,
        $messageType,
        $mediaStored,
        $messageType === 'audio' && $audioUrl !== null ? $audioDuration : null,
        $mediaName,
        $mediaMime,
        $mediaSize,
    ]);

    $messageId = (int)$db->lastInsertId();
} catch (Throwable $e) {
    error_log('chat send insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send message.']);
    exit;
}

$senderNameStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
$senderNameStmt->execute([$userId]);
$senderName = (string)($senderNameStmt->fetchColumn() ?: 'Unknown');

$mediaUrls = [];

if ($messageType === 'image') {
    $mediaUrls = $imageUrls;
} elseif ($attachment !== null) {
    $mediaUrls = [$attachment['url']];
} elseif ($audioUrl !== null) {
    $mediaUrls = [$audioUrl];
}

echo json_encode([
    'success' => true,
    'message' => [
        'id'                 => $messageId,
        'mine'               => true,
        'sender_name'        => $senderName,
        'receiver_id'        => $receiverId,
        'body'               => $body,
        'message_type'       => $messageType,
        'media_urls'         => $mediaUrls,
        'media_duration_sec' => ($messageType === 'audio' && $audioUrl !== null) ? $audioDuration : null,
        'media_name'         => $mediaName,
        'media_mime'         => $mediaMime,
        'media_size'         => $mediaSize,
        'inquiry_meta'       => null,
        'is_read'            => false,
        'is_viewing_request' => false,
        'created_at'         => date('Y-m-d H:i:s'),
        'time_formatted'     => date('g:i A'),
        'date_label'         => 'Today',
    ],
]);
