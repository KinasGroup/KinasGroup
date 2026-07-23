<?php
/**
 * KINAS AUTOMOBILE — Compare vehicles side by side
 */
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../api/config/database.php';
require_once '../../includes/je-components.php';
require_once '../../includes/security.php';

$db = Database::getInstance()->getConnection();

// Parse and sanitize the comma-separated id list, capped at 4.
$idsParam = $_GET['ids'] ?? '';
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsParam)))));
$ids = array_slice($ids, 0, 4);

$cars = [];
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail
        FROM car_listings c
        WHERE c.id IN ($placeholders) AND c.status = 'active'
    ");
    $stmt->execute($ids);
    $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preserve the order the user selected them in, not the SQL result order.
    $byId = [];
    foreach ($fetched as $row) { $byId[(int)$row['id']] = $row; }
    foreach ($ids as $id) {
        if (isset($byId[$id])) $cars[] = $byId[$id];
    }
}

include '../../templates/header.php';

$specRows = [
    ['label' => 'Price',        'key' => 'price',            'format' => 'price'],
    ['label' => 'Year',         'key' => 'year',              'format' => 'plain'],
    ['label' => 'Mileage',      'key' => 'mileage',           'format' => 'mileage'],
    ['label' => 'Body Type',    'key' => 'body_type',         'format' => 'plain'],
    ['label' => 'Fuel Type',    'key' => 'fuel_type',         'format' => 'plain'],
    ['label' => 'Transmission', 'key' => 'transmission',      'format' => 'plain'],
    ['label' => 'Drivetrain',   'key' => 'drivetrain',        'format' => 'plain'],
    ['label' => 'Doors',        'key' => 'doors',             'format' => 'plain'],
    ['label' => 'Color',        'key' => 'color',             'format' => 'plain'],
    ['label' => 'Condition',    'key' => 'condition_status',  'format' => 'plain'],
    ['label' => 'Location',     'key' => 'location',          'format' => 'location'],
];

function compareFormat($car, $row) {
    $val = $car[$row['key']] ?? null;
    if ($val === null || $val === '') return '<span style="color:#bbb;">—</span>';
    switch ($row['format']) {
        case 'price':   return '₦' . number_format((float)$val);
        case 'mileage': return number_format((int)$val) . ' km';
        case 'location':
            $loc = trim(($car['city'] ?? '') . ', ' . ($car['state'] ?? ''), ', ');
            return $loc !== '' ? htmlspecialchars($loc) : '<span style="color:#bbb;">—</span>';
        default: return htmlspecialchars((string)$val);
    }
}
?>
<style>
.cmp-wrap { max-width: 1200px; margin: 0 auto; padding: 40px 20px 80px; }
.cmp-header { text-align: center; margin-bottom: 32px; }
.cmp-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #151515; margin-bottom: 8px; }
.cmp-header p { color: #717171; font-size: 14px; }
.cmp-table-scroll { overflow-x: auto; }
.cmp-table { width: 100%; border-collapse: collapse; min-width: 640px; }
.cmp-table th, .cmp-table td { padding: 14px 16px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
.cmp-table th { background: #faf9f6; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; width: 160px; }
.cmp-car-col { min-width: 200px; }
.cmp-car-img { width: 100%; height: 130px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; background: #f0f0f0; }
.cmp-car-title { font-weight: 700; font-size: 14px; color: #151515; margin-bottom: 8px; }
.cmp-car-link { display: inline-block; margin-top: 4px; font-size: 12px; color: #C6A43F; font-weight: 600; text-decoration: none; }
.cmp-empty { text-align: center; padding: 60px 20px; color: #999; }
.cmp-empty a { color: #C6A43F; font-weight: 600; }
</style>

<div class="cmp-wrap">
    <div class="cmp-header">
        <h1>Compare Vehicles</h1>
        <p>Side-by-side specs for the vehicles you selected.</p>
    </div>

    <?php if (count($cars) < 2): ?>
        <div class="cmp-empty">
            <p>Select at least 2 vehicles from search results to compare them here.</p>
            <a href="search.php">← Back to search</a>
        </div>
    <?php else: ?>
    <div class="cmp-table-scroll">
        <table class="cmp-table">
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ($cars as $car): ?>
                        <th class="cmp-car-col" style="text-transform:none;">
                            <img class="cmp-car-img" src="<?= htmlspecialchars($car['thumbnail'] ?: '/assets/images/placeholder/product-placeholder.svg') ?>"
                                 alt="<?= htmlspecialchars($car['title']) ?>"
                                 onerror="this.onerror=null;this.src='/assets/images/placeholder/product-placeholder.svg';">
                            <div class="cmp-car-title"><?= htmlspecialchars($car['title']) ?></div>
                            <a class="cmp-car-link" href="detail.php?id=<?= (int)$car['id'] ?>">View Details →</a>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specRows as $row): ?>
                <tr>
                    <th><?= htmlspecialchars($row['label']) ?></th>
                    <?php foreach ($cars as $car): ?>
                        <td><?= compareFormat($car, $row) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../../templates/footer.php'; ?>
