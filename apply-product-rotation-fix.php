<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * KINAS GROUP — Product Rotation Fix Installer
 *
 * This script automatically patches:
 * - includes/featured-algorithm.php
 * - index.php
 * - divisions/kinas-automobile/index.php
 * - divisions/kinas-volt/index.php
 * - divisions/kinas-marketplace/index.php
 * - divisions/williams-connect-home/index.php
 *
 * Run:
 * /apply-product-rotation-fix.php?apply=1
 */

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';

$root = __DIR__;
$backupDir = $root . '/backups/product-rotation-fix-' . date('Ymd-His');

function kpf_header(): void
{
    echo '<html><body style="font-family:Arial,sans-serif;background:#0f0f0f;color:#fff;padding:30px;">';
    echo '<h1 style="color:#C6A43F;">KINAS Product Rotation Fix</h1>';
}

function kpf_footer(): void
{
    echo '</body></html>';
}

function kpf_message(string $message, string $type = 'info'): void
{
    $colors = [
        'info' => '#9ad0ff',
        'success' => '#7ee081',
        'warning' => '#ffd166',
        'error' => '#ff7b7b',
    ];

    $color = $colors[$type] ?? '#fff';

    echo '<div style="padding:10px 14px;margin:8px 0;border:1px solid ' . htmlspecialchars($color) . ';border-left:6px solid ' . htmlspecialchars($color) . ';background:#161616;">';
    echo htmlspecialchars($message);
    echo '</div>';
}

function kpf_backup_file(string $file): void
{
    global $root, $backupDir;

    if (!file_exists($file)) {
        return;
    }

    $relative = ltrim(str_replace($root, '', $file), '/');
    $destination = $backupDir . '/' . $relative;

    if (!is_dir(dirname($destination))) {
        mkdir(dirname($destination), 0775, true);
    }

    if (!copy($file, $destination)) {
        throw new RuntimeException("Could not backup {$relative}");
    }
}

function kpf_write_file(string $file, string $content): void
{
    global $root;

    if (!file_exists($file)) {
        throw new RuntimeException("File not found: {$file}");
    }

    kpf_backup_file($file);

    if (file_put_contents($file, $content) === false) {
        $relative = ltrim(str_replace($root, '', $file), '/');
        throw new RuntimeException("Could not write {$relative}. Check file permissions.");
    }
}

function kpf_add_require(string &$content, string $needle, string $require, string $label): bool
{
    if (strpos($content, $require) !== false) {
        return false;
    }

    if (strpos($content, $needle) === false) {
        throw new RuntimeException("Could not find insertion point for {$label}.");
    }

    $content = str_replace(
        $needle,
        $needle . PHP_EOL . PHP_EOL . $require,
        $content
    );

    return true;
}

function kpf_preg_replace_once(string $pattern, string $replacement, string $subject): ?string
{
    if (!preg_match($pattern, $subject)) {
        return null;
    }

    return preg_replace_callback(
        $pattern,
        function () use ($replacement) {
            return $replacement;
        },
        $subject,
        1
    );
}

if (!$apply) {
    kpf_header();
    kpf_message('This script will patch the homepage and division pages to enable product rotation.', 'info');
    kpf_message('Make sure you are ready, then open this URL:', 'warning');
    kpf_message('/apply-product-rotation-fix.php?apply=1', 'success');
    kpf_footer();
    exit;
}

kpf_header();

try {
    // ============================================================
    // 1. ROTATION ENGINE CODE
    // ============================================================

    $rotationEngineCode = <<<'PHP'
// ============================================================
// PRODUCT ROTATION ENGINE — FAIR HOMEPAGE/DIVISION VISIBILITY
// ============================================================

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

    /**
     * Get rotated listing IDs for a division.
     *
     * This avoids showing the same products repeatedly.
     * It stores a shuffled pool of active listing IDs in the session
     * and serves them until the pool is exhausted.
     */
    public function getRotatingIds(string $division, int $limit = 12): array
    {
        $division = strtolower($division);

        if (!isset($this->tables[$division])) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $this->sendNoCacheHeaders();

        // If session cannot be used, fall back to random selection.
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

        // Reset rotation if the number of active listings changed.
        // This allows newly added products to enter the rotation.
        if (!isset($state['total']) || (int)$state['total'] !== $total) {
            $state = [
                'total' => $total,
                'remaining' => [],
                'shown' => [],
            ];
        }

        $state['remaining'] = array_values(array_filter(array_map('intval', (array)($state['remaining'] ?? []))));
        $state['shown'] = array_values(array_filter(array_map('intval', (array)($state['shown'] ?? []))));

        // If not enough products remain in the pool, add more unseen products.
        if (count($state['remaining']) < $limit) {
            $allIds = $this->getActiveIds($division);
            $unseen = array_values(array_diff($allIds, $state['shown']));
            $needed = $limit - count($state['remaining']);

            // If almost everything has already been shown, start a new cycle.
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

        // Prevent session storage from growing too large over time.
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

/**
 * Preserve the rotation order after fetching rows by ID.
 */
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
PHP;

    // ============================================================
    // 2. PATCH includes/featured-algorithm.php
    // ============================================================

    $featuredAlgorithmFile = $root . '/includes/featured-algorithm.php';

    if (!file_exists($featuredAlgorithmFile)) {
        throw new RuntimeException('includes/featured-algorithm.php was not found.');
    }

    $featuredAlgorithmContent = file_get_contents($featuredAlgorithmFile);

    if ($featuredAlgorithmContent === false) {
        throw new RuntimeException('Could not read includes/featured-algorithm.php.');
    }

    if (strpos($featuredAlgorithmContent, 'class KinasListingRotation') === false) {
        $featuredAlgorithmContent = preg_replace('/\?>\s*$/', '', $featuredAlgorithmContent);
        $featuredAlgorithmContent = rtrim((string)$featuredAlgorithmContent);
        $featuredAlgorithmContent .= PHP_EOL . PHP_EOL . $rotationEngineCode . PHP_EOL;

        kpf_write_file($featuredAlgorithmFile, $featuredAlgorithmContent);
        kpf_message('includes/featured-algorithm.php updated with rotation engine.', 'success');
    } else {
        kpf_message('includes/featured-algorithm.php already contains rotation engine.', 'info');
    }

    // ============================================================
    // 3. HOMEPAGE ROTATION REPLACEMENT
    // ============================================================

    $homepageRotationBlock = <<<'PHP'
// ============================================================
// PRODUCT ROTATION / FAIR VISIBILITY
// ============================================================
// Instead of showing the same featured products forever, rotate
// through all active listings across all divisions.
// ============================================================

$rotation = new KinasListingRotation($db);

$homeLimit = 12;
$homePerDivisionPool = 6;

$carIds = $rotation->getRotatingIds('car', $homePerDivisionPool);
$propertyIds = $rotation->getRotatingIds('property', $homePerDivisionPool);
$solarIds = $rotation->getRotatingIds('solar', $homePerDivisionPool);
$marketplaceIds = $rotation->getRotatingIds('marketplace', $homePerDivisionPool);

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
