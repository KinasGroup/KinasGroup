<?php
/**
 * KINAS GROUP — Chat media pre-upload endpoint
 * POST /api/messages/upload-media.php (multipart: csrf_token, file)
 * Stores ONE chat image (R2 first, local fallback), returns its URL so
 * the client can show a thumbnail with real round upload progress and
 * only enable Send once storage is confirmed.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
if (file_exists(__DIR__ . '/../../includes/r2-upload.php')) {
    require_once __DIR__ . '/../../includes/r2-upload.php';
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed.']); exit; }
if (!SessionManager::isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Please log in.']); exit; }
$csrf = (string)($_POST['csrf_token'] ?? '');
if (!Security::verifyCSRFToken($csrf)) { http_response_code(403); echo json_encode(['success' => false, 'error' => 'Invalid security token.']); exit; }
$userId = (int)SessionManager::getUserId();
Security::rateLimitDB('chat_upload_u' . $userId, 40, 600);

$IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function chatup_store(array $file, string $prefix, array $mimeMap, int $maxBytes): ?string
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
            if (!empty($result['success']) && !empty($result['filepath'])) return (string)$result['filepath'];
            error_log('chat upload: R2 failed — ' . ($result['error'] ?? 'unknown'));
        } catch (Throwable $e) { error_log('chat upload: R2 unavailable, local — ' . $e->getMessage()); }
    }
    $dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $fname = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) return null;
    return '/uploads/chat/' . $fname;
}

if (!isset($_FILES['file'])) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'No file received.']); exit; }
$url = chatup_store($_FILES['file'], 'chat_img_', $IMAGE_MIMES, 10 * 1024 * 1024);
if ($url === null) { http_response_code(422); echo json_encode(['success' => false, 'error' => 'Image rejected (type, size or upload error). Only JPG, PNG or WEBP up to 10MB.']); exit; }
echo json_encode(['success' => true, 'url' => $url]);
