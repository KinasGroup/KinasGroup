<?php
/**
 * KINAS GROUP — Review Helpful Vote Endpoint
 *
 * POST /api/reviews/helpful.php
 *
 * Expected fields:
 * - csrf_token
 * - review_id
 *
 * Toggles the current logged-in user's "helpful" vote on an approved
 * review. A user can have at most one vote per review (unique key
 * uniq_prh_user_review), so clicking again removes the vote.
 *
 * Response:
 * - success: true  → { action: 'added'|'removed', count: <new total> }
 * - success: false → { error: '...' }
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
        'error' => 'Please log in to mark reviews helpful.',
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
// Voting is lightweight, but still capped (30 toggles per hour) to
// stop scripted vote manipulation.
// ------------------------------------------------------------
kinas_review_rate_limit('review_helpful', (int)$userId, 30, 3600);

// ------------------------------------------------------------
// 4. Read input
// ------------------------------------------------------------
$jsonInput = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}
$input = array_merge($jsonInput, $_POST);

$reviewId = (int)($input['review_id'] ?? 0);
if ($reviewId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid review.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 5. Database connection
// ------------------------------------------------------------
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('api/reviews/helpful.php database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'The review system is temporarily unavailable. Please try again later.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 6. Toggle the vote (engine validates review exists + is approved)
// ------------------------------------------------------------
$result = kinas_toggle_helpful($db, (int)$userId, $reviewId);

if (!empty($result['success'])) {
    echo json_encode([
        'success' => true,
        'action'  => $result['action'] ?? 'added',
        'count'   => (int)($result['count'] ?? 0),
    ]);
    exit;
}

http_response_code(422);
echo json_encode([
    'success' => false,
    'error' => $result['error'] ?? 'Could not update helpful vote.',
]);
