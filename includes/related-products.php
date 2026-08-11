<?php
/**
 * KINAS GROUP — Related Products Engine
 *
 * Provides dynamic, weighted related products for:
 * - car detail pages
 * - marketplace detail pages
 * - solar detail pages
 * - property detail pages
 *
 * The results are related first, then randomized so the
 * "You may also like" section does not stay static.
 */

if (!function_exists('kinas_related_price_close')) {
    function kinas_related_price_close(?float $a, ?float $b, float $tolerance = 0.35): bool
    {
        if (!$a || !$b) {
            return false;
        }

        $a = abs($a);
        $b = abs($b);

        if ($a <= 0 || $b <= 0) {
            return false;
        }

        $min = min($a, $b);
        $max = max($a, $b);

        return (($max - $min) / $min) <= $tolerance;
    }
}

if (!function_exists('kinas_related_capacity_close')) {
    function kinas_related_capacity_close(?float $a, ?float $b): bool
    {
        if (!$a || !$b) {
            return false;
        }

        $a = abs((float)$a);
        $b = abs((float)$b);

        if ($a <= 0 || $b <= 0) {
            return false;
        }

        $diff = abs($a - $b);
        $allowed = max(1.0, 0.25 * max($a, $b));

        return $diff <= $allowed;
    }
}

// ============================================================
// CARS
// ============================================================

if (!function_exists('kinas_get_related_cars')) {
    function kinas_get_related_cars(PDO $db, array $current, int $limit = 4): array
    {
        try {
            $limit = max(1, min(12, $limit));

            $currentId = (int)($current['id'] ?? 0);
            $brand = trim((string)($current['brand'] ?? ''));
            $bodyType = trim((string)($current['body_type'] ?? ''));
            $fuelType = trim((string)($current['fuel_type'] ?? ''));
            $transmission = trim((string)($current['transmission'] ?? ''));
            $price = (float)($current['price'] ?? 0);

            $select = "
                SELECT c.id, c.title, c.brand, c.model, c.year, c.price,
                       c.body_type, c.fuel_type, c.transmission, c.featured,
                       (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM car_listings c
                WHERE c.id != ?
                  AND c.status = 'active'
            ";

            $clauses = [];
            $params = [$currentId];

            if ($brand !== '') {
                $clauses[] = 'c.brand = ?';
                $params[] = $brand;
            }

            if ($bodyType !== '') {
                $clauses[] = 'c.body_type = ?';
                $params[] = $bodyType;
            }

            if ($fuelType !== '') {
                $clauses[] = 'c.fuel_type = ?';
                $params[] = $fuelType;
            }

            $candidates = [];

            if (!empty($clauses)) {
                $sql = $select . ' AND (' . implode(' OR ', $clauses) . ') ORDER BY RAND() LIMIT 80';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            if (count($candidates) < $limit) {
                $sql = $select . ' ORDER BY RAND() LIMIT 80';
                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);
                $randomCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $candidateIds = array_map(static function ($row) {
                    return (int)($row['id'] ?? 0);
                }, $candidates);

                foreach ($randomCandidates as $row) {
                    if (!in_array((int)($row['id'] ?? 0), $candidateIds, true)) {
                        $candidates[] = $row;
                        $candidateIds[] = (int)($row['id'] ?? 0);
                    }
                }
            }

            $seen = [$currentId => true];
            $results = [];

            foreach ($candidates as $row) {
                $rowId = (int)($row['id'] ?? 0);

                if ($rowId <= 0 || isset($seen[$rowId])) {
                    continue;
                }

                $seen[$rowId] = true;

                $score = 0;

                if ($brand !== '' && strcasecmp((string)($row['brand'] ?? ''), $brand) === 0) {
                    $score += 4;
                }

                if ($bodyType !== '' && strcasecmp((string)($row['body_type'] ?? ''), $bodyType) === 0) {
                    $score += 3;
                }

                if ($fuelType !== '' && strcasecmp((string)($row['fuel_type'] ?? ''), $fuelType) === 0) {
                    $score += 1;
                }

                if ($transmission !== '' && strcasecmp((string)($row['transmission'] ?? ''), $transmission) === 0) {
                    $score += 1;
                }

                if (kinas_related_price_close($price, (float)($row['price'] ?? 0))) {
                    $score += 2;
                }

                if (!empty($row['featured'])) {
                    $score += 1;
                }

                $row['_score'] = $score + mt_rand(0, 2);
                $results[] = $row;
            }

            usort($results, static function ($a, $b) {
                return ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
            });

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            error_log('kinas_get_related_cars error: ' . $e->getMessage());
            return [];
        }
    }
}

// ============================================================
// MARKETPLACE
// ============================================================

if (!function_exists('kinas_get_related_marketplace_items')) {
    function kinas_get_related_marketplace_items(PDO $db, array $current, int $limit = 4): array
    {
        try {
            $limit = max(1, min(12, $limit));

            $currentId = (int)($current['id'] ?? 0);
            $categoryId = (int)($current['category_id'] ?? 0);
            $brand = trim((string)($current['brand'] ?? ''));
            $price = (float)($current['price'] ?? 0);

            $select = "
                SELECT m.id, m.title, m.price, m.brand, m.category_id, m.featured,
                       c.name AS category_name,
                       (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM marketplace_listings m
                LEFT JOIN marketplace_categories c ON m.category_id = c.id
                WHERE m.id != ?
                  AND m.status = 'active'
            ";

            $clauses = [];
            $params = [$currentId];

            if ($categoryId > 0) {
                $clauses[] = 'm.category_id = ?';
                $params[] = $categoryId;
            }

            if ($brand !== '') {
                $clauses[] = 'm.brand = ?';
                $params[] = $brand;
            }

            $candidates = [];

            if (!empty($clauses)) {
                $sql = $select . ' AND (' . implode(' OR ', $clauses) . ') ORDER BY RAND() LIMIT 80';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            if (count($candidates) < $limit) {
                $sql = $select . ' ORDER BY RAND() LIMIT 80';
                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);
                $randomCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $candidateIds = array_map(static function ($row) {
                    return (int)($row['id'] ?? 0);
                }, $candidates);

                foreach ($randomCandidates as $row) {
                    if (!in_array((int)($row['id'] ?? 0), $candidateIds, true)) {
                        $candidates[] = $row;
                        $candidateIds[] = (int)($row['id'] ?? 0);
                    }
                }
            }

            $seen = [$currentId => true];
            $results = [];

            foreach ($candidates as $row) {
                $rowId = (int)($
