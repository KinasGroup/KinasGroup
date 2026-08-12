<?php
/**
 * KINAS GROUP — Product Review Submission Endpoint (ALIGNED)
 *
 * POST /api/reviews/create.php
 *
 * Expected fields:
 * - csrf_token
 * - listing_type  car | property | solar | marketplace
 * - listing_id
 * - rating        1 to 5
 * - title         optional review headline (max 150 chars)
 * - comment       review text (10–2000 chars)
 * - photos[]      optional, up to 4 images (jpg/jpeg/png/webp, ≤ 5MB each)
 *
 * Reviews are created as 'pending' and only appear publicly once an
 * admin approves them (admin/reviews.php), which also notifies the
 * customer by email.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/reviews.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ------------------------------------------------------------
// 1. Authentication
// ------------------------------------------------------------
$userId = kinas_review_current_user_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to submit a review.']);
    exit;
}

// ------------------------------------------------------------
// 2. CSRF protection
// ------------------------------------------------------------
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!kinas_review_verify_csrf_token((string)$csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session security token is invalid. Please refresh the page and try again.']);
    exit;
}

// ------------------------------------------------------------
// 3. Rate limiting (max 5 review submissions per hour per user)
// ------------------------------------------------------------
kinas_review_rate_limit('review_create', (int)$userId, 5, 3600);

// ------------------------------------------------------------
// 4. Read input
// ------------------------------------------------------------
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonInput)) $jsonInput = [];
$input = array_merge($jsonInput, $_POST);

$listingType = trim((string)($input['listing_type'] ?? ''));
$listingId   = (int)($input['listing_id'] ?? 0);
$rating      = (int)($input['rating'] ?? 0);
$comment     = (string)($input['comment'] ?? '');
$title       = (string)($input['title'] ?? '');

if ($listingId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid product listing.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please choose a rating from 1 to 5 stars.']);
    exit;
}

// ------------------------------------------------------------
// 5. Photo upload handling (optional, max 4)
// ------------------------------------------------------------
function review_create_normalize_files(?array $entry): array
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

function review_create_store_photo(array $file, array $allowedExt, array $allowedMime, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_CANT_WRITE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime === false || !in_array($mime, $allowedMime, true)) return null;

    $dir = __DIR__ . '/../../uploads/reviews/';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    $fname = 'rev_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $dir . $fname)) return null;

    return '/uploads/reviews/' . $fname;
}

$photoFiles   = review_create_normalize_files($_FILES['photos'] ?? null);
$photoUrls    = [];
$storedPaths  = [];

if (count($photoFiles) > 4) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You can attach up to 4 photos per review.']);
    exit;
}

if (!empty($photoFiles)) {
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes    = 5 * 1024 * 1024; // 5MB each

    foreach ($photoFiles as $pf) {
        $url = review_create_store_photo($pf, $allowedExt, $allowedMime, $maxBytes);
        if ($url === null) {
            // Clean up anything already stored for this request.
            foreach ($storedPaths as $p) { @unlink($p); }
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'One of the photos was rejected (type, size or upload error). Only JPG, PNG or WEBP up to 5MB are allowed.']);
            exit;
        }
        $photoUrls[]  = $url;
        $storedPaths[] = __DIR__ . '/../../' . ltrim($url, '/');
    }
}

// ------------------------------------------------------------
// 6. Submit review (title + photos go to the aligned engine)
// ------------------------------------------------------------
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    foreach ($storedPaths as $p) { @unlink($p); }
    error_log('api/reviews/create.php database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'The review system is temporarily unavailable. Please try again later.']);
    exit;
}

$result = kinas_create_product_review(
    $db,
    (int)$userId,
    $listingType,
    $listingId,
    $rating,
    $comment,
    $title,
    $photoUrls
);

if (!empty($result['success'])) {
    if (class_exists('Security', false) && method_exists('Security', 'logActivity')) {
        Security::logActivity(
            (int)$userId,
            'product_review_submitted',
            sprintf('Submitted a %d-star review for %s listing #%d (%d photo(s))', $rating, $listingType, $listingId, count($photoUrls))
        );
    }
    echo json_encode([
        'success'   => true,
        'message'   => $result['message'] ?? 'Thank you. Your review has been submitted and is awaiting moderation.',
        'review_id' => $result['review_id'] ?? null,
        'photos'    => count($photoUrls),
    ]);
    exit;
}

// On failure, remove any photos we stored for this request.
foreach ($storedPaths as $p) { @unlink($p); }

$error = $result['error'] ?? 'Could not submit your review. Please try again.';
if (stripos($error, 'already') !== false) {
    http_response_code(409);
} else {
    http_response_code(422);
}
echo json_encode(['success' => false, 'error' => $error]);
