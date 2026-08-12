<?php
/**
 * KINAS GROUP — Product Reviews List Endpoint (ALIGNED)
 *
 * GET /api/reviews/list.php
 *
 * Expected query parameters:
 * - listing_type  car | property | solar | marketplace
 * - listing_id    integer
 * - limit         optional, default 10, max 50
 * - offset        optional, default 0
 * - sort          optional: recent | highest | lowest | helpful (default recent)
 *
 * Returns approved reviews only — now including each review's title,
 * photos, helpful count and whether the current user voted it helpful —
 * plus the rating summary and the current user's review permission state.
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
$sort = strtolower(trim((string)($_GET['sort'] ?? 'recent')));

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

if (!in_array($sort, ['recent', 'highest', 'lowest', 'helpful'], true)) {
    $sort = 'recent';
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
// 5. Fetch approved reviews (sorted) + rating summary
// ------------------------------------------------------------
$summary = kinas_get_review_summary($db, $listingType, $listingId);
$reviews = kinas_get_listing_reviews($db, $listingType, $listingId, $limit, $offset, $sort);

$reviewIds = array_map(function ($r) {
    return (int)($r['id'] ?? 0);
}, $reviews);

$photos = kinas_get_review_photos($db, $reviewIds);
$helpfulCounts = kinas_get_helpful_counts($db, $reviewIds);

$currentUserId = kinas_review_current_user_id();
$userVotedIds = kinas_get_user_helpful($db, $currentUserId, $reviewIds);

$formattedReviews = [];
foreach ($reviews as $review) {
    $reviewId = (int)($review['id'] ?? 0);
    $createdAt = (string)($review['created_at'] ?? '');

    $formattedReviews[] = [
        'id' => $reviewId,
        'rating' => (int)($review['rating'] ?? 0),
        'comment' => (string)($review['comment'] ?? ''),
        'title' => $review['title'] !== null ? (string)$review['title'] : null,
        'verified_purchase' => !empty($review['verified_purchase']),
        'user_name' => $review['user_name'] ?? 'Customer',
        'photos' => $photos[$reviewId] ?? [],
        'helpful_count' => (int)($helpfulCounts[$reviewId] ?? ($review['helpful_count'] ?? 0)),
        'user_voted' => in_array($reviewId, $userVotedIds, true),
        'created_at' => $createdAt,
        'created_at_formatted' => $createdAt !== ''
            ? date('M j, Y', strtotime($createdAt))
            : '',
    ];
}

// ------------------------------------------------------------
// 6. Current user review state
// ------------------------------------------------------------
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
    'sort' => $sort,
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
