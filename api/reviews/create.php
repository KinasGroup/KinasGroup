<?php
/**
 * KINAS GROUP — Product Review Submission Endpoint
 *
 * POST /api/reviews/create.php
 *
 * Expected fields:
 * - csrf_token
 * - listing_type  car | property | solar | marketplace
 * - listing_id
 * - rating        1 to 5
 * - comment
 *
 * Reviews are created as pending and must be approved by admin
 * before they appear publicly.
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
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 1. Authentication
// ------------------------------------------------------------

$userId = kinas_review_current_user_id();

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Please log in to submit a review.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 2. CSRF protection
// ------------------------------------------------------------

$csrfToken = $_POST['csrf_token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? '';

if (!kinas_review_verify_csrf_token((string)$csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Your session security token is invalid. Please refresh the page and try again.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 3. Rate limiting
//
// Allows a maximum of 5 review submissions per hour for each user.
// This helps prevent spam and abusive automated reviews.
// ------------------------------------------------------------

kinas_review_rate_limit('review_create', (int)$userId, 5, 3600);

// ------------------------------------------------------------
// 4. Read input
// ------------------------------------------------------------

$jsonInput = json_decode(file_get_contents('php://input'), true);

if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$input = array_merge($jsonInput, $_POST);

$listingType = trim((string)($input['listing_type'] ?? ''));
$listingId = (int)($input['listing_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$comment = (string)($input['comment'] ?? '');

if ($listingId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid product listing.',
    ]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Please choose a rating from 1 to 5 stars.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 5. Submit review
// ------------------------------------------------------------

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('api/reviews/create.php database error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'The review system is temporarily unavailable. Please try again later.',
    ]);
    exit;
}

$result = kinas_create_product_review(
    $db,
    (int)$userId,
    $listingType,
    $listingId,
    $rating,
    $comment
);

if (!empty($result['success'])) {
    if (class_exists('Security', false) && method_exists('Security', 'logActivity')) {
        Security::logActivity(
            (int)$userId,
            'product_review_submitted',
            sprintf(
                'Submitted a %d-star review for %s listing #%d',
                $rating,
                $listingType,
                $listingId
            )
        );
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Thank you. Your review has been submitted and is awaiting moderation.',
        'review_id' => $result['review_id'] ?? null,
    ]);
    exit;
}

$error = $result['error'] ?? 'Could not submit your review. Please try again.';

// Use a more accurate HTTP code for common duplicate-review cases.
if (stripos($error, 'already') !== false) {
    http_response_code(409);
} else {
    http_response_code(422);
}

echo json_encode([
    'success' => false,
    'error' => $error,
]);
