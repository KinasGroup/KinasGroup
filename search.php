<?php
/**
 * Global Search Page - KINAS GROUP
 * Searches across all divisions with images
 */

require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/helpers.php';
require_once 'api/config/database.php';
require_once 'includes/je-components.php';

$db = Database::getInstance()->getConnection();

// Get search parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$division = isset($_GET['division']) ? $_GET['division'] : 'all';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$searchTerm = '%' . $query . '%';
$hasFilter = ($query !== '' || $division !== 'all');
$results = [];
$totalCount = 0;

// Division folder mapping for links
$divisionFolders = [
    'car' => 'kinas-automobile',
    'solar' => 'kinas-volt',
    'property' => 'williams-connect-home',
    'marketplace' => 'kinas-marketplace'
];

// Function to get image for a listing
function getListingImage($db, $listingId, $divisionName) {
    $tableMap = [
        'car' => 'car_listings',
        'solar' => 'solar_listings',
        'property' => 'property_listings',
        'marketplace' => 'marketplace_listings'
    ];
    
    $table = $tableMap[$divisionName] ?? '';
    if (empty($table)) return null;
    
    try {
        $stmt = $db->prepare("
            SELECT url FROM listing_images 
            WHERE listing_id = ? AND listing_type = ? 
            ORDER BY sort_order LIMIT 1
        ");
        $stmt->execute([$listingId, $divisionName]);
        $image = $stmt->fetch();
        
        if ($image) {
            return $image['url'];
        }
        
        $stmt2 = $db->prepare("SELECT thumbnail FROM $table WHERE id = ?");
        $stmt2->execute([$listingId]);
        $thumb = $stmt2->fetch();
        
        if ($thumb && !empty($thumb['thumbnail'])) {
            return $thumb['thumbnail'];
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// Function to search a specific table
function searchTable($db, $table, $divisionName, $searchTerm, $rawQuery, $offset, $perPage) {
    $results = [];
    $count = 0;
    
    try {
        $searchFields = [];
        $categoryJoin = '';
        switch ($table) {
            case 'car_listings':
                $searchFields = ['title', 'brand', 'model', 'body_type', 'fuel_type', 'transmission', 'city', 'state'];
                break;
            case 'solar_listings':
                $searchFields = ['title', 'brand', 'service_type', 'city', 'state', 'description'];
                break;
            case 'property_listings':
                $searchFields = ['title', 'address', 'city', 'state', 'description'];
                break;
            case 'marketplace_listings':
                // category_id is a FK (no text column on this table itself),
                // so category name search needs a join to marketplace_categories
                // rather than a plain field like the others below.
                $searchFields = ['title', 'brand', 'description', 'city', 'state', 'mc.name'];
                $categoryJoin = 'LEFT JOIN marketplace_categories mc ON mc.id = t.category_id';
                break;
            default:
                $searchFields = ['title'];
        }
        
        $whereClauses = [];
        foreach ($searchFields as $field) {
            $col = str_contains($field, '.') ? $field : "t.$field";
            $whereClauses[] = "$col LIKE ?";
        }
        $whereSQL = implode(' OR ', $whereClauses);
        
        // property_listings has no 'brand' column (only car/solar/marketplace
        // do). Selecting it unconditionally threw a SQL error on every
        // Homes search, which the catch block silently swallowed, making
        // Homes search always return zero results.
        $brandColumn = ($table === 'property_listings') ? 'NULL' : 't.brand';

        // Relevance ranking: an exact title match ranks highest, then a
        // title that STARTS WITH the query, then any other field match.
        // Without this, results were ordered purely by recency — a
        // perfect title match could rank below an unrelated newer
        // listing that only loosely matched on a description field.
        $relevanceSQL = "
            CASE
                WHEN t.title = ? THEN 0
                WHEN t.title LIKE ? THEN 1
                ELSE 2
            END
        ";
        
        $stmt = $db->prepare("
            SELECT 
                t.id, 
                t.title, 
                t.price, 
                t.status,
                t.views,
                t.created_at,
                '$divisionName' as division,
                '" . str_replace('_listings', '', $table) . "' as type,
                $brandColumn as brand,
                t.city,
                t.state
            FROM $table t
            $categoryJoin
            WHERE ($whereSQL) 
              AND t.status = 'active'
            ORDER BY $relevanceSQL ASC, t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params = [$rawQuery, $rawQuery . '%'];
        foreach ($searchFields as $field) {
            $params[] = $searchTerm;
        }
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM $table t
            $categoryJoin
            WHERE ($whereSQL) 
              AND t.status = 'active'
        ");
        $countParams = [];
        foreach ($searchFields as $field) {
            $countParams[] = $searchTerm;
        }
        $countStmt->execute($countParams);
        $count = $countStmt->fetch()['total'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Search error for $table: " . $e->getMessage());
    }
    
    return ['results' => $results, 'count' => $count];
}

// Search all divisions or specific one
$divisionsToSearch = [];
if ($division === 'all') {
    $divisionsToSearch = [
        'car_listings' => 'car',
        'solar_listings' => 'solar',
        'property_listings' => 'property',
        'marketplace_listings' => 'marketplace'
    ];
} else {
    $tableMap = [
        'car' => 'car_listings',
        'solar' => 'solar_listings',
        'property' => 'property_listings',
        'marketplace' => 'marketplace_listings'
    ];
    if (isset($tableMap[$division])) {
        $divisionsToSearch = [$tableMap[$division] => $division];
    }
}

$allResults = [];
foreach ($divisionsToSearch as $table => $divName) {
    $result = searchTable($db, $table, $divName, $searchTerm, $query, $offset, $perPage);
    
    foreach ($result['results'] as &$item) {
        $item['image'] = getListingImage($db, $item['id'], $item['division']);
        // Add the correct folder path for the link
        $item['folder'] = $divisionFolders[$item['division']] ?? $item['division'];
    }
    // Break the reference from the loop above — leaving it dangling means
    // the next foreach() that reuses the $item variable name (by value)
    // would silently write through this reference and corrupt whichever
    // array element it's still bound to.
    unset($item);
    
    $allResults = array_merge($allResults, $result['results']);
    $totalCount += $result['count'];
}

// Safety net: de-duplicate by division+id in case the same listing was
// ever picked up more than once (e.g. a stray duplicate row).
$seen = [];
$allResults = array_values(array_filter($allResults, function ($r) use (&$seen) {
    $key = $r['division'] . ':' . $r['id'];
    if (isset($seen[$key])) return false;
    $seen[$key] = true;
    return true;
}));

usort($allResults, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$paginatedResults = array_slice($allResults, $offset, $perPage);
$totalPages = ceil($totalCount / $perPage);

// No matches — suggest something rather than a dead end. Pulls a small
// spread of popular (most-viewed) active listings across divisions, or
// just the selected division if one was chosen, so the person isn't
// left with nothing to do but retype their search.
$suggestions = [];
if ($totalCount === 0 && $hasFilter) {
    $suggestDivisions = ($division !== 'all' && isset($tableMap[$division]))
        ? [$tableMap[$division] => $division]
        : $divisionsToSearch;
    foreach ($suggestDivisions as $sTable => $sDivName) {
        try {
            $categoryJoin = $sTable === 'marketplace_listings' ? 'LEFT JOIN marketplace_categories mc ON mc.id = t.category_id' : '';
            $stmt = $db->prepare("
                SELECT t.id, t.title, t.price, t.views, t.created_at,
                       '$sDivName' as division,
                       '" . str_replace('_listings', '', $sTable) . "' as type
                FROM $sTable t
                $categoryJoin
                WHERE t.status = 'active'
                ORDER BY t.views DESC, t.created_at DESC
                LIMIT 3
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $row['image'] = getListingImage($db, $row['id'], $row['division']);
                $row['folder'] = $divisionFolders[$row['division']] ?? $row['division'];
                $suggestions[] = $row;
            }
        } catch (Exception $e) {
            // Skip this division's suggestions on error rather than fail the page
        }
    }
    // Cap total suggestions shown regardless of how many divisions contributed
    $suggestions = array_slice($suggestions, 0, 8);
}

$pageTitle = 'Search Results - KINAS GROUP';
include 'templates/header.php';
?>

<style>
.search-result-card {
    display: flex;
    gap: 20px;
    background: #fff;
    border: 1px solid #E0E0E0;
    border-radius: 12px;
    padding: 16px;
    transition: all 0.3s ease;
    margin-bottom: 16px;
    align-items: flex-start;
}
.search-result-card:hover {
    border-color: #C6A43F;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.search-result-image {
    flex-shrink: 0;
    width: 160px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f0f0;
    position: relative;
}
.search-result-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.search-result-image .placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f0f0;
    color: #999;
    font-size: 32px;
}
.search-result-content {
    flex: 1;
    min-width: 0;
}
.search-result-title {
    font-size: 18px;
    font-weight: 600;
    color: #0A0A0A;
    text-decoration: none;
}
.search-result-title:hover {
    color: #C6A43F;
}
.search-result-division {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.division-car { background: #E3F2FD; color: #0D47A1; }
.division-solar { background: #FFF3E0; color: #E65100; }
.division-property { background: #E8F5E9; color: #1B5E20; }
.division-marketplace { background: #F3E5F5; color: #4A148C; }

.search-result-price {
    font-size: 20px;
    font-weight: 700;
    color: #C6A43F;
}
.search-result-meta {
    color: #888;
    font-size: 13px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.search-result-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.search-result-brand {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
}
.search-result-location {
    font-size: 13px;
    color: #888;
}
.search-result-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.search-result-actions .btn-view {
    display: inline-block;
    padding: 6px 16px;
    background: #C6A43F;
    color: #0A0A0A;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}
.search-result-actions .btn-view:hover {
    background: #A8882E;
}

@media (max-width: 768px) {
    .search-result-card {
        flex-direction: column;
        align-items: stretch;
    }
    .search-result-image {
        width: 100%;
        height: 180px;
    }
}

.status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.status-badge-active { background: #E8F5E9; color: #1B5E20; }

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
}
.pagination a, .pagination span {
    padding: 8px 16px;
    border: 1px solid #E0E0E0;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
}
.pagination a:hover {
    background: #C6A43F;
    color: #0A0A0A;
    border-color: #C6A43F;
}
.pagination .active {
    background: #C6A43F;
    color: #0A0A0A;
    border-color: #C6A43F;
}
.no-results {
    text-align: center;
    padding: 80px 20px;
    background: #F8F8F8;
    border-radius: 12px;
}
.no-results i {
    font-size: 48px;
    color: #C6A43F;
    margin-bottom: 16px;
    display: block;
}
</style>

<div style="max-width: 1400px; margin: 100px auto 40px; padding: 0 40px;">
    <h1 style="font-family: 'Prata', serif; font-size: 32px; margin-bottom: 20px;">
        <i class="fas fa-search" style="color: #C6A43F;"></i> 
        Search Results
    </h1>
    
    <form method="GET" action="search.php" style="display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
               placeholder="Search for cars, properties, solar, products..." 
               style="flex: 1; min-width: 280px; padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; font-size: 16px; font-family: 'Inter', sans-serif;">
        <select name="division" style="padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; background: #fff; min-width: 150px; font-family: 'Inter', sans-serif;">
            <option value="all" <?php echo $division === 'all' ? 'selected' : ''; ?>>All Divisions</option>
            <option value="car" <?php echo $division === 'car' ? 'selected' : ''; ?>>🚗 Automobile</option>
            <option value="solar" <?php echo $division === 'solar' ? 'selected' : ''; ?>>☀️ Volt</option>
            <option value="property" <?php echo $division === 'property' ? 'selected' : ''; ?>>🏠 Homes</option>
            <option value="marketplace" <?php echo $division === 'marketplace' ? 'selected' : ''; ?>>🛍️ Marketplace</option>
        </select>
        <button type="submit" style="padding: 14px 32px; background: #C6A43F; color: #0A0A0A; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
    
    <?php if ($hasFilter): ?>
        <p style="color: #666; margin-bottom: 30px;">
            <?php if ($query): ?>
                <strong><?php echo $totalCount; ?></strong> result<?php echo $totalCount !== 1 ? 's' : ''; ?> for
                "<strong><?php echo htmlspecialchars($query); ?></strong>"
                <?php if ($division !== 'all'): ?>
                    in <strong><?php echo ucfirst($division); ?></strong>
                <?php endif; ?>
            <?php else: ?>
                Browsing <strong><?php echo $totalCount; ?></strong> listing<?php echo $totalCount !== 1 ? 's' : ''; ?>
                in <strong><?php echo $division === 'all' ? 'All Divisions' : ucfirst($division); ?></strong>
            <?php endif; ?>
        </p>
        
        <?php if (!empty($paginatedResults)): ?>
            <?php foreach ($paginatedResults as $item): ?>
                <div class="search-result-card">
                    <div class="search-result-image">
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="search-result-content">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <?php 
                                // Get the correct folder name for this division
                                $folderMap = [
                                    'car' => 'kinas-automobile',
                                    'solar' => 'kinas-volt',
                                    'property' => 'williams-connect-home',
                                    'marketplace' => 'kinas-marketplace'
                                ];
                                $folder = $folderMap[$item['division']] ?? $item['division'];
                                ?>
                                <a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $item['id']; ?>" 
                                   class="search-result-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                                <div style="margin-top: 4px;">
                                    <span class="search-result-division division-<?php echo $item['division']; ?>">
                                        <?php 
                                        $displayNames = [
                                            'car' => 'Automobile',
                                            'solar' => 'Volt',
                                            'property' => 'Homes',
                                            'marketplace' => 'Marketplace'
                                        ];
                                        echo $displayNames[$item['division']] ?? ucfirst($item['division']); 
                                        ?>
                                    </span>
                                    <span style="color: #888; font-size: 13px; margin-left: 8px;">
                                        <?php echo ucfirst($item['type']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($item['brand'])): ?>
                                    <div class="search-result-brand">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['brand']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['city']) || !empty($item['state'])): ?>
                                    <div class="search-result-location">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($item['city'] ?? ''); ?>
                                        <?php if (!empty($item['city']) && !empty($item['state'])): ?>, <?php endif; ?>
                                        <?php echo htmlspecialchars($item['state'] ?? ''); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="search-result-meta">
                                    <span><i class="far fa-clock"></i> <?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                                    <span><i class="far fa-eye"></i> <?php echo number_format($item['views'] ?? 0); ?> views</span>
                                    <span class="status-badge status-badge-<?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="search-result-price">₦<?php echo number_format($item['price']); ?></div>
                                <div class="search-result-actions">
                                    <a href="/divisions/<?php echo $folder; ?>/detail.php?id=<?php echo $item['id']; ?>" 
                                       class="btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $page - 1; ?>">‹ Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?php echo urlencode($query); ?>&division=<?php echo $division; ?>&page=<?php echo $page + 1; ?>">Next ›</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No results found</h3>
                <p style="color: #666;">We couldn't find anything matching "<?php echo htmlspecialchars($query); ?>". Try different keywords, or check out these popular listings instead.</p>
                <a href="search.php" style="color: #C6A43F; text-decoration: none; font-weight: 600;">Clear all filters</a>
            </div>

            <?php if (!empty($suggestions)): ?>
            <div style="margin-top:32px;">
                <h3 style="font-size:16px;color:#333;margin-bottom:16px;">You might like these</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
                    <?php
                    $folderMap = ['car' => 'kinas-automobile', 'solar' => 'kinas-volt', 'property' => 'williams-connect-home', 'marketplace' => 'kinas-marketplace'];
                    $displayNames = ['car' => 'Automobile', 'solar' => 'Volt', 'property' => 'Homes', 'marketplace' => 'Marketplace'];
                    ?>
                    <?php foreach ($suggestions as $sug): $sFolder = $folderMap[$sug['division']] ?? $sug['division']; ?>
                    <a href="/divisions/<?php echo $sFolder; ?>/detail.php?id=<?php echo (int)$sug['id']; ?>" style="display:block;border:1px solid #eee;border-radius:8px;overflow:hidden;text-decoration:none;color:inherit;transition:box-shadow .15s;">
                        <div style="height:130px;background:#f5f5f5;">
                            <?php if (!empty($sug['image'])): ?>
                                <img src="<?php echo htmlspecialchars($sug['image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                            <?php endif; ?>
                        </div>
                        <div style="padding:10px 12px;">
                            <div style="font-size:13px;font-weight:600;color:#151515;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($sug['title']); ?></div>
                            <div style="font-size:11px;color:#999;margin-top:2px;"><?php echo $displayNames[$sug['division']] ?? ucfirst($sug['division']); ?></div>
                            <?php if (!empty($sug['price'])): ?>
                                <div style="font-size:13px;color:#C6A43F;font-weight:700;margin-top:4px;">₦<?php echo number_format((float)$sug['price']); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
    <?php else: ?>
        <div style="background: #F8F8F8; padding: 80px 20px; text-align: center; border-radius: 12px;">
            <i class="fas fa-search" style="font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block;"></i>
            <h3>Search for anything on KINAS GROUP</h3>
            <p style="color: #666; max-width: 500px; margin: 0 auto;">Enter keywords above to search across all divisions: Automobile, Volt, Homes, and Marketplace.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>
