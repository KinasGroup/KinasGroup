<?php
declare(strict_types=1);

if (!class_exists('KinasListingRotation')) {
    class KinasListingRotation
    {
        private $db;

        private $tables = [
            'car' => 'car_listings',
            'property' => 'property_listings',
            'solar' => 'solar_listings',
            'marketplace' => 'marketplace_listings',
        ];

        public function __construct($db)
        {
            $this->db = $db;
        }

        public function getRotatingIds(string $division, int $limit = 12): array
        {
            $division = strtolower($division);

            if (!isset($this->tables[$division])) {
                return [];
            }

            $limit = max(1, min(100, $limit));

            $this->sendNoCacheHeaders();

            if (!$this->sessionReady()) {
                return $this->getRandomIds($division, $limit);
            }

            $key = 'kinas_product_rotation_' . $division;
            $state = $_SESSION[$key] ?? [];

            try {
                $total = (int)$this->db->query("
                    SELECT COUNT(*)
                    FROM {$this->tables[$division]}
                    WHERE status = 'active'
                ")->fetchColumn();
            } catch (Exception $e) {
                $total = 0;
            }

            if ($total === 0) {
                unset($_SESSION[$key]);
                return [];
            }

            if (!isset($state['total']) || (int)$state['total'] !== $total) {
                $state = [
                    'total' => $total,
                    'remaining' => [],
                    'shown' => [],
                ];
            }

            $state['remaining'] = array_values(array_filter(array_map('intval', (array)($state['remaining'] ?? []))));
            $state['shown'] = array_values(array_filter(array_map('intval', (array)($state['shown'] ?? []))));

            if (count($state['remaining']) < $limit) {
                $allIds = $this->getActiveIds($division);
                $unseen = array_values(array_diff($allIds, $state['shown']));
                $needed = $limit - count($state['remaining']);

                if (count($unseen) < $needed) {
                    $state['shown'] = [];
                    $unseen = $allIds;
                }

                shuffle($unseen);

                $state['remaining'] = array_merge(
                    $state['remaining'],
                    array_slice($unseen, 0, $needed)
                );
            }

            $take = array_slice($state['remaining'], 0, $limit);

            $state['remaining'] = array_slice($state['remaining'], count($take));
            $state['shown'] = array_values(array_unique(array_merge($state['shown'], $take)));

            if (count($state['shown']) > 5000) {
                $state['shown'] = array_slice($state['shown'], -5000);
            }

            $_SESSION[$key] = $state;

            return $take;
        }

        private function getActiveIds(string $division): array
        {
            $table = $this->tables[$division] ?? null;

            if (!$table) {
                return [];
            }

            try {
                $ids = $this->db->query("
                    SELECT id
                    FROM {$table}
                    WHERE status = 'active'
                ")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                return [];
            }

            return array_values(array_unique(array_map('intval', (array)$ids)));
        }

        private function getRandomIds(string $division, int $limit): array
        {
            $table = $this->tables[$division] ?? null;

            if (!$table) {
                return [];
            }

            try {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM {$table}
                    WHERE status = 'active'
                    ORDER BY RAND()
                    LIMIT " . (int)$limit . "
                ");

                $stmt->execute();

                return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (Exception $e) {
                return [];
            }
        }

        private function sessionReady(): bool
        {
            if (session_status() === PHP_SESSION_ACTIVE) {
                return true;
            }

            if (headers_sent()) {
                return false;
            }

            return @session_start();
        }

        private function sendNoCacheHeaders(): void
        {
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
            }
        }
    }
}

if (!function_exists('kinas_sort_rows_by_ids')) {
    function kinas_sort_rows_by_ids(array $rows, array $ids, string $idKey = 'id'): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (isset($row[$idKey])) {
                $indexed[(int)$row[$idKey]] = $row;
            }
        }

        $sorted = [];

        foreach ($ids as $id) {
            $id = (int)$id;

            if (isset($indexed[$id])) {
                $sorted[] = $indexed[$id];
            }
        }

        return $sorted;
    }
}

if (!function_exists('kinas_get_home_rotated_listings')) {
    function kinas_get_home_rotated_listings($db, int $homeLimit = 12, int $perDivisionPool = 6): array
    {
        $rotation = new KinasListingRotation($db);

        $carIds = $rotation->getRotatingIds('car', $perDivisionPool);
        $propertyIds = $rotation->getRotatingIds('property', $perDivisionPool);
        $solarIds = $rotation->getRotatingIds('solar', $perDivisionPool);
        $marketplaceIds = $rotation->getRotatingIds('marketplace', $perDivisionPool);

        $featuredCar = [];
        if (!empty($carIds)) {
            $carIdList = implode(',', array_map('intval', $carIds));

            $featuredCar = $db->query("
                SELECT c.id, c.title, c.brand, c.model, c.year, c.price, c.featured,
                       'car' as listing_type, 'KINAS Automobile' as division,
                       (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM car_listings c
                WHERE c.status = 'active' AND c.id IN ($carIdList)
            ")->fetchAll();

            $featuredCar = kinas_sort_rows_by_ids($featuredCar, $carIds);
        }

        $featuredProperty = [];
        if (!empty($propertyIds)) {
            $propertyIdList = implode(',', array_map('intval', $propertyIds));

            $featuredProperty = $db->query("
                SELECT p.id, p.title, p.price, p.featured, p.property_type,
                       'property' as listing_type, 'Williams Connect Home' as division,
                       (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM property_listings p
                WHERE p.status = 'active' AND p.id IN ($propertyIdList)
            ")->fetchAll();

            $featuredProperty = kinas_sort_rows_by_ids($featuredProperty, $propertyIds);
        }

        $featuredSolar = [];
        if (!empty($solarIds)) {
            $solarIdList = implode(',', array_map('intval', $solarIds));

            $featuredSolar = $db->query("
                SELECT s.id, s.title, s.price, s.service_type, s.featured,
                       'solar' as listing_type, 'KINAS Volt' as division,
                       (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM solar_listings s
                WHERE s.status = 'active' AND s.id IN ($solarIdList)
            ")->fetchAll();

            $featuredSolar = kinas_sort_rows_by_ids($featuredSolar, $solarIds);
        }

        $featuredMarketplace = [];
        if (!empty($marketplaceIds)) {
            $marketplaceIdList = implode(',', array_map('intval', $marketplaceIds));

            $featuredMarketplace = $db->query("
                SELECT m.id, m.title, m.price, m.featured, m.brand,
                       'marketplace' as listing_type, 'KINAS Marketplace' as division,
                       (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
                FROM marketplace_listings m
                WHERE m.status = 'active' AND m.id IN ($marketplaceIdList)
            ")->fetchAll();

            $featuredMarketplace = kinas_sort_rows_by_ids($featuredMarketplace, $marketplaceIds);
        }

        $featuredListings = array_merge(
            $featuredCar,
            $featuredProperty,
            $featuredSolar,
            $featuredMarketplace
        );

        shuffle($featuredListings);

        return array_slice($featuredListings, 0, $homeLimit);
    }
}

if (!function_exists('kinas_get_rotated_cars')) {
    function kinas_get_rotated_cars($db, int $limit = 12): array
    {
        $rotation = new KinasListingRotation($db);
        $ids = $rotation->getRotatingIds('car', $limit);

        if (empty($ids)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $ids));

        $rows = $db->query("
            SELECT c.*, a.verified AS agent_verified,
                   (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM car_listings c
            LEFT JOIN users a ON c.agent_id = a.id
            WHERE c.status = 'active' AND c.id IN ($idList)
        ")->fetchAll();

        return kinas_sort_rows_by_ids($rows, $ids);
    }
}

if (!function_exists('kinas_get_rotated_solar')) {
    function kinas_get_rotated_solar($db, int $limit = 12): array
    {
        $rotation = new KinasListingRotation($db);
        $ids = $rotation->getRotatingIds('solar', $limit);

        if (empty($ids)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $ids));

        $rows = $db->query("
            SELECT s.*, a.verified AS agent_verified,
                   (SELECT url FROM listing_images WHERE listing_id = s.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM solar_listings s
            LEFT JOIN users a ON s.agent_id = a.id
            WHERE s.status = 'active' AND s.id IN ($idList)
        ")->fetchAll();

        return kinas_sort_rows_by_ids($rows, $ids);
    }
}

if (!function_exists('kinas_get_rotated_marketplace')) {
    function kinas_get_rotated_marketplace($db, int $limit = 12): array
    {
        $rotation = new KinasListingRotation($db);
        $ids = $rotation->getRotatingIds('marketplace', $limit);

        if (empty($ids)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $ids));

        $rows = $db->query("
            SELECT m.*, c.name AS category_name, c.slug AS category_slug,
                   a.verified AS agent_verified,
                   (SELECT url FROM listing_images WHERE listing_id = m.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM marketplace_listings m
            LEFT JOIN marketplace_categories c ON m.category_id = c.id
            LEFT JOIN users a ON m.agent_id = a.id
            WHERE m.status = 'active' AND m.id IN ($idList)
        ")->fetchAll();

        return kinas_sort_rows_by_ids($rows, $ids);
    }
}

if (!function_exists('kinas_get_rotated_properties')) {
    function kinas_get_rotated_properties($db, int $limit = 12): array
    {
        $rotation = new KinasListingRotation($db);
        $ids = $rotation->getRotatingIds('property', $limit);

        if (empty($ids)) {
            return [];
        }

        $idList = implode(',', array_map('intval', $ids));

        $rows = $db->query("
            SELECT p.*, a.verified AS agent_verified,
                   (SELECT url FROM listing_images WHERE listing_id = p.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) AS thumbnail
            FROM property_listings p
            LEFT JOIN users a ON p.agent_id = a.id
            WHERE p.status = 'active' AND p.id IN ($idList)
        ")->fetchAll();

        return kinas_sort_rows_by_ids($rows, $ids);
    }
}
