<?php
/**
 * KINAS GROUP — Product Review Engine
 *
 * Core review helper for product reviews across:
 * - car
 * - property
 * - solar
 * - marketplace
 *
 * This file provides:
 * - review summary calculations
 * - approved review listing
 * - verified purchase detection
 * - review permission checks
 * - review submission helper
 * - review reporting helper
 * - frontend review section renderer
 */

if (!class_exists('SessionManager', false)) {
    require_once __DIR__ . '/session.php';
}

if (!function_exists('generate_csrf_token')) {
    require_once __DIR__ . '/csrf.php';
}

// ============================================================
// BASIC HELPERS
// ============================================================

if (!function_exists('kinas_review_allowed_types')) {
    function kinas_review_allowed_types(): array
    {
        return ['car', 'property', 'solar', 'marketplace'];
    }
}

if (!function_exists('kinas_normalize_review_listing_type')) {
    function kinas_normalize_review_listing_type(?string $type): ?string
    {
        $type = strtolower(trim((string)$type));

        $map = [
            'car' => 'car',
            'automobile' => 'car',
            'vehicle' => 'car',
            'vehicles' => 'car',

            'property' => 'property',
            'real_estate' => 'property',
            'realestate' => 'property',
            'home' => 'property',
            'homes' => 'property',

            'solar' => 'solar',
            'volt' => 'solar',
            'energy' => 'solar',

            'marketplace' => 'marketplace',
            'market' => 'marketplace',
            'item' => 'marketplace',
            'product' => 'marketplace',
        ];

        return $map[$type] ?? null;
    }
}

if (!function_exists('kinas_review_listing_table')) {
    function kinas_review_listing_table(string $type): ?string
    {
        $tables = [
            'car' => 'car_listings',
            'property' => 'property_listings',
            'solar' => 'solar_listings',
            'marketplace' => 'marketplace_listings',
        ];

        return $tables[$type] ?? null;
    }
}

if (!function_exists('kinas_review_current_user_id')) {
    function kinas_review_current_user_id(): ?int
    {
        if (class_exists('SessionManager', false) && method_exists('SessionManager', 'getUserId')) {
            $userId = SessionManager::getUserId();
            return $userId ? (int)$userId : null;
        }

        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('kinas_review_client_ip')) {
    function kinas_review_client_ip(): string
    {
        if (class_exists('Security', false) && method_exists('Security', 'getClientIP')) {
            return Security::getClientIP();
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

if (!function_exists('kinas_review_csrf_token')) {
    function kinas_review_csrf_token(): string
    {
        if (class_exists('Security', false) && method_exists('Security', 'generateCSRFToken')) {
            return Security::generateCSRFToken();
        }

        if (function_exists('generate_csrf_token')) {
            return generate_csrf_token();
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('kinas_review_verify_csrf_token')) {
    function kinas_review_verify_csrf_token(?string $token): bool
    {
        $token = (string)($token ?? '');

        if ($token === '') {
            return false;
        }

        if (class_exists('Security', false) && method_exists('Security', 'verifyCSRFToken')) {
            return Security::verifyCSRFToken($token);
        }

        if (function_exists('verify_csrf_token')) {
            return verify_csrf_token($token);
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('kinas_review_rate_limit')) {
    /**
     * Uses Security::rateLimitDB() when available.
     * This will stop execution with a 429 JSON response when abused.
     */
    function kinas_review_rate_limit(string $action, int $userId = 0, int $max = 5, int $windowSeconds = 3600): void
    {
        if (class_exists('Security', false) && method_exists('Security', 'rateLimitDB')) {
            $key = 'review_' . $action . '_' . ($userId > 0 ? 'u' . $userId : 'ip_' . kinas_review_client_ip());
            Security::rateLimitDB($key, $max, $windowSeconds);
        }
    }
}

// ============================================================
// DATABASE SCHEMA HELPERS
// ============================================================

if (!function_exists('kinas_reviews_table_exists')) {
    function kinas_reviews_table_exists(PDO $db, string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

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

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cache[$table] = [];

        try {
            $stmt = $db->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

            $stmt->execute([$table]);

            $cache[$table] = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }
}

// ============================================================
// LISTING HELPERS
// ============================================================

if (!function_exists('kinas_get_review_listing')) {
    function kinas_get_review_listing(PDO $db, string $type, int $listingId): ?array
    {
        $type = kinas_normalize_review_listing_type($type);

        if (!$type) {
            return null;
        }

        $table = kinas_review_listing_table($type);

        if (!$table || !kinas_reviews_table_exists($db, $table)) {
            return null;
        }

        try {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
            $stmt->execute([$listingId]);

            $listing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$listing) {
                return null;
            }

            $listing['listing_type'] = $type;

            return $listing;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('kinas_review_listing_is_reviewable')) {
    function kinas_review_listing_is_reviewable(array $listing): bool
    {
        $status = strtolower(trim((string)($listing['status'] ?? 'active')));

        if ($status === '') {
            $status = 'active';
        }

        return in_array($status, [
            'active',
            'sold',
            'rented',
            'completed',
            'under_offer',
            'pending_sale',
        ], true);
    }
}

// ============================================================
// REVIEW DATA HELPERS
// ============================================================

if (!function_exists('kinas_get_review_summary')) {
    function kinas_get_review_summary(PDO $db, string $type, int $listingId): array
    {
        $type = kinas_normalize_review_listing_type($type);

        $empty = [
            'count' => 0,
            'average' => 0,
            'distribution' => [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0,
            ],
        ];

        if (!$type || !kinas_reviews_table_exists($db, 'product_reviews')) {
            return $empty;
        }

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) AS review_count, AVG(rating) AS average_rating
                FROM product_reviews
                WHERE listing_type = ?
                  AND listing_id = ?
                  AND status = 'approved'
            ");

            $stmt->execute([$type, $listingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $count = (int)($row['review_count'] ?? 0);
            $average = (float)($row['average_rating'] ?? 0);

            $distribution = [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0,
            ];

            if ($count > 0) {
                $distStmt = $db->prepare("
                    SELECT rating, COUNT(*) AS total
                    FROM product_reviews
                    WHERE listing_type = ?
                      AND listing_id = ?
                      AND status = 'approved'
                    GROUP BY rating
                ");

                $distStmt->execute([$type, $listingId]);

                foreach ($distStmt->fetchAll(PDO::FETCH_ASSOC) as $distRow) {
                    $rating = (int)$distRow['rating'];

                    if (isset($distribution[$rating])) {
                        $distribution[$rating] = (int)$distRow['total'];
                    }
                }
            }

            return [
                'count' => $count,
                'average' => round($average, 1),
                'distribution' => $distribution,
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }
}

if (!function_exists('kinas_get_listing_reviews')) {
    function kinas_get_listing_reviews(PDO $db, string $type, int $listingId, int $limit = 10, int $offset = 0): array
    {
        $type = kinas_normalize_review_listing_type($type);

        if (!$type || !kinas_reviews_table_exists($db, 'product_reviews')) {
            return [];
        }

        $limit = max(1, min(50, (int)$limit));
        $offset = max(0, (int)$offset);

        try {
            $reviewColumns = kinas_reviews_table_columns($db, 'product_reviews');
            $verifiedSelect = in_array('verified_purchase', $reviewColumns, true)
                ? 'r.verified_purchase'
                : '0 AS verified_purchase';

            $stmt = $db->prepare("
                SELECT
                    r.id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    {$verifiedSelect},
                    u.name AS user_name
                FROM product_reviews r
                LEFT JOIN users u ON u.id = r.user_id
                WHERE r.listing_type = ?
                  AND r.listing_id = ?
                  AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ");

            $stmt->execute([$type, $listingId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('kinas_get_user_review_status')) {
    function kinas_get_user_review_status(PDO $db, int $userId, string $type, int $listingId): ?string
    {
        $type = kinas_normalize_review_listing_type($type);

        if (!$userId || !$type || !kinas_reviews_table_exists($db, 'product_reviews')) {
            return null;
        }

        try {
            $stmt = $db->prepare("
                SELECT status
                FROM product_reviews
                WHERE user_id = ?
                  AND listing_type = ?
                  AND listing_id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId, $type, $listingId]);

            $status = $stmt->fetchColumn();

            return $status ? (string)$status : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

// ============================================================
// VERIFIED PURCHASE DETECTION
// ============================================================

if (!function_exists('kinas_has_verified_purchase')) {
    function kinas_has_verified_purchase(PDO $db, int $userId, string $type, int $listingId): array
    {
        $result = [
            'verified' => false,
            'source' => null,
            'reference' => null,
        ];

        $type = kinas_normalize_review_listing_type($type);

        if (!$userId || !$type) {
            return $result;
        }

        // ------------------------------------------------------------
        // 1. Manual / admin verified purchases
        // ------------------------------------------------------------
        if (kinas_reviews_table_exists($db, 'verified_purchases')) {
            try {
                $stmt = $db->prepare("
                    SELECT source, order_reference
                    FROM verified_purchases
                    WHERE user_id = ?
                      AND listing_type = ?
                      AND listing_id = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId, $type, $listingId]);
                $manual = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($manual) {
                    return [
                        'verified' => true,
                        'source' => $manual['source'] ?? 'manual',
                        'reference' => $manual['order_reference'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                // Ignore and continue to automatic checks.
            }
        }

        // ------------------------------------------------------------
        // 2. Transactions table
        // ------------------------------------------------------------
        if (kinas_reviews_table_exists($db, 'transactions')) {
            $columns = kinas_reviews_table_columns($db, 'transactions');

            if (in_array('buyer_id', $columns, true) && in_array('listing_id', $columns, true) && in_array('status', $columns, true)) {
                $referenceExpression = 'NULL';

                foreach (['paystack_reference', 'reference', 'transaction_ref'] as $candidate) {
                    if (in_array($candidate, $columns, true)) {
                        $referenceExpression = $candidate;
                        break;
                    }
                }

                if ($referenceExpression === 'NULL' && in_array('id', $columns, true)) {
                    $referenceExpression = "CONCAT('TXN-', id)";
                }

                try {
                    $sql = "
                        SELECT {$referenceExpression} AS ref
                        FROM transactions
                        WHERE buyer_id = ?
                          AND listing_id = ?
                          AND status IN ('paid', 'completed', 'successful', 'approved')
                    ";

                    $params = [$userId, $listingId];

                    if (in_array('listing_type', $columns, true)) {
                        $sql .= " AND listing_type = ?";
                        $params[] = $type;
                    }

                    $sql .= " LIMIT 1";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);

                    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($transaction) {
                        return [
                            'verified' => true,
                            'source' => 'transaction',
                            'reference' => $transaction['ref'] ?? null,
                        ];
                    }
                } catch (Throwable $e) {
                    // Continue.
                }
            }
        }

        // ------------------------------------------------------------
        // 3. Marketplace orders / order items
        // ------------------------------------------------------------
        if ($type === 'marketplace' && kinas_reviews_table_exists($db, 'orders') && kinas_reviews_table_exists($db, 'order_items')) {
            $orderColumns = kinas_reviews_table_columns($db, 'orders');
            $itemColumns = kinas_reviews_table_columns($db, 'order_items');

            if (
                in_array('buyer_id', $orderColumns, true)
                && in_array('status', $orderColumns, true)
                && in_array('order_id', $itemColumns, true)
                && in_array('listing_id', $itemColumns, true)
            ) {
                $referenceExpression = 'NULL';

                if (in_array('reference', $orderColumns, true)) {
                    $referenceExpression = 'o.reference';
                } elseif (in_array('id', $orderColumns, true)) {
                    $referenceExpression = "CONCAT('ORD-', o.id)";
                }

                try {
                    $stmt = $db->prepare("
                        SELECT {$referenceExpression} AS ref
                        FROM orders o
                        JOIN order_items oi ON oi.order_id = o.id
                        WHERE o.buyer_id = ?
                          AND o.status = 'paid'
                          AND oi.listing_id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([$userId, $listingId]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($order) {
                        return [
                            'verified' => true,
                            'source' => 'order',
                            'reference' => $order['ref'] ?? null,
                        ];
                    }
                } catch (Throwable $e) {
                    // Continue.
                }
            }
        }

        // ------------------------------------------------------------
        // 4. Inspection bookings
        // ------------------------------------------------------------
        if (kinas_reviews_table_exists($db, 'inspection_bookings')) {
            $columns = kinas_reviews_table_columns($db, 'inspection_bookings');

            if (in_array('buyer_id', $columns, true) && in_array('listing_id', $columns, true)) {
                $statusColumn = null;

                foreach (['status', 'payment_status', 'booking_status'] as $candidate) {
                    if (in_array($candidate, $columns, true)) {
                        $statusColumn = $candidate;
                        break;
                    }
                }

                if ($statusColumn) {
                    $referenceExpression = 'NULL';

                    if (in_array('reference', $columns, true)) {
                        $referenceExpression = 'reference';
                    } elseif (in_array('id', $columns, true)) {
                        $referenceExpression = "CONCAT('INS-', id)";
                    }

                    try {
                        $sql = "
                            SELECT {$referenceExpression} AS ref
                            FROM inspection_bookings
                            WHERE buyer_id = ?
                              AND listing_id = ?
                              AND {$statusColumn} IN ('paid', 'confirmed', 'completed', 'approved', 'success')
                        ";

                        $params = [$userId, $listingId];

                        if (in_array('listing_type', $columns, true)) {
                            $sql .= " AND listing_type = ?";
                            $params[] = $type;
                        }

                        $sql .= " LIMIT 1";

                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);

                        $inspection = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($inspection) {
                            return [
                                'verified' => true,
                                'source' => 'inspection',
                                'reference' => $inspection['ref'] ?? null,
                            ];
                        }
                    } catch (Throwable $e) {
                        // Continue.
                    }
                }
            }
        }

        // ------------------------------------------------------------
        // 5. Car rental bookings
        // ------------------------------------------------------------
        if ($type === 'car' && kinas_reviews_table_exists($db, 'car_rental_bookings')) {
            $columns = kinas_reviews_table_columns($db, 'car_rental_bookings');

            if (
                in_array('user_id', $columns, true)
                && in_array('car_id', $columns, true)
                && in_array('status', $columns, true)
            ) {
                $referenceExpression = 'NULL';

                if (in_array('reference', $columns, true)) {
                    $referenceExpression = 'reference';
                } elseif (in_array('id', $columns, true)) {
                    $referenceExpression = "CONCAT('RENT-', id)";
                }

                try {
                    $stmt = $db->prepare("
                        SELECT {$referenceExpression} AS ref
                        FROM car_rental_bookings
                        WHERE user_id = ?
                          AND car_id = ?
                          AND status IN ('confirmed', 'active', 'completed')
                        LIMIT 1
                    ");

                    $stmt->execute([$userId, $listingId]);
                    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($rental) {
                        return [
                            'verified' => true,
                            'source' => 'rental',
                            'reference' => $rental['ref'] ?? null,
                        ];
                    }
                } catch (Throwable $e) {
                    // Continue.
                }
            }
        }

        return $result;
    }
}

// ============================================================
// PERMISSIONS
// ============================================================

if (!function_exists('kinas_user_is_listing_owner')) {
    function kinas_user_is_listing_owner(PDO $db, int $userId, string $type, int $listingId): bool
    {
        $listing = kinas_get_review_listing($db, $type, $listingId);

        if (!$listing) {
            return false;
        }

        return (int)($listing['agent_id'] ?? 0) === $userId;
    }
}

if (!function_exists('kinas_can_user_review')) {
    function kinas_can_user_review(PDO $db, ?int $userId, string $type, int $listingId): array
    {
        $type = kinas_normalize_review_listing_type($type);

        if (!$userId) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'Please log in to leave a review.',
                'reference' => null,
                'source' => null,
            ];
        }

        if (!$type) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'Invalid listing type.',
                'reference' => null,
                'source' => null,
            ];
        }

        $listing = kinas_get_review_listing($db, $type, $listingId);

        if (!$listing) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'This listing could not be found.',
                'reference' => null,
                'source' => null,
            ];
        }

        if (!kinas_review_listing_is_reviewable($listing)) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'This listing is not currently available for reviews.',
                'reference' => null,
                'source' => null,
            ];
        }

        if ((int)($listing['agent_id'] ?? 0) === (int)$userId) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'You cannot review your own listing.',
                'reference' => null,
                'source' => null,
            ];
        }

        $existingStatus = kinas_get_user_review_status($db, (int)$userId, $type, $listingId);

        if ($existingStatus === 'pending') {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'Your review has already been submitted and is awaiting moderation.',
                'reference' => null,
                'source' => null,
            ];
        }

        if ($existingStatus === 'approved') {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'You have already reviewed this product.',
                'reference' => null,
                'source' => null,
            ];
        }

        if ($existingStatus === 'rejected') {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'Your previous review was not approved. Please contact support if you believe this is an error.',
                'reference' => null,
                'source' => null,
            ];
        }

        $purchase = kinas_has_verified_purchase($db, (int)$userId, $type, $listingId);

        if (!$purchase['verified']) {
            return [
                'allowed' => false,
                'verified' => false,
                'reason' => 'Only verified customers who purchased or completed a transaction for this product can leave a review.',
                'reference' => null,
                'source' => null,
            ];
        }

        return [
            'allowed' => true,
            'verified' => true,
            'reason' => '',
            'reference' => $purchase['reference'] ?? null,
            'source' => $purchase['source'] ?? null,
        ];
    }
}

// ============================================================
// VALIDATION
// ============================================================

if (!function_exists('kinas_validate_review_comment')) {
    function kinas_validate_review_comment(?string $comment): array
    {
        $comment = trim(strip_tags((string)$comment));

        $length = function_exists('mb_strlen') ? mb_strlen($comment) : strlen($comment);

        if ($length < 10) {
            return [
                'valid' => false,
                'comment' => $comment,
                'error' => 'Please write at least 10 characters in your review.',
            ];
        }

        if ($length > 2000) {
            return [
                'valid' => false,
                'comment' => $comment,
                'error' => 'Your review is too long. Maximum length is 2000 characters.',
            ];
        }

        if (preg_match('#(https?://|www\.)#i', $comment)) {
            return [
                'valid' => false,
                'comment' => $comment,
                'error' => 'Reviews cannot contain links.',
            ];
        }

        if (strpos($comment, '<') !== false || strpos($comment, '>') !== false) {
            return [
                'valid' => false,
                'comment' => $comment,
                'error' => 'Reviews cannot contain HTML.',
            ];
        }

        return [
            'valid' => true,
            'comment' => $comment,
            'error' => null,
        ];
    }
}

// ============================================================
// CREATE REVIEW
// ============================================================

if (!function_exists('kinas_create_product_review')) {
    function kinas_create_product_review(PDO $db, ?int $userId, ?string $type, int $listingId, int $rating, ?string $comment): array
    {
        $userId = (int)$userId;

        if (!$userId) {
            return [
                'success' => false,
                'error' => 'Please log in to leave a review.',
            ];
        }

        $type = kinas_normalize_review_listing_type($type);

        if (!$type) {
            return [
                'success' => false,
                'error' => 'Invalid listing type.',
            ];
        }

        $rating = (int)$rating;

        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'error' => 'Please choose a rating from 1 to 5 stars.',
            ];
        }

        $commentCheck = kinas_validate_review_comment($comment);

        if (!$commentCheck['valid']) {
            return [
                'success' => false,
                'error' => $commentCheck['error'],
            ];
        }

        $comment = $commentCheck['comment'];

        $canReview = kinas_can_user_review($db, $userId, $type, $listingId);

        if (!$canReview['allowed']) {
            return [
                'success' => false,
                'error' => $canReview['reason'] ?: 'You are not allowed to review this product.',
            ];
        }

        if (!kinas_reviews_table_exists($db, 'product_reviews')) {
            return [
                'success' => false,
                'error' => 'The review system is not installed.',
            ];
        }

        $columns = kinas_reviews_table_columns($db, 'product_reviews');

        if (empty($columns)) {
            return [
                'success' => false,
                'error' => 'The review system is not installed correctly.',
            ];
        }

        $insertColumns = [
            'user_id',
            'listing_type',
            'listing_id',
            'rating',
            'comment',
            'status',
        ];

        $insertValues = [
            $userId,
            $type,
            $listingId,
            $rating,
            $comment,
            'pending',
        ];

        if (in_array('verified_purchase', $columns, true)) {
            $insertColumns[] = 'verified_purchase';
            $insertValues[] = 1;
        }

        if (in_array('ip_address', $columns, true)) {
            $insertColumns[] = 'ip_address';
            $insertValues[] = kinas_review_client_ip();
        }

        if (in_array('order_reference', $columns, true)) {
            $insertColumns[] = 'order_reference';
            $insertValues[] = $canReview['reference'] ?? null;
        }

        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));

        try {
            $stmt = $db->prepare("
                INSERT INTO product_reviews
                (" . implode(', ', $insertColumns) . ")
                VALUES
                ({$placeholders})
            ");

            $stmt->execute($insertValues);

            return [
                'success' => true,
                'review_id' => (int)$db->lastInsertId(),
                'message' => 'Thank you. Your review has been submitted and is awaiting moderation.',
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return [
                    'success' => false,
                    'error' => 'You have already reviewed this product.',
                ];
            }

            error_log('kinas_create_product_review error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Could not submit your review. Please try again.',
            ];
        } catch (Throwable $e) {
            error_log('kinas_create_product_review error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Could not submit your review. Please try again.',
            ];
        }
    }
}

// ============================================================
// REPORT REVIEW
// ============================================================

if (!function_exists('kinas_report_product_review')) {
    function kinas_report_product_review(PDO $db, ?int $userId, int $reviewId, ?string $reason): array
    {
        $userId = (int)$userId;

        if (!$userId) {
            return [
                'success' => false,
                'error' => 'Please log in to report a review.',
            ];
        }

        $reviewId = (int)$reviewId;

        if (!$reviewId) {
            return [
                'success' => false,
                'error' => 'Invalid review.',
            ];
        }

        $reason = trim(strip_tags((string)$reason));

        $length = function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason);

        if ($length < 5) {
            return [
                'success' => false,
                'error' => 'Please provide a short reason for reporting this review.',
            ];
        }

        if ($length > 500) {
            return [
                'success' => false,
                'error' => 'Your report reason is too long.',
            ];
        }

        if (!kinas_reviews_table_exists($db, 'product_review_reports')) {
            return [
                'success' => false,
                'error' => 'Review reporting is not available.',
            ];
        }

        try {
            $reviewStmt = $db->prepare("
                SELECT id
                FROM product_reviews
                WHERE id = ?
                  AND status = 'approved'
                LIMIT 1
            ");

            $reviewStmt->execute([$reviewId]);

            if (!$reviewStmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'This review could not be found.',
                ];
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'Could not validate the review.',
            ];
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO product_review_reports
                (review_id, user_id, reason, status, created_at)
                VALUES (?, ?, ?, 'open', NOW())
            ");

            $stmt->execute([$reviewId, $userId, $reason]);

            return [
                'success' => true,
                'message' => 'Thank you. This review has been reported and will be checked by our team.',
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return [
                    'success' => false,
                    'error' => 'You have already reported this review.',
                ];
            }

            error_log('kinas_report_product_review error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Could not report this review. Please try again.',
            ];
        } catch (Throwable $e) {
            error_log('kinas_report_product_review error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Could not report this review. Please try again.',
            ];
        }
    }
}

// ============================================================
// FRONTEND RENDER HELPERS
// ============================================================

if (!function_exists('kinas_render_review_stars')) {
    function kinas_render_review_stars($rating): string
    {
        $rating = max(0, min(5, (int)$rating));

        $stars = '';

        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="fas fa-star"></i>';
            } else {
                $stars .= '<i class="far fa-star"></i>';
            }
        }

        return '<span class="kr-stars" aria-label="' . $rating . ' out of 5 stars">' . $stars . '</span>';
    }
}

if (!function_exists('kinas_render_reviews_section')) {
    function kinas_render_reviews_section(PDO $db, string $listingType, int $listingId): void
    {
        $listingType = kinas_normalize_review_listing_type($listingType);

        if (!$listingType) {
            return;
        }

        if (!kinas_reviews_table_exists($db, 'product_reviews')) {
            return;
        }

        $listingId = (int)$listingId;

        $summary = kinas_get_review_summary($db, $listingType, $listingId);
        $reviews = kinas_get_listing_reviews($db, $listingType, $listingId, 10, 0);

        $currentUserId = kinas_review_current_user_id();
        $canReview = kinas_can_user_review($db, $currentUserId, $listingType, $listingId);

        $csrfToken = kinas_review_csrf_token();

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $loginUrl = '/auth/login.php?redirect=' . urlencode($requestUri);

        static $assetsLoaded = false;

        if (!$assetsLoaded) {
            $assetsLoaded = true;
            ?>
            <style>
                .kr-section {
                    margin-top: 40px;
                }

                .kr-summary {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 30px;
                    align-items: flex-start;
                    margin-bottom: 30px;
                }

                .kr-average {
                    min-width: 180px;
                }

                .kr-average-number {
                    font-family: 'Prata', serif;
                    font-size: 48px;
                    color: #0A0A0A;
                    line-height: 1;
                }

                .kr-average-count {
                    font-size: 13px;
                    color: #888;
                    margin-top: 6px;
                }

                .kr-stars {
                    display: inline-flex;
                    gap: 2px;
                    color: #C6A43F;
                    font-size: 14px;
                }

                .kr-average .kr-stars {
                    font-size: 18px;
                    margin-top: 8px;
                }

                .kr-distribution {
                    flex: 1;
                    min-width: 240px;
                    max-width: 420px;
                }

                .kr-dist-row {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 6px;
                    font-size: 12px;
                    color: #666;
                }

                .kr-dist-label {
                    width: 42px;
                    white-space: nowrap;
                }

                .kr-dist-bar {
                    flex: 1;
                    height: 8px;
                    border-radius: 4px;
                    background: #ECECEC;
                    overflow: hidden;
                }

                .kr-dist-fill {
                    height: 100%;
                    border-radius: 4px;
                    background: #C6A43F;
                }

                .kr-dist-count {
                    width: 30px;
                    text-align: right;
                }

                .kr-review {
                    border-top: 1px solid #E8E8E8;
                    padding: 20px 0;
                }

                .kr-review:first-child {
                    border-top: none;
                    padding-top: 0;
                }

                .kr-review-head {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: space-between;
                    gap: 10px;
                    margin-bottom: 8px;
                }

                .kr-review-user {
                    font-weight: 600;
                    color: #0A0A0A;
                }

                .kr-review-date {
                    font-size: 12px;
                    color: #999;
                }

                .kr-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    background: #E8F5E9;
                    color: #2E7D32;
                    border-radius: 20px;
                    padding: 3px 10px;
                    font-size: 11px;
                    font-weight: 700;
                    margin-left: 8px;
                }

                .kr-comment {
                    color: #444;
                    line-height: 1.6;
                    font-size: 14px;
                }

                .kr-report-btn {
                    background: none;
                    border: none;
                    color: #999;
                    font-size: 12px;
                    cursor: pointer;
                    padding: 0;
                    margin-top: 8px;
                }

                .kr-report-btn:hover {
                    color: #C62828;
                }

                .kr-form-wrap {
                    background: #F9F8F5;
                    border: 1px solid #E8E8E8;
                    border-radius: 10px;
                    padding: 24px;
                    margin-top: 30px;
                }

                .kr-form-title {
                    font-family: 'Prata', serif;
                    font-size: 20px;
                    margin-bottom: 14px;
                }

                .kr-form-group {
                    margin-bottom: 14px;
                }

                .kr-form-group label {
                    display: block;
                    font-size: 13px;
                    font-weight: 600;
                    color: #555;
                    margin-bottom: 6px;
                }

                .kr-form-group select,
                .kr-form-group textarea {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #CCC;
                    border-radius: 6px;
                    font-family: 'Inter', sans-serif;
                    font-size: 14px;
                    box-sizing: border-box;
                }

                .kr-form-group textarea {
                    min-height: 110px;
                    resize: vertical;
                }

                .kr-message {
                    margin-top: 12px;
                    padding: 10px 14px;
                    border-radius: 6px;
                    font-size: 14px;
                    display: none;
                }

                .kr-message.success {
                    display: block;
                    background: #E8F5E9;
                    color: #2E7D32;
                    border: 1px solid #A5D6A7;
                }

                .kr-message.error {
                    display: block;
                    background: #FFEBEE;
                    color: #C62828;
                    border: 1px solid #EF9A9A;
                }

                .kr-note {
                    font-size: 12px;
                    color: #888;
                    margin-top: 10px;
                }

                .kr-empty {
                    color: #888;
                    font-style: italic;
                }

                .kr-login-prompt {
                    background: #F9F8F5;
                    border: 1px solid #E8E8E8;
                    border-radius: 10px;
                    padding: 20px;
                    margin-top: 30px;
                }
            </style>
            <?php
        }
        ?>
        <section class="je-section kr-section" id="kinasReviewsSection" style="padding-left:0;padding-right:0;border-top:1px solid #e8e8e8;">
            <h2>Customer Reviews</h2>

            <?php if ((int)$summary['count'] === 0): ?>
                <p class="kr-empty">No approved reviews yet. Verified customers who purchase this product can leave the first review.</p>
            <?php else: ?>
                <div class="kr-summary">
                    <div class="kr-average">
                        <div class="kr-average-number"><?= number_format((float)$summary['average'], 1) ?></div>
                        <?= kinas_render_review_stars((int)round((float)$summary['average'])) ?>
                        <div class="kr-average-count">
                            <?= (int)$summary['count'] ?> <?= (int)$summary['count'] === 1 ? 'review' : 'reviews' ?>
                        </div>
                    </div>

                    <div class="kr-distribution">
                        <?php
                        $totalReviews = max(1, (int)$summary['count']);

                        for ($star = 5; $star >= 1; $star--):
                            $starCount = (int)($summary['distribution'][$star] ?? 0);
                            $percent = (int)round(($starCount / $totalReviews) * 100);
                        ?>
                            <div class="kr-dist-row">
                                <span class="kr-dist-label"><?= $star ?> star</span>
                                <div class="kr-dist-bar">
                                    <div class="kr-dist-fill" style="width: <?= $percent ?>%;"></div>
                                </div>
                                <span class="kr-dist-count"><?= $starCount ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="kr-reviews-list">
                    <?php foreach ($reviews as $review): ?>
                        <div class="kr-review">
                            <div class="kr-review-head">
                                <div>
                                    <span class="kr-review-user">
                                        <?= htmlspecialchars($review['user_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                    <?php if (!empty($review['verified_purchase'])): ?>
                                        <span class="kr-badge">
                                            <i class="fas fa-badge-check"></i> Verified Purchase
                                        </span>
                                    <?php endif; ?>

                                    <div class="kr-review-date">
                                        <?= date('M j, Y', strtotime((string)$review['created_at'])) ?>
                                    </div>
                                </div>

                                <div>
                                    <?= kinas_render_review_stars((int)$review['rating']) ?>
                                </div>
                            </div>

                            <?php if (!empty($review['comment'])): ?>
                                <div class="kr-comment">
                                    <?= nl2br(htmlspecialchars((string)$review['comment'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($currentUserId): ?>
                                <button type="button"
                                        class="kr-report-btn"
                                        data-review-id="<?= (int)$review['id'] ?>"
                                        data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-flag"></i> Report
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($canReview['allowed'])): ?>
                <div class="kr-form-wrap">
                    <div class="kr-form-title">Write a Review</div>

                    <form id="krReviewForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="listing_type" value="<?= htmlspecialchars($listingType, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="listing_id" value="<?= (int)$listingId ?>">

                        <div class="kr-form-group">
                            <label for="krRating">Your Rating *</label>
                            <select id="krRating" name="rating" required>
                                <option value="">Select a rating</option>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Very Poor</option>
                            </select>
                        </div>

                        <div class="kr-form-group">
                            <label for="krComment">Your Review *</label>
                            <textarea id="krComment"
                                      name="comment"
                                      required
                                      minlength="10"
                                      maxlength="2000"
                                      placeholder="Share your honest experience with this product..."></textarea>
                        </div>

                        <button type="submit" class="je-btn je-btn-gold">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>

                        <div class="kr-note">
                            Reviews are moderated. Approved reviews will appear publicly on this product page.
                        </div>

                        <div class="kr-form-message kr-message"></div>
                    </form>
                </div>
            <?php elseif (!$currentUserId): ?>
                <div class="kr-login-prompt">
                    <p style="margin-bottom: 12px;">
                        Please log in if you purchased this product and want to leave a review.
                    </p>

                    <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" class="je-btn je-btn-gold">
                        <i class="fas fa-user"></i> Log In
                    </a>
                </div>
            <?php else: ?>
                <div class="kr-login-prompt">
                    <p style="margin: 0;">
                        <?= htmlspecialchars($canReview['reason'] ?? 'You are not able to review this product.', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>

        <?php
        static $jsLoaded = false;

        if (!$jsLoaded) {
            $jsLoaded = true;
            ?>
            <script>
                (function () {
                    if (window.__kinasReviewsJsLoaded) {
                        return;
                    }

                    window.__kinasReviewsJsLoaded = true;

                    document.addEventListener('submit', function (e) {
                        var form = e.target;

                        if (!form.matches('#krReviewForm')) {
                            return;
                        }

                        e.preventDefault();

                        var message = form.querySelector('.kr-form-message');
                        var submitButton = form.querySelector('button[type="submit"]');

                        if (message) {
                            message.className = 'kr-message';
                            message.textContent = '';
                        }

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                        }

                        fetch('/api/reviews/create.php', {
                            method: 'POST',
                            body: new FormData(form),
                            credentials: 'same-origin'
                        })
                        .then(function (response) {
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                form.reset();

                                if (message) {
                                    message.className = 'kr-message success';
                                    message.textContent = data.message || 'Thank you. Your review has been submitted.';
                                }

                                if (submitButton) {
                                    submitButton.innerHTML = '<i class="fas fa-check"></i> Submitted';
                                }
                            } else {
                                if (message) {
                                    message.className = 'kr-message error';
                                    message.textContent = data.error || 'Could not submit your review.';
                                }

                                if (submitButton) {
                                    submitButton.disabled = false;
                                    submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review';
                                }
                            }
                        })
                        .catch(function () {
                            if (message) {
                                message.className = 'kr-message error';
                                message.textContent = 'Network error. Please try again.';
                            }

                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review';
                            }
                        });
                    });

                    document.addEventListener('click', function (e) {
                        var button = e.target.closest('.kr-report-btn');

                        if (!button) {
                            return;
                        }

                        e.preventDefault();

                        if (!confirm('Report this review as inappropriate?')) {
                            return;
                        }

                        var reason = prompt('Briefly explain why you are reporting this review:');

                        if (!reason || reason.length < 5) {
                            alert('Please provide a short reason.');
                            return;
                        }

                        var formData = new FormData();
                        formData.append('review_id', button.getAttribute('data-review-id'));
                        formData.append('reason', reason);
                        formData.append('csrf_token', button.getAttribute('data-csrf'));

                        button.disabled = true;

                        fetch('/api/reviews/report.php', {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        })
                        .then(function (response) {
                            return response.json();
                        })
                        .then(function (data) {
                            if (data.success) {
                                button.innerHTML = '<i class="fas fa-check"></i> Reported';
                            } else {
                                alert(data.error || 'Could not report this review.');
                                button.disabled = false;
                            }
                        })
                        .catch(function () {
                            alert('Network error. Please try again.');
                            button.disabled = false;
                        });
                    });
                })();
            </script>
            <?php
        }
    }
}
