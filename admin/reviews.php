<?php
/**
 * ADMIN — Product Reviews Moderation (ALIGNED)
 *
 * Aligned additions vs. the previous version:
 * - Shows each review's title and customer photos in the moderation table.
 * - Shows how many customers marked the review helpful.
 * - Emails the customer automatically when a review is approved or
 *   rejected (including rejections triggered from a report).
 *
 * Still allows admins to:
 * - approve/reject/delete product reviews
 * - manage reported reviews
 * - manually add / remove verified purchases for offline sales
 * - see matching paid order/transaction evidence where available
 *
 * Self-heals the required review moderation schema if columns or
 * supporting tables are missing.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

// Load the shared review engine if available.
$kinasReviewsEngine = __DIR__ . '/../includes/reviews.php';
if (file_exists($kinasReviewsEngine)) {
    require_once $kinasReviewsEngine;
}

// Load the notification layer so approval/rejection emails can be sent.
$kinasNotify = __DIR__ . '/../includes/notify.php';
if (file_exists($kinasNotify)) {
    require_once $kinasNotify;
}

// ============================================================
// FALLBACK REVIEW HELPERS
// ============================================================
if (!function_exists('kinas_reviews_table_exists')) {
    function kinas_reviews_table_exists(PDO $db, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            $cache[$table] = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}
if (!function_exists('kinas_reviews_table_columns')) {
    function kinas_reviews_table_columns(PDO $db, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $cache[$table] = [];
        try {
            $stmt = $db->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            $cache[$table] = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            $cache[$table] = [];
        }
        return $cache[$table];
    }
}
if (!function_exists('kinas_normalize_review_listing_type')) {
    function kinas_normalize_review_listing_type(?string $type): ?string
    {
        $type = strtolower(trim((string)$type));
        $map = [
            'car' => 'car', 'automobile' => 'car', 'vehicle' => 'car', 'vehicles' => 'car',
            'property' => 'property', 'real_estate' => 'property', 'realestate' => 'property',
            'home' => 'property', 'homes' => 'property',
            'solar' => 'solar', 'volt' => 'solar', 'energy' => 'solar',
            'marketplace' => 'marketplace', 'market' => 'marketplace', 'item' => 'marketplace', 'product' => 'marketplace',
        ];
        return $map[$type] ?? null;
    }
}
if (!function_exists('kinas_get_review_listing')) {
    function kinas_get_review_listing(PDO $db, string $type, int $listingId): ?array
    {
        $type = kinas_normalize_review_listing_type($type);
        if (!$type || $listingId <= 0) return null;
        $tables = [
            'car' => 'car_listings',
            'property' => 'property_listings',
            'solar' => 'solar_listings',
            'marketplace' => 'marketplace_listings',
        ];
        $table = $tables[$type] ?? null;
        if (!$table || !kinas_reviews_table_exists($db, $table)) return null;
        try {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$listing) return null;
            $listing['listing_type'] = $type;
            return $listing;
        } catch (Throwable $e) {
            return null;
        }
    }
}

SessionManager::requireAdmin();
$db = Database::getInstance()->getConnection();

// ============================================================
// SCHEMA SELF-HEALING
// ============================================================
function admin_reviews_add_column_if_missing(PDO $db, string $column, string $definition): void
{
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = 'product_reviews'
            AND column_name = ?
        ");
        $stmt->execute([$column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("ALTER TABLE product_reviews ADD COLUMN {$definition}");
        }
    } catch (Throwable $e) {
        // Ignore schema errors.
    }
}
function admin_reviews_ensure_schema(PDO $db): void
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS product_reviews (
                id                INT PRIMARY KEY AUTO_INCREMENT,
                user_id           INT NOT NULL,
                listing_type      ENUM('car','property','solar','marketplace') NOT NULL,
                listing_id        INT NOT NULL,
                rating            TINYINT(1) NOT NULL,
                title             VARCHAR(150) NULL,
                comment           TEXT NULL,
                status            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
                ip_address        VARCHAR(45) NULL,
                order_reference   VARCHAR(191) NULL,
                moderated_by      INT NULL,
                moderated_at      DATETIME NULL,
                moderation_note   VARCHAR(500) NULL,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_listing (user_id, listing_type, listing_id),
                INDEX idx_listing (listing_type, listing_id),
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_created (created_at),
                INDEX idx_verified_purchase (verified_purchase)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        // Table may already exist with a slightly different structure.
    }

    admin_reviews_add_column_if_missing($db, 'title', 'title VARCHAR(150) NULL');
    admin_reviews_add_column_if_missing($db, 'verified_purchase', 'verified_purchase TINYINT(1) NOT NULL DEFAULT 0');
    admin_reviews_add_column_if_missing($db, 'ip_address', 'ip_address VARCHAR(45) NULL');
    admin_reviews_add_column_if_missing($db, 'order_reference', 'order_reference VARCHAR(191) NULL');
    admin_reviews_add_column_if_missing($db, 'moderated_by', 'moderated_by INT NULL');
    admin_reviews_add_column_if_missing($db, 'moderated_at', 'moderated_at DATETIME NULL');
    admin_reviews_add_column_if_missing($db, 'moderation_note', 'moderation_note VARCHAR(500) NULL');

    try {
        $stmt = $db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = 'product_reviews'
            AND column_name = 'listing_type'
        ");
        $stmt->execute();
        $columnType = (string)$stmt->fetchColumn();
        if ($columnType !== '' && stripos($columnType, 'solar') === false) {
            $db->exec("
                ALTER TABLE product_reviews
                MODIFY COLUMN listing_type ENUM('car','property','solar','marketplace') NOT NULL
            ");
        }
    } catch (Throwable $e) {
        // Ignore.
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS product_review_reports (
                id          INT PRIMARY KEY AUTO_INCREMENT,
                review_id   INT NOT NULL,
                user_id     INT NULL,
                reason      VARCHAR(500) NOT NULL,
                status      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                UNIQUE KEY uniq_user_review_report (user_id, review_id),
                INDEX idx_report_review (review_id),
                INDEX idx_report_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        // Ignore.
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS verified_purchases (
                id              INT PRIMARY KEY AUTO_INCREMENT,
                user_id         INT NOT NULL,
                listing_type    ENUM('car','property','solar','marketplace') NOT NULL,
                listing_id      INT NOT NULL,
                source          ENUM('order','inspection','rental','manual') NOT NULL DEFAULT 'manual',
                order_reference VARCHAR(191) NULL,
                created_by      INT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_listing_source (user_id, listing_type, listing_id, source),
                INDEX idx_verified_listing (listing_type, listing_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        // Ignore.
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS product_review_photos (
                id          INT PRIMARY KEY AUTO_INCREMENT,
                review_id   INT NOT NULL,
                url         VARCHAR(500) NOT NULL,
                sort_order  TINYINT NOT NULL DEFAULT 0,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_prp_review (review_id),
                CONSTRAINT fk_prp_review FOREIGN KEY (review_id)
                    REFERENCES product_reviews(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        // Ignore.
    }
}
admin_reviews_ensure_schema($db);

$reviewsInstalled  = kinas_reviews_table_exists($db, 'product_reviews');
$reportsInstalled  = kinas_reviews_table_exists($db, 'product_review_reports');
$verifiedInstalled = kinas_reviews_table_exists($db, 'verified_purchases');

// ============================================================
// HELPERS
// ============================================================
function admin_reviews_redirect(string $tab, string $type, string $message): void
{
    $flashKey = $type === 'success' ? 'reviews_success' : 'reviews_error';
    SessionManager::setFlash($flashKey, $message);
    header('Location: reviews.php?tab=' . urlencode($tab));
    exit;
}
function admin_reviews_success_redirect(string $tab, string $message): void
{
    unset($_SESSION['csrf_token']);
    Security::generateCSRFToken();
    admin_reviews_redirect($tab, 'success', $message);
}
function admin_reviews_listing_url(string $type, int $listingId): string
{
    $map = [
        'car' => '/divisions/kinas-automobile/detail.php?id=%d',
        'property' => '/divisions/williams-connect-home/detail.php?id=%d',
        'solar' => '/divisions/kinas-volt/detail.php?id=%d',
        'marketplace' => '/divisions/kinas-marketplace/detail.php?id=%d',
    ];
    $pattern = $map[$type] ?? '/search.php';
    return sprintf($pattern, $listingId);
}
function admin_reviews_listing_title(PDO $db, string $type, int $listingId): string
{
    $tables = [
        'car' => 'car_listings',
        'property' => 'property_listings',
        'solar' => 'solar_listings',
        'marketplace' => 'marketplace_listings',
    ];
    $table = $tables[$type] ?? null;
    if (!$table) return 'Listing #' . $listingId;
    try {
        $stmt = $db->prepare("SELECT title FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$listingId]);
        $title = $stmt->fetchColumn();
        if ($title) return (string)$title;
    } catch (Throwable $e) {
        // Ignore and fall back.
    }
    return ucfirst($type) . ' listing #' . $listingId;
}
function admin_reviews_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '<span class="kr-admin-stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
    }
    $html .= '</span>';
    return $html;
}
function admin_reviews_order_match(PDO $db, array $review): array
{
    $info = ['reference' => null, 'order_id' => null, 'paid_at' => null, 'source' => null];
    $userId = (int)($review['user_id'] ?? 0);
    $listingId = (int)($review['listing_id'] ?? 0);
    $listingType = (string)($review['listing_type'] ?? '');
    if (!$userId || !$listingId) return $info;

    try {
        $stmt = $db->prepare("
            SELECT order_id, paystack_reference, paid_at
            FROM transactions
            WHERE buyer_id = ? AND listing_id = ? AND listing_type = ? AND status = 'paid'
            ORDER BY paid_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $listingId, $listingType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'reference' => $row['paystack_reference'] ?? null,
                'order_id' => $row['order_id'] ?? null,
                'paid_at' => $row['paid_at'] ?? null,
                'source' => 'transactions',
            ];
        }
    } catch (Throwable $e) {
        // Continue.
    }

    if ($listingType === 'marketplace') {
        try {
            $stmt = $db->prepare("
                SELECT o.id AS order_id, o.reference AS paystack_reference, o.paid_at
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE o.buyer_id = ? AND oi.listing_id = ? AND o.status = 'paid'
                ORDER BY o.paid_at DESC LIMIT 1
            ");
            $stmt->execute([$userId, $listingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'reference' => $row['paystack_reference'] ?? null,
                    'order_id' => $row['order_id'] ?? null,
                    'paid_at' => $row['paid_at'] ?? null,
                    'source' => 'orders',
                ];
            }
        } catch (Throwable $e) {
            // Continue.
        }
    }
    return $info;
}

// ============================================================
// PROCESS POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$reviewsInstalled) {
        admin_reviews_redirect('reviews', 'error', 'The review system is not installed.');
    }
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!Security::verifyCSRFToken($csrfToken)) {
        admin_reviews_redirect('reviews', 'error', 'Invalid security token. Please try again.');
    }
    $adminAction = (string)($_POST['admin_action'] ?? '');
    $adminId = (int)SessionManager::getUserId();

    try {
        // ----------------------------------------------------
        // Moderate review: approve / reject / delete
        // ----------------------------------------------------
        if ($adminAction === 'moderate_review') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $moderationAction = (string)($_POST['moderation_action'] ?? '');
            $moderationNote = trim((string)($_POST['moderation_note'] ?? ''));
            if (!$reviewId || !in_array($moderationAction, ['approve', 'reject', 'delete'], true)) {
                admin_reviews_redirect('reviews', 'error', 'Invalid review moderation action.');
            }

            $stmt = $db->prepare("
                SELECT id, user_id, listing_type, listing_id, status
                FROM product_reviews
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$reviewId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$review) {
                admin_reviews_redirect('reviews', 'error', 'Review not found.');
            }

            if ($moderationAction === 'delete') {
                if ($reportsInstalled) {
                    $db->prepare("DELETE FROM product_review_reports WHERE review_id = ?")->execute([$reviewId]);
                }
                $db->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$reviewId]);
                Security::logActivity(
                    $adminId,
                    'product_review_deleted',
                    sprintf('Deleted review #%d for %s listing #%d', $reviewId, $review['listing_type'], $review['listing_id'])
                );
                admin_reviews_success_redirect('reviews', 'Review deleted permanently.');
            }

            $newStatus = $moderationAction === 'approve' ? 'approved' : 'rejected';
            $reviewColumns = kinas_reviews_table_columns($db, 'product_reviews');
            $sql = "UPDATE product_reviews SET status = ?";
            $params = [$newStatus];
            if (in_array('moderated_by', $reviewColumns, true)) {
                $sql .= ", moderated_by = ?";
                $params[] = $adminId;
            }
            if (in_array('moderated_at', $reviewColumns, true)) {
                $sql .= ", moderated_at = NOW()";
            }
            if (in_array('moderation_note', $reviewColumns, true)) {
                $sql .= ", moderation_note = ?";
                $params[] = $moderationNote !== '' ? $moderationNote : null;
            }
            $sql .= " WHERE id = ?";
            $params[] = $reviewId;
            $db->prepare($sql)->execute($params);

            Security::logActivity(
                $adminId,
                'product_review_moderated',
                sprintf('%s review #%d for %s listing #%d', ucfirst($newStatus), $reviewId, $review['listing_type'], $review['listing_id'])
            );

            // ALIGNED: notify the customer about the decision.
            if (function_exists('kinas_notify_review_decision')) {
                kinas_notify_review_decision($db, $review, $newStatus);
            }

            admin_reviews_success_redirect(
                'reviews',
                $newStatus === 'approved'
                    ? 'Review approved and now visible on the product page. The customer has been notified.'
                    : 'Review rejected and hidden from the product page. The customer has been notified.'
            );
        }

        // ----------------------------------------------------
        // Handle report: dismiss / resolve / reject linked review
        // ----------------------------------------------------
        if ($adminAction === 'resolve_report') {
            if (!$reportsInstalled) {
                admin_reviews_redirect('reports', 'error', 'Review reports are not installed.');
            }
            $reportId = (int)($_POST['report_id'] ?? 0);
            $reportAction = (string)($_POST['report_action'] ?? '');
            $moderationNote = trim((string)($_POST['moderation_note'] ?? ''));
            if (!$reportId || !in_array($reportAction, ['dismiss', 'resolve', 'reject_review'], true)) {
                admin_reviews_redirect('reports', 'error', 'Invalid report action.');
            }

            $stmt = $db->prepare("
                SELECT rr.id, rr.review_id, r.status AS review_status
                FROM product_review_reports rr
                LEFT JOIN product_reviews r ON r.id = rr.review_id
                WHERE rr.id = ?
                LIMIT 1
            ");
            $stmt->execute([$reportId]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$report) {
                admin_reviews_redirect('reports', 'error', 'Report not found.');
            }
            $linkedReviewId = (int)($report['review_id'] ?? 0);

            if ($reportAction === 'reject_review' && $linkedReviewId > 0) {
                $linkedReview = null;
                $lrStmt = $db->prepare("SELECT id, user_id, listing_type, listing_id FROM product_reviews WHERE id = ? LIMIT 1");
                $lrStmt->execute([$linkedReviewId]);
                $linkedReview = $lrStmt->fetch(PDO::FETCH_ASSOC);

                $reviewColumns = kinas_reviews_table_columns($db, 'product_reviews');
                $sql = "UPDATE product_reviews SET status = 'rejected'";
                $params = [];
                if (in_array('moderated_by', $reviewColumns, true)) {
                    $sql .= ", moderated_by = ?";
                    $params[] = $adminId;
                }
                if (in_array('moderated_at', $reviewColumns, true)) {
                    $sql .= ", moderated_at = NOW()";
                }
                if (in_array('moderation_note', $reviewColumns, true)) {
                    $sql .= ", moderation_note = ?";
                    $params[] = $moderationNote !== '' ? $moderationNote : 'Rejected after customer report.';
                }
                $sql .= " WHERE id = ?";
                $params[] = $linkedReviewId;
                $db->prepare($sql)->execute($params);

                Security::logActivity(
                    $adminId,
                    'product_review_rejected_via_report',
                    sprintf('Rejected review #%d after report #%d', $linkedReviewId, $reportId)
                );

                // ALIGNED: notify the customer their review was rejected.
                if ($linkedReview && function_exists('kinas_notify_review_decision')) {
                    kinas_notify_review_decision($db, $linkedReview, 'rejected');
                }
            }

            $newReportStatus = $reportAction === 'dismiss' ? 'dismissed' : 'resolved';
            $db->prepare("
                UPDATE product_review_reports
                SET status = ?, resolved_at = NOW()
                WHERE id = ?
            ")->execute([$newReportStatus, $reportId]);

            Security::logActivity(
                $adminId,
                'product_review_report_' . $newReportStatus,
                sprintf('Report #%d marked as %s', $reportId, $newReportStatus)
            );
            admin_reviews_success_redirect('reports', 'Report updated successfully.');
        }

        // ----------------------------------------------------
        // Add manual verified purchase
        // ----------------------------------------------------
        if ($adminAction === 'add_verified_purchase') {
            if (!$verifiedInstalled) {
                admin_reviews_redirect('verified', 'error', 'Verified purchases are not installed.');
            }
            $userIdInput = (int)($_POST['user_id'] ?? 0);
            $userEmail = trim((string)($_POST['user_email'] ?? ''));
            $listingType = kinas_normalize_review_listing_type((string)($_POST['listing_type'] ?? ''));
            $listingId = (int)($_POST['listing_id'] ?? 0);
            $orderReference = trim((string)($_POST['order_reference'] ?? ''));
            if (!$listingType || $listingId <= 0) {
                admin_reviews_redirect('verified', 'error', 'Please provide a valid listing type and listing ID.');
            }
            $listing = kinas_get_review_listing($db, $listingType, $listingId);
            if (!$listing) {
                admin_reviews_redirect('verified', 'error', 'Listing not found. Please check the listing ID.');
            }
            $verifiedUserId = 0;
            if ($userIdInput > 0) {
                $stmt = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userIdInput]);
                $verifiedUserId = (int)$stmt->fetchColumn();
            } elseif ($userEmail !== '') {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$userEmail]);
                $verifiedUserId = (int)$stmt->fetchColumn();
            }
            if ($verifiedUserId <= 0) {
                admin_reviews_redirect('verified', 'error', 'Customer not found. Provide a valid user ID or account email.');
            }
            try {
                $db->prepare("
                    INSERT INTO verified_purchases
                    (user_id, listing_type, listing_id, source, order_reference, created_by, created_at)
                    VALUES (?, ?, ?, 'manual', ?, ?, NOW())
                ")->execute([
                    $verifiedUserId,
                    $listingType,
                    $listingId,
                    $orderReference !== '' ? $orderReference : null,
                    $adminId,
                ]);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    admin_reviews_redirect('verified', 'error', 'This customer is already marked as a verified purchaser for that listing.');
                }
                throw $e;
            }
            Security::logActivity(
                $adminId,
                'verified_purchase_added',
                sprintf('Manually verified user #%d for %s listing #%d', $verifiedUserId, $listingType, $listingId)
            );
            admin_reviews_success_redirect('verified', 'Verified purchase added successfully.');
        }

        // ----------------------------------------------------
        // Remove manual verified purchase
        // ----------------------------------------------------
        if ($adminAction === 'remove_verified_purchase') {
            if (!$verifiedInstalled) {
                admin_reviews_redirect('verified', 'error', 'Verified purchases are not installed.');
            }
            $purchaseId = (int)($_POST['purchase_id'] ?? 0);
            if (!$purchaseId) {
                admin_reviews_redirect('verified', 'error', 'Invalid verified purchase record.');
            }
            $db->prepare("DELETE FROM verified_purchases WHERE id = ?")->execute([$purchaseId]);
            Security::logActivity(
                $adminId,
                'verified_purchase_removed',
                sprintf('Removed verified purchase record #%d', $purchaseId)
            );
            admin_reviews_success_redirect('verified', 'Verified purchase removed.');
        }

        admin_reviews_redirect('reviews', 'error', 'Unknown admin action.');
    } catch (Throwable $e) {
        error_log('admin/reviews.php error: ' . $e->getMessage());
        admin_reviews_redirect('reviews', 'error', 'Something went wrong while processing that action.');
    }
}

// ============================================================
// GET DATA
// ============================================================
$tab = (string)($_GET['tab'] ?? 'reviews');
if (!in_array($tab, ['reviews', 'reports', 'verified'], true)) {
    $tab = 'reviews';
}
$csrfToken = Security::generateCSRFToken();
$flashSuccess = SessionManager::getFlash('reviews_success');
$flashError = SessionManager::getFlash('reviews_error');

// Stats
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'open_reports' => 0];
if ($reviewsInstalled) {
    try {
        $stmt = $db->query("SELECT status, COUNT(*) AS total FROM product_reviews GROUP BY status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($stats[$row['status']])) $stats[$row['status']] = (int)$row['total'];
        }
    } catch (Throwable $e) {
        // Ignore stats failure.
    }
}
if ($reportsInstalled) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM product_review_reports WHERE status = 'open'");
        $stats['open_reports'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        // Ignore.
    }
}

// Reviews tab
$statusFilter = (string)($_GET['status'] ?? 'pending');
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) $statusFilter = 'pending';
$typeFilter = (string)($_GET['type'] ?? 'all');
if (!in_array($typeFilter, ['all', 'car', 'property', 'solar', 'marketplace'], true)) $typeFilter = 'all';
$searchQuery = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$reviews = [];
$totalReviews = 0;
$totalPages = 1;
$photosByReview = [];
$helpfulByReview = [];

if ($reviewsInstalled) {
    $where = [];
    $params = [];
    if ($statusFilter !== 'all') {
        $where[] = 'r.status = ?';
        $params[] = $statusFilter;
    }
    if ($typeFilter !== 'all') {
        $where[] = 'r.listing_type = ?';
        $params[] = $typeFilter;
    }
    if ($searchQuery !== '') {
        $where[] = '(r.comment LIKE ? OR r.title LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR r.listing_id = ?)';
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = (int)$searchQuery;
    }
    $whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';
    try {
        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM product_reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalReviews = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalReviews / $perPage));

        $stmt = $db->prepare("
            SELECT r.*, u.name AS user_name, u.email AS user_email
            FROM product_reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE {$whereSql}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ALIGNED: load photos + helpful counts for the visible reviews.
        if (!empty($reviews)) {
            $reviewIds = array_map(function ($r) {
                return (int)$r['id'];
            }, $reviews);
            if (function_exists('kinas_get_review_photos')) {
                $photosByReview = kinas_get_review_photos($db, $reviewIds);
            }
            if (function_exists('kinas_get_helpful_counts')) {
                $helpfulByReview = kinas_get_helpful_counts($db, $reviewIds);
            }
        }
    } catch (Throwable $e) {
        error_log('admin/reviews.php review list error: ' . $e->getMessage());
    }
}

// Reports tab
$reportStatusFilter = (string)($_GET['report_status'] ?? 'open');
if (!in_array($reportStatusFilter, ['open', 'resolved', 'dismissed', 'all'], true)) $reportStatusFilter = 'open';
$reports = [];
if ($reportsInstalled) {
    try {
        $reportWhere = '1=1';
        $reportParams = [];
        if ($reportStatusFilter !== 'all') {
            $reportWhere = 'rr.status = ?';
            $reportParams[] = $reportStatusFilter;
        }
        $stmt = $db->prepare("
            SELECT rr.*, r.listing_type, r.listing_id, r.status AS review_status, u.name AS reporter_name
            FROM product_review_reports rr
            LEFT JOIN product_reviews r ON r.id = rr.review_id
            LEFT JOIN users u ON u.id = rr.user_id
            WHERE {$reportWhere}
            ORDER BY rr.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($reportParams);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('admin/reviews.php report list error: ' . $e->getMessage());
    }
}

// Verified purchases tab
$verifiedPurchases = [];
if ($verifiedInstalled) {
    try {
        $stmt = $db->query("
            SELECT vp.*, u.name AS user_name, u.email AS user_email, c.name AS creator_name
            FROM verified_purchases vp
            LEFT JOIN users u ON u.id = vp.user_id
            LEFT JOIN users c ON c.id = vp.created_by
            ORDER BY vp.created_at DESC
            LIMIT 50
        ");
        $verifiedPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('admin/reviews.php verified purchase list error: ' . $e->getMessage());
    }
}

$pageTitle = 'Product Reviews — Admin';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.kr-admin-wrap { max-width: 100%; overflow-x: hidden; }
.kr-admin-header { margin-bottom: 20px; }
.kr-admin-header h1 { font-family: 'Prata', serif; font-size: 26px; color: #0A0A0A; margin: 0; }
.kr-admin-header p { color: #666; font-size: 14px; margin-top: 6px; }
.kr-flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
.kr-flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #A5D6A7; }
.kr-flash.error { background: #FFEBEE; color: #C62828; border: 1px solid #EF9A9A; }
.kr-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.kr-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 30px; background: #fff; border: 1px solid #E0E0E0; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; }
.kr-tabs a.active { background: #C6A43F; border-color: #C6A43F; color: #0A0A0A; }
.kr-count-pill { background: rgba(0,0,0,0.08); border-radius: 20px; padding: 2px 8px; font-size: 11px; }
.kr-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; overflow: hidden; margin-bottom: 20px; }
.kr-card-pad { padding: 18px; }
.kr-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.kr-filters input, .kr-filters select { padding: 10px 12px; border: 1px solid #DDD; border-radius: 8px; font-size: 13px; background: #fff; }
.kr-btn { border: none; border-radius: 30px; padding: 10px 18px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.kr-btn.gold { background: #C6A43F; color: #0A0A0A; }
.kr-btn.secondary { background: #F1F1F1; color: #333; }
.kr-btn-sm { border: none; border-radius: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer; margin-right: 6px; margin-bottom: 6px; }
.kr-btn-sm.success { background: #E8F5E9; color: #2E7D32; }
.kr-btn-sm.danger { background: #FFEBEE; color: #C62828; }
.kr-btn-sm.dark { background: #222; color: #fff; }
.kr-table-wrap { overflow-x: auto; }
table.kr-table { width: 100%; min-width: 1100px; border-collapse: collapse; }
table.kr-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #888; padding: 12px 14px; border-bottom: 1px solid #E0E0E0; background: #FAFAFA; }
table.kr-table td { padding: 14px; border-bottom: 1px solid #F0F0F0; font-size: 13px; vertical-align: top; }
.kr-admin-stars { color: #C6A43F; white-space: nowrap; }
.kr-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
.kr-status.pending { background: #FFF8E1; color: #8D6E00; }
.kr-status.approved { background: #E8F5E9; color: #2E7D32; }
.kr-status.rejected { background: #FFEBEE; color: #C62828; }
.kr-status.open { background: #FFF8E1; color: #8D6E00; }
.kr-status.resolved { background: #E8F5E9; color: #2E7D32; }
.kr-status.dismissed { background: #ECEFF1; color: #546E7A; }
.kr-badge { display: inline-flex; align-items: center; gap: 5px; background: #E8F5E9; color: #2E7D32; border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 800; }
.kr-muted { color: #888; font-size: 12px; }
.kr-inline-form { display: inline-block; }
.kr-comment { max-width: 380px; color: #444; line-height: 1.5; }
/* ALIGNED: review title + photos */
.kr-review-title { font-weight: 700; color: #0A0A0A; margin-bottom: 4px; }
.kr-photos { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
.kr-photos a { display: block; }
.kr-photos img { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #E0E0E0; }
.kr-note { margin-top: 8px; font-size: 12px; color: #8D6E00; background: #FFF8E1; border-radius: 8px; padding: 6px 8px; }
.kr-empty { padding: 50px 20px; text-align: center; color: #999; }
.kr-pagination { display: flex; gap: 10px; align-items: center; padding: 14px 18px; border-top: 1px solid #F0F0F0; flex-wrap: wrap; }
.kr-verified-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
.kr-verified-form label { display: block; font-size: 12px; font-weight: 700; color: #555; margin-bottom: 6px; }
.kr-verified-form input, .kr-verified-form select { width: 100%; padding: 10px 12px; border: 1px solid #DDD; border-radius: 8px; font-size: 13px; box-sizing: border-box; background: #fff; }
.ref-code { font-family: monospace; font-size: 11px; color: #999; }
@media (prefers-color-scheme: dark) {
    .kr-admin-header h1, .kr-admin-header p, .kr-card, table.kr-table th, table.kr-table td,
    .kr-filters input, .kr-filters select, .kr-verified-form input, .kr-verified-form select {
        background: #fff !important; color: #111 !important;
    }
}
</style>
<div class="je-dash-shell kr-admin-wrap">
<?php include __DIR__ . '/../includes/partials/admin-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
<div class="kr-admin-header">
    <h1><i class="fas fa-star" style="color:#C6A43F;margin-right:10px;"></i>Product Reviews</h1>
    <p>Moderate customer product reviews, manage reported reviews, and control verified purchase access.</p>
</div>

<?php if (!empty($flashSuccess)): ?>
<div class="kr-flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
<div class="kr-flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<?php if (!$reviewsInstalled): ?>
<div class="kr-flash error">The <strong>product_reviews</strong> table is not installed. Run the review system database migration first.</div>
<?php endif; ?>

<div class="kr-tabs">
    <a href="?tab=reviews&status=<?= htmlspecialchars($statusFilter) ?>&type=<?= htmlspecialchars($typeFilter) ?>&q=<?= urlencode($searchQuery) ?>" class="<?= $tab === 'reviews' ? 'active' : '' ?>">
        <i class="fas fa-comments"></i> Reviews <span class="kr-count-pill"><?= (int)($stats['pending'] + $stats['approved'] + $stats['rejected']) ?></span>
    </a>
    <a href="?tab=reports" class="<?= $tab === 'reports' ? 'active' : '' ?>">
        <i class="fas fa-flag"></i> Reports <span class="kr-count-pill"><?= (int)$stats['open_reports'] ?></span>
    </a>
    <a href="?tab=verified" class="<?= $tab === 'verified' ? 'active' : '' ?>">
        <i class="fas fa-badge-check"></i> Verified Purchases
    </a>
</div>

<?php if ($tab === 'reviews'): ?>
<div class="kr-card">
    <div class="kr-card-pad">
        <form method="GET" class="kr-filters">
            <input type="hidden" name="tab" value="reviews">
            <select name="status">
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <select name="type">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="car" <?= $typeFilter === 'car' ? 'selected' : '' ?>>Car</option>
                <option value="property" <?= $typeFilter === 'property' ? 'selected' : '' ?>>Property</option>
                <option value="solar" <?= $typeFilter === 'solar' ? 'selected' : '' ?>>Solar</option>
                <option value="marketplace" <?= $typeFilter === 'marketplace' ? 'selected' : '' ?>>Marketplace</option>
            </select>
            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search comment, title, customer, email, listing ID">
            <button type="submit" class="kr-btn gold"><i class="fas fa-filter"></i> Filter</button>
            <a href="?tab=reviews" class="kr-btn secondary">Reset</a>
        </form>
    </div>
    <?php if (empty($reviews)): ?>
    <div class="kr-empty"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No reviews found for this filter.</div>
    <?php else: ?>
    <div class="kr-table-wrap">
        <table class="kr-table">
            <thead>
            <tr>
                <th>ID</th><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th>
                <th>Order Match</th><th>Status</th><th>Submitted</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $review): ?>
            <?php
                $reviewId = (int)$review['id'];
                $listingType = (string)($review['listing_type'] ?? '');
                $listingId = (int)($review['listing_id'] ?? 0);
                $listingUrl = admin_reviews_listing_url($listingType, $listingId);
                $listingTitle = admin_reviews_listing_title($db, $listingType, $listingId);
                $orderMatch = admin_reviews_order_match($db, $review);
                $commentText = trim((string)($review['comment'] ?? ''));
                $titleText = trim((string)($review['title'] ?? ''));
                if (function_exists('mb_substr')) {
                    $commentExcerpt = mb_substr($commentText, 0, 180);
                    $commentLong = function_exists('mb_strlen') && mb_strlen($commentText) > 180;
                } else {
                    $commentExcerpt = substr($commentText, 0, 180);
                    $commentLong = strlen($commentText) > 180;
                }
                if ($commentLong) $commentExcerpt .= '…';
                $rowPhotos = $photosByReview[$reviewId] ?? [];
                $helpfulCount = (int)($helpfulByReview[$reviewId] ?? 0);
            ?>
            <tr>
                <td>#<?= $reviewId ?></td>
                <td>
                    <a href="<?= htmlspecialchars($listingUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($listingTitle) ?></a>
                    <div class="kr-muted"><?= htmlspecialchars(ucfirst($listingType)) ?> #<?= $listingId ?></div>
                </td>
                <td>
                    <strong><?= htmlspecialchars($review['user_name'] ?? 'Unknown') ?></strong>
                    <div class="kr-muted"><?= htmlspecialchars($review['user_email'] ?? '') ?></div>
                    <?php if (!empty($review['verified_purchase'])): ?>
                    <div style="margin-top:6px;"><span class="kr-badge"><i class="fas fa-badge-check"></i> Verified</span></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?= admin_reviews_stars((int)($review['rating'] ?? 0)) ?>
                    <div class="kr-muted"><?= (int)($review['rating'] ?? 0) ?>/5</div>
                </td>
                <td>
                    <?php if ($titleText !== ''): ?>
                    <div class="kr-review-title"><?= htmlspecialchars($titleText) ?></div>
                    <?php endif; ?>
                    <div class="kr-comment"><?= nl2br(htmlspecialchars($commentExcerpt)) ?></div>
                    <?php if (!empty($rowPhotos)): ?>
                    <div class="kr-photos">
                        <?php foreach ($rowPhotos as $photoUrl): ?>
                        <a href="<?= htmlspecialchars($photoUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Review photo">
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($helpfulCount > 0): ?>
                    <div class="kr-muted" style="margin-top:6px;"><i class="far fa-thumbs-up"></i> <?= $helpfulCount ?> found this helpful</div>
                    <?php endif; ?>
                    <?php if (!empty($review['moderation_note'])): ?>
                    <div class="kr-note"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars((string)$review['moderation_note']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($orderMatch['reference'])): ?>
                    <span class="ref-code"><?= htmlspecialchars((string)$orderMatch['reference']) ?></span><br>
                    <span style="font-size:11px;color:#2E7D32;"><i class="fas fa-check-circle"></i> Paid order found</span>
                    <?php if (!empty($orderMatch['paid_at'])): ?>
                    <br><span style="font-size:11px;color:#999;"><?= date('M j, Y', strtotime((string)$orderMatch['paid_at'])) ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="kr-muted">No direct order match</span>
                    <?php endif; ?>
                </td>
                <td><span class="kr-status <?= htmlspecialchars((string)$review['status']) ?>"><?= htmlspecialchars(ucfirst((string)$review['status'])) ?></span></td>
                <td><?= date('M j, Y H:i', strtotime((string)($review['created_at'] ?? 'now'))) ?></td>
                <td>
                    <?php if (($review['status'] ?? '') !== 'approved'): ?>
                    <form method="POST" class="kr-inline-form" data-confirm="Approve this review? The customer will be notified.">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="moderate_review">
                        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
                        <input type="hidden" name="moderation_action" value="approve">
                        <button type="submit" class="kr-btn-sm success"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <?php endif; ?>
                    <?php if (($review['status'] ?? '') !== 'rejected'): ?>
                    <form method="POST" class="kr-inline-form" data-confirm="Reject this review? The customer will be notified." data-prompt="Optional moderation note:">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="moderate_review">
                        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
                        <input type="hidden" name="moderation_action" value="reject">
                        <input type="hidden" name="moderation_note" value="">
                        <button type="submit" class="kr-btn-sm danger"><i class="fas fa-times"></i> Reject</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="kr-inline-form" data-confirm="Delete this review permanently? This cannot be undone.">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="moderate_review">
                        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
                        <input type="hidden" name="moderation_action" value="delete">
                        <button type="submit" class="kr-btn-sm dark"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="kr-pagination">
        <?php $paginationParams = $_GET; $paginationParams['tab'] = 'reviews'; ?>
        <?php if ($page > 1): $paginationParams['page'] = $page - 1; ?>
        <a class="kr-btn secondary" href="reviews.php?<?= htmlspecialchars(http_build_query($paginationParams)) ?>"><i class="fas fa-arrow-left"></i> Previous</a>
        <?php endif; ?>
        <span style="font-size:13px;color:#666;">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
        <?php if ($page < $totalPages): $paginationParams['page'] = $page + 1; ?>
        <a class="kr-btn secondary" href="reviews.php?<?= htmlspecialchars(http_build_query($paginationParams)) ?>">Next <i class="fas fa-arrow-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'reports'): ?>
<div class="kr-card">
    <div class="kr-card-pad">
        <form method="GET" class="kr-filters">
            <input type="hidden" name="tab" value="reports">
            <select name="report_status">
                <option value="open" <?= $reportStatusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="resolved" <?= $reportStatusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="dismissed" <?= $reportStatusFilter === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
                <option value="all" <?= $reportStatusFilter === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <button type="submit" class="kr-btn gold"><i class="fas fa-filter"></i> Filter</button>
            <a href="?tab=reports" class="kr-btn secondary">Reset</a>
        </form>
    </div>
    <?php if (!$reportsInstalled): ?>
    <div class="kr-empty">Review reports are not installed. Run the review system database migration first.</div>
    <?php elseif (empty($reports)): ?>
    <div class="kr-empty"><i class="fas fa-flag" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No reports found.</div>
    <?php else: ?>
    <div class="kr-table-wrap">
        <table class="kr-table">
            <thead>
            <tr><th>Report</th><th>Review</th><th>Product</th><th>Reported By</th><th>Reason</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report): ?>
            <?php
                $reportId = (int)$report['id'];
                $linkedReviewId = (int)($report['review_id'] ?? 0);
                $reportListingType = (string)($report['listing_type'] ?? '');
                $reportListingId = (int)($report['listing_id'] ?? 0);
                $reportListingUrl = $reportListingType && $reportListingId ? admin_reviews_listing_url($reportListingType, $reportListingId) : '';
            ?>
            <tr>
                <td>#<?= $reportId ?><div class="kr-muted"><?= date('M j, Y H:i', strtotime((string)($report['created_at'] ?? 'now'))) ?></div></td>
                <td>
                    <?php if ($linkedReviewId > 0): ?>
                    <a href="?tab=reviews&status=all&q=<?= $linkedReviewId ?>">Review #<?= $linkedReviewId ?></a>
                    <div class="kr-muted">Current status: <?= htmlspecialchars(ucfirst((string)($report['review_status'] ?? 'unknown'))) ?></div>
                    <?php else: ?>
                    <span class="kr-muted">Deleted review</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($reportListingUrl): ?>
                    <a href="<?= htmlspecialchars($reportListingUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(admin_reviews_listing_title($db, $reportListingType, $reportListingId)) ?></a>
                    <div class="kr-muted"><?= htmlspecialchars(ucfirst($reportListingType)) ?> #<?= $reportListingId ?></div>
                    <?php else: ?>
                    <span class="kr-muted">Unknown</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($report['reporter_name'] ?? 'Unknown') ?></td>
                <td><div class="kr-comment"><?= nl2br(htmlspecialchars((string)($report['reason'] ?? ''))) ?></div></td>
                <td><span class="kr-status <?= htmlspecialchars((string)($report['status'] ?? 'open')) ?>"><?= htmlspecialchars(ucfirst((string)($report['status'] ?? 'open'))) ?></span></td>
                <td>
                    <?php if (($report['status'] ?? '') === 'open'): ?>
                    <?php if ($linkedReviewId > 0 && ($report['review_status'] ?? '') !== 'rejected'): ?>
                    <form method="POST" class="kr-inline-form" data-confirm="Reject the linked review? The customer will be notified." data-prompt="Optional moderation note:">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="resolve_report">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <input type="hidden" name="report_action" value="reject_review">
                        <input type="hidden" name="moderation_note" value="">
                        <button type="submit" class="kr-btn-sm danger"><i class="fas fa-ban"></i> Reject Review</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="kr-inline-form" data-confirm="Mark this report as resolved?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="resolve_report">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <input type="hidden" name="report_action" value="resolve">
                        <button type="submit" class="kr-btn-sm success"><i class="fas fa-check"></i> Resolve</button>
                    </form>
                    <form method="POST" class="kr-inline-form" data-confirm="Dismiss this report?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="resolve_report">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <input type="hidden" name="report_action" value="dismiss">
                        <button type="submit" class="kr-btn-sm dark"><i class="fas fa-times"></i> Dismiss</button>
                    </form>
                    <?php else: ?>
                    <span class="kr-muted">Closed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'verified'): ?>
<div class="kr-card">
    <div class="kr-card-pad">
        <h3 style="margin:0 0 12px;"><i class="fas fa-plus-circle" style="color:#C6A43F;"></i> Add Manual Verified Purchase</h3>
        <p class="kr-muted" style="margin-top:0;">Use this for offline sales, direct bank transfers, completed inspections, or completed rentals where the platform did not automatically record a purchase.</p>
        <?php if (!$verifiedInstalled): ?>
        <div class="kr-flash error">Verified purchases are not installed. Run the review system database migration first.</div>
        <?php else: ?>
        <form method="POST" class="kr-verified-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="admin_action" value="add_verified_purchase">
            <div><label>Customer Email</label><input type="email" name="user_email" placeholder="customer@email.com"></div>
            <div><label>Or Customer User ID</label><input type="number" name="user_id" min="1" placeholder="123"></div>
            <div>
                <label>Listing Type</label>
                <select name="listing_type" required>
                    <option value="car">Car</option><option value="property">Property</option>
                    <option value="solar">Solar</option><option value="marketplace">Marketplace</option>
                </select>
            </div>
            <div><label>Listing ID</label><input type="number" name="listing_id" min="1" required placeholder="45"></div>
            <div><label>Reference / Notes</label><input type="text" name="order_reference" placeholder="Offline sale, inspection, rental, etc."></div>
            <div><button type="submit" class="kr-btn gold"><i class="fas fa-badge-check"></i> Add Verified Purchase</button></div>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($verifiedInstalled): ?>
    <?php if (empty($verifiedPurchases)): ?>
    <div class="kr-empty"><i class="fas fa-badge-check" style="font-size:36px;display:block;margin-bottom:10px;color:#DDD;"></i>No manual verified purchases recorded yet.</div>
    <?php else: ?>
    <div class="kr-table-wrap">
        <table class="kr-table">
            <thead>
            <tr><th>ID</th><th>Customer</th><th>Listing</th><th>Source</th><th>Reference</th><th>Added By</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($verifiedPurchases as $purchase): ?>
            <?php
                $purchaseType = (string)($purchase['listing_type'] ?? '');
                $purchaseListingId = (int)($purchase['listing_id'] ?? 0);
                $purchaseUrl = admin_reviews_listing_url($purchaseType, $purchaseListingId);
            ?>
            <tr>
                <td>#<?= (int)$purchase['id'] ?></td>
                <td><strong><?= htmlspecialchars($purchase['user_name'] ?? 'Unknown') ?></strong><div class="kr-muted"><?= htmlspecialchars($purchase['user_email'] ?? '') ?></div></td>
                <td>
                    <a href="<?= htmlspecialchars($purchaseUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(admin_reviews_listing_title($db, $purchaseType, $purchaseListingId)) ?></a>
                    <div class="kr-muted"><?= htmlspecialchars(ucfirst($purchaseType)) ?> #<?= $purchaseListingId ?></div>
                </td>
                <td><?= htmlspecialchars(ucfirst((string)($purchase['source'] ?? 'manual'))) ?></td>
                <td><?= htmlspecialchars((string)($purchase['order_reference'] ?? '—')) ?></td>
                <td><?= htmlspecialchars($purchase['creator_name'] ?? 'System') ?></td>
                <td><?= date('M j, Y H:i', strtotime((string)($purchase['created_at'] ?? 'now'))) ?></td>
                <td>
                    <form method="POST" class="kr-inline-form" data-confirm="Remove this verified purchase?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="admin_action" value="remove_verified_purchase">
                        <input type="hidden" name="purchase_id" value="<?= (int)$purchase['id'] ?>">
                        <button type="submit" class="kr-btn-sm dark"><i class="fas fa-trash"></i> Remove</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
</main>
</div>
<script>
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('form[data-confirm]')) return;
    if (!confirm(form.getAttribute('data-confirm') || 'Are you sure?')) { e.preventDefault(); return; }
    if (form.hasAttribute('data-prompt')) {
        var note = prompt(form.getAttribute('data-prompt') || 'Optional note:');
        if (note === null) { e.preventDefault(); return; }
        var noteInput = form.querySelector('input[name="moderation_note"]');
        if (noteInput) noteInput.value = note;
    }
});
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
