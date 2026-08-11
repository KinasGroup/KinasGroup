<?php
/**
 * KINAS GROUP — Product Reviews Detail Loader
 *
 * This file is included automatically near the bottom of each product
 * detail page by the review detail integration patcher.
 *
 * It detects the current division and listing ID, then renders the
 * approved customer review section with the review submission form.
 */

if (!function_exists('kinas_render_reviews_section')) {
    require_once __DIR__ . '/reviews.php';
}

// ------------------------------------------------------------
// Make sure we have a database connection
// ------------------------------------------------------------

if (!isset($db) || !($db instanceof PDO)) {
    if (!class_exists('Database', false)) {
        require_once __DIR__ . '/../api/config/database.php';
    }

    try {
        $db = Database::getInstance()->getConnection();
    } catch (Throwable $e) {
        $db = null;
    }
}

if (!$db) {
    return;
}

// ------------------------------------------------------------
// Detect listing type
// ------------------------------------------------------------

$kinasReviewListingType = null;

// Detail page can override this by setting $review_listing_type.
if (isset($review_listing_type)) {
    $kinasReviewListingType = kinas_normalize_review_listing_type((string)$review_listing_type);
}

// Some detail pages may already have a generic $listing_type variable.
if (!$kinasReviewListingType && isset($listing_type)) {
    $kinasReviewListingType = kinas_normalize_review_listing_type((string)$listing_type);
}

// Fallback for query-string driven pages.
if (!$kinasReviewListingType && isset($_GET['listing_type'])) {
    $kinasReviewListingType = kinas_normalize_review_listing_type((string)$_GET['listing_type']);
}

// Detect from the division folder.
if (!$kinasReviewListingType) {
    $kinasReviewScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

    if (strpos($kinasReviewScript, '/divisions/kinas-automobile/') !== false) {
        $kinasReviewListingType = 'car';
    } elseif (strpos($kinasReviewScript, '/divisions/williams-connect-home/') !== false) {
        $kinasReviewListingType = 'property';
    } elseif (strpos($kinasReviewScript, '/divisions/kinas-volt/') !== false) {
        $kinasReviewListingType = 'solar';
    } elseif (strpos($kinasReviewScript, '/divisions/kinas-marketplace/') !== false) {
        $kinasReviewListingType = 'marketplace';
    }
}

// ------------------------------------------------------------
// Detect listing ID
// ------------------------------------------------------------

$kinasReviewListingId = 0;

if (isset($review_listing_id)) {
    $kinasReviewListingId = (int)$review_listing_id;
} elseif (isset($id)) {
    $kinasReviewListingId = (int)$id;
} elseif (isset($listingId)) {
    $kinasReviewListingId = (int)$listingId;
} elseif (isset($listing['id'])) {
    $kinasReviewListingId = (int)$listing['id'];
} elseif (isset($car['id'])) {
    $kinasReviewListingId = (int)$car['id'];
} elseif (isset($item['id'])) {
    $kinasReviewListingId = (int)$item['id'];
} elseif (isset($property['id'])) {
    $kinasReviewListingId = (int)$property['id'];
} elseif (isset($system['id'])) {
    $kinasReviewListingId = (int)$system['id'];
} elseif (isset($_GET['id'])) {
    $kinasReviewListingId = (int)$_GET['id'];
}

// ------------------------------------------------------------
// Render reviews
// ------------------------------------------------------------

if ($kinasReviewListingType && $kinasReviewListingId > 0) {
    echo "\n\n<!-- KINAS PRODUCT REVIEWS -->\n";

    kinas_render_reviews_section(
        $db,
        $kinasReviewListingType,
        $kinasReviewListingId
    );
}
