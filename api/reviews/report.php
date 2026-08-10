<?php
/**
 * KINAS GROUP — Product Review Report Endpoint
 *
 * POST /api/reviews/report.php
 *
 * Expected fields:
 * - csrf_token
 * - review_id
 * - reason
 *
 * Allows logged-in customers to report an approved review as
 * inappropriate, spammy, fraudulent, or abusive.
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
        'error' => 'Please log in to report a review.',
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
// Allows a maximum of 10 review reports per hour for each user.
// This helps prevent abuse of the reporting system.
// ------------------------------------------------------------

kinas_review_rate_limit('review_report', (int)$userId, 10, 3600);

// ------------------------------------------------------------
// 4. Read input
// ------------------------------------------------------------

$jsonInput = json_decode(file_get_contents('php://input'), true);

if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$input = array_merge($jsonInput, $_POST);

$reviewId = (int)($input['review_id'] ?? 0);
$reason = (string)($input['reason'] ?? '');

if ($reviewId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid review.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 5. Submit report
// ------------------------------------------------------------

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('api/reviews/report.php database error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'The review system is temporarily unavailable. Please try again later.',
    ]);
    exit;
}

$result = kinas_report_product_review(
    $db,
    (int)$userId,
    $reviewId,
    $reason
);

if (!empty($result['success'])) {
    if (class_exists('Security', false) && method_exists('Security', 'logActivity')) {
        Security::logActivity(
            (int)$userId,
            'product_review_reported',
            sprintf(
                'Reported review #%d for moderation review',
                $reviewId
            )
        );
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Thank you. This review has been reported and will be checked by our team.',
    ]);
    exit;
}

$error = $result['error'] ?? 'Could not report this review. Please try again.';

if (stripos($error, 'already reported') !== false) {
    http_response_code(409);
} elseif (stripos($error, 'could not be found') !== false) {
    http_response_code(404);
} else {
    http_response_code(422);
}

echo json_encode([
    'success' => false,
    'error' => $error,
]);
