<?php
/**
 * KINAS GROUP — Related Products Engine
 *
 * Safe replacement version.
 *
 * Provides related products for:
 * - car detail pages
 * - marketplace detail pages
 * - solar detail pages
 * - property detail pages
 *
 * Each function returns related active listings first, then fills with
 * random active listings if there are not enough related items.
 */

if (!function_exists('kinas_related_products_limit')) {
    function kinas_related_products_limit(int $limit): int
    {
        return max(1, min(12, $limit));
    }
}

if (!function_exists('kinas_related_products_unique_by_id')) {
    function kinas_related_products_unique_by_id(array $rows): array
    {
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $out[] = $row;
        }

        return $out;
    }
}

// ============================================================
// CARS
// ============================================================

if (!function_exists('kinas_get_related_cars')) {
    function kinas_get_related_cars(PDO $db, array $item, int $limit = 4): array
    {
        $limit = kinas_related_products_limit($limit);

        $currentId = (int)($item['id'] ?? 0);
        $brand = trim((string)($item['brand'] ?? ''));
        $bodyType = trim((string)($item['body_type'] ?? ''));
        $fuelType = trim((string)($item['fuel_type'] ?? ''));
        $transmission = trim((string)($item['transmission'] ?? ''));

        $baseSelect = "
            SELECT
                c.id,
                c.title,
                c.brand,
                c.model,
                c.year,
                c.price,
                c.body_type,
                c.fuel_type,
                c.transmission,
                c.featured,
                (
                    SELECT url
                    FROM listing_images
                    WHERE listing_id = c.id
                      AND listing_type = 'car'
                    ORDER BY sort_order
                    LIMIT 1
                ) AS thumbnail
            FROM car_listings c
            WHERE c.status = 'active'
              AND c.id != ?
        ";

        try {
            $results = [];

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

            if ($transmission !== '') {
                $clauses[] = 'c.transmission = ?';
                $params[] = $transmission;
            }

            if (!empty($clauses)) {
                $sql = $baseSelect
                    . ' AND (' . implode(' OR ', $clauses) . ')'
                    . ' ORDER BY RAND() LIMIT ' . $limit;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!is_array($results)) {
                    $results = [];
                }
            }

            if (count($results) < $limit) {
                $needed = $limit - count($results);

                $sql = $baseSelect . ' ORDER BY RAND() LIMIT ' . $needed;

                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);

                $extra = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (is_array($extra)) {
                    $results = array_merge($results, $extra);
                }
            }

            $results = kinas_related_products_unique_by_id($results);

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
    function kinas_get_related_marketplace_items(PDO $db, array $item, int $limit = 4): array
    {
        $limit = kinas_related_products_limit($limit);

        $currentId = (int)($item['id'] ?? 0);
        $categoryId = (int)($item['category_id'] ?? 0);
        $brand = trim((string)($item['brand'] ?? ''));

        $baseSelect = "
            SELECT
                m.id,
                m.title,
                m.price,
                m.brand,
                m.category_id,
                c.name AS category_name,
                (
                    SELECT url
                    FROM listing_images
                    WHERE listing_id = m.id
                      AND listing_type = 'marketplace'
                    ORDER BY sort_order
                    LIMIT 1
                ) AS thumbnail
            FROM marketplace_listings m
            LEFT JOIN marketplace_categories c ON m.category_id = c.id
            WHERE m.status = 'active'
              AND m.id != ?
        ";

        try {
            $results = [];

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

            if (!empty($clauses)) {
                $sql = $baseSelect
                    . ' AND (' . implode(' OR ', $clauses) . ')'
                    . ' ORDER BY RAND() LIMIT ' . $limit;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!is_array($results)) {
                    $results = [];
                }
            }

            if (count($results) < $limit) {
                $needed = $limit - count($results);

                $sql = $baseSelect . ' ORDER BY RAND() LIMIT ' . $needed;

                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);

                $extra = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (is_array($extra)) {
                    $results = array_merge($results, $extra);
                }
            }

            $results = kinas_related_products_unique_by_id($results);

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            error_log('kinas_get_related_marketplace_items error: ' . $e->getMessage());
            return [];
        }
    }
}

// ============================================================
// SOLAR
// ============================================================

if (!function_exists('kinas_get_related_solar_systems')) {
    function kinas_get_related_solar_systems(PDO $db, array $item, int $limit = 4): array
    {
        $limit = kinas_related_products_limit($limit);

        $currentId = (int)($item['id'] ?? 0);
        $serviceType = trim((string)($item['service_type'] ?? ''));
        $brand = trim((string)($item['brand'] ?? ''));

        $baseSelect = "
            SELECT
                s.id,
                s.title,
                s.service_type,
                s.price,
                s.brand,
                s.capacity_kw,
                s.featured,
                (
                    SELECT url
                    FROM listing_images
                    WHERE listing_id = s.id
                      AND listing_type = 'solar'
                    ORDER BY sort_order
                    LIMIT 1
                ) AS thumbnail
            FROM solar_listings s
            WHERE s.status = 'active'
              AND s.id != ?
        ";

        try {
            $results = [];

            $clauses = [];
            $params = [$currentId];

            if ($serviceType !== '') {
                $clauses[] = 's.service_type = ?';
                $params[] = $serviceType;
            }

            if ($brand !== '') {
                $clauses[] = 's.brand = ?';
                $params[] = $brand;
            }

            if (!empty($clauses)) {
                $sql = $baseSelect
                    . ' AND (' . implode(' OR ', $clauses) . ')'
                    . ' ORDER BY RAND() LIMIT ' . $limit;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!is_array($results)) {
                    $results = [];
                }
            }

            if (count($results) < $limit) {
                $needed = $limit - count($results);

                $sql = $baseSelect . ' ORDER BY RAND() LIMIT ' . $needed;

                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);

                $extra = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (is_array($extra)) {
                    $results = array_merge($results, $extra);
                }
            }

            $results = kinas_related_products_unique_by_id($results);

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            error_log('kinas_get_related_solar_systems error: ' . $e->getMessage());
            return [];
        }
    }
}

// ============================================================
// PROPERTY
// ============================================================

if (!function_exists('kinas_get_related_properties')) {
    function kinas_get_related_properties(PDO $db, array $item, int $limit = 4): array
    {
        $limit = kinas_related_products_limit($limit);

        $currentId = (int)($item['id'] ?? 0);
        $propertyType = trim((string)($item['property_type'] ?? ''));
        $city = trim((string)($item['city'] ?? ''));

        $baseSelect = "
            SELECT
                p.id,
                p.title,
                p.property_type,
                p.price,
                p.beds,
                p.baths,
                p.sqft,
                p.city,
                p.state,
                p.featured,
                (
                    SELECT url
                    FROM listing_images
                    WHERE listing_id = p.id
                      AND listing_type = 'property'
                    ORDER BY sort_order
                    LIMIT 1
                ) AS thumbnail
            FROM property_listings p
            WHERE p.status = 'active'
              AND p.id != ?
        ";

        try {
            $results = [];

            $clauses = [];
            $params = [$currentId];

            if ($propertyType !== '') {
                $clauses[] = 'p.property_type = ?';
                $params[] = $propertyType;
            }

            if ($city !== '') {
                $clauses[] = 'p.city = ?';
                $params[] = $city;
            }

            if (!empty($clauses)) {
                $sql = $baseSelect
                    . ' AND (' . implode(' OR ', $clauses) . ')'
                    . ' ORDER BY RAND() LIMIT ' . $limit;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!is_array($results)) {
                    $results = [];
                }
            }

            if (count($results) < $limit) {
                $needed = $limit - count($results);

                $sql = $baseSelect . ' ORDER BY RAND() LIMIT ' . $needed;

                $stmt = $db->prepare($sql);
                $stmt->execute([$currentId]);

                $extra = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (is_array($extra)) {
                    $results = array_merge($results, $extra);
                }
            }

            $results = kinas_related_products_unique_by_id($results);

            return array_slice($results, 0, $limit);
        } catch (Throwable $e) {
            error_log('kinas_get_related_properties error: ' . $e->getMessage());
            return [];
        }
    }
}

// ============================================================
// GENERIC HELPER
// ============================================================

if (!function_exists('kinas_get_related_products')) {
    function kinas_get_related_products(PDO $db, string $type, array $item, int $limit = 4): array
    {
        $type = strtolower(trim($type));

        if ($type === 'car' || $type === 'automobile') {
            return kinas_get_related_cars($db, $item, $limit);
        }

        if ($type === 'marketplace' || $type === 'market') {
            return kinas_get_related_marketplace_items($db, $item, $limit);
        }

        if ($type === 'solar' || $type === 'volt') {
            return kinas_get_related_solar_systems($db, $item, $limit);
        }

        if ($type === 'property' || $type === 'home') {
            return kinas_get_related_properties($db, $item, $limit);
        }

        return [];
    }
}
