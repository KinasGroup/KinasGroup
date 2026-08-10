<?php
/**
 * KINAS GROUP — Product Reviews List Endpoint
 *
 * GET /api/reviews/list.php
 *
 * Expected query parameters:
 * - listing_type  car | property | solar | marketplace
 * - listing_id    integer
 * - limit         optional, default 10, max 50
 * - offset        optional, default 0
 *
 * Returns approved reviews only, plus rating summary and the current
 * user's review permission state.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/reviews.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 1. Read and validate input
// ------------------------------------------------------------

$rawListingType = (string)($_GET['listing_type'] ?? '');
$listingId = (int)($_GET['listing_id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 10);
$offset = (int)($_GET['offset'] ?? 0);

$listingType = kinas_normalize_review_listing_type($rawListingType);

if (!$listingType) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid listing type.',
    ]);
    exit;
}

if ($listingId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid listing ID.',
    ]);
    exit;
}

$limit = max(1, min(50, $limit));
$offset = max(0, $offset);

// ------------------------------------------------------------
// 2. Database connection
// ------------------------------------------------------------

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('api/reviews/list.php database error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'The review system is temporarily unavailable. Please try again later.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 3. Confirm review system is installed
// ------------------------------------------------------------

if (!kinas_reviews_table_exists($db, 'product_reviews')) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'The review system is not installed.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 4. Confirm listing exists and is reviewable
// ------------------------------------------------------------

$listing = kinas_get_review_listing($db, $listingType, $listingId);

if (!$listing || !kinas_review_listing_is_reviewable($listing)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'This listing could not be found.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 5. Fetch approved reviews and rating summary
// ------------------------------------------------------------

$summary = kinas_get_review_summary($db, $listingType, $listingId);
$reviews = kinas_get_listing_reviews($db, $listingType, $listingId, $limit, $offset);

$formattedReviews = [];

foreach ($reviews as $review) {
    $createdAt = (string)($review['created_at'] ?? '');

    $formattedReviews[] = [
        'id' => (int)($review['id'] ?? 0),
        'rating' => (int)($review['rating'] ?? 0),
        'comment' => (string)($review['comment'] ?? ''),
        'verified_purchase' => !empty($review['verified_purchase']),
        'user_name' => $review['user_name'] ?? 'Customer',
        'created_at' => $createdAt,
        'created_at_formatted' => $createdAt !== ''
            ? date('M j, Y', strtotime($createdAt))
            : '',
    ];
}

// ------------------------------------------------------------
// 6. Current user review state
// ------------------------------------------------------------

$currentUserId = kinas_review_current_user_id();

$currentUser = [
    'logged_in' => (bool)$currentUserId,
    'review_status' => null,
    'can_review' => false,
    'reason' => '',
];

if ($currentUserId) {
    $currentUser['review_status'] = kinas_get_user_review_status(
        $db,
        (int)$currentUserId,
        $listingType,
        $listingId
    );

    $canReview = kinas_can_user_review(
        $db,
        (int)$currentUserId,
        $listingType,
        $listingId
    );

    $currentUser['can_review'] = !empty($canReview['allowed']);
    $currentUser['reason'] = $canReview['reason'] ?? '';
}

// ------------------------------------------------------------
// 7. Response
// ------------------------------------------------------------

echo json_encode([
    'success' => true,
    'listing_type' => $listingType,
    'listing_id' => $listingId,
    'summary' => [
        'count' => (int)($summary['count'] ?? 0),
        'average' => (float)($summary['average'] ?? 0),
        'distribution' => $summary['distribution'] ?? [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ],
    ],
    'reviews' => $formattedReviews,
    'pagination' => [
        'total' => (int)($summary['count'] ?? 0),
        'limit' => $limit,
        'offset' => $offset,
        'returned' => count($formattedReviews),
        'has_more' => ($offset + $limit) < (int)($summary['count'] ?? 0),
    ],
    'current_user' => $currentUser,
]);
