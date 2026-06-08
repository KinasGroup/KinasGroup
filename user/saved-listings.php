<?php
/**
 * KINAS GROUP — Saved Listings (Fixed: uses saved_listings table from dashboard_patch.sql)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// ── Remove saved listing ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $lid  = (int)$_POST['listing_id'];
    $type = $_POST['listing_type'] ?? 'car';
    if (in_array($type,['car','property','solar','marketplace'])) {
        $db->prepare("DELETE FROM saved_listings WHERE user_id=? AND listing_id=? AND listing_type=?")
           ->execute([$user_id, $lid, $type]);
    }
}

// ── Fetch saved listings with UNION across division tables ───
$saved = $db->prepare("
    SELECT 'car' AS listing_type, sl.listing_id, sl.saved_at,
           cl.title, cl.price, cl.location, cl.status
    FROM saved_listings sl
    JOIN car_listings cl ON sl.listing_id = cl.id
    WHERE sl.user_id = ? AND sl.listing_type = 'car'

    UNION ALL

    SELECT 'property', sl.listing_id, sl.saved_at,
           pl.title, pl.price, pl.city, pl.status
    FROM saved_listings sl
    JOIN property_listings pl ON sl.listing_id = pl.id
    WHERE sl.user_id = ? AND sl.listing_type = 'property'

    UNION ALL

    SELECT 'solar', sl.listing_id, sl.saved_at,
           sol.title, sol.price, 'Solar' AS location, sol.status
    FROM saved_listings sl
    JOIN solar_listings sol ON sl.listing_id = sol.id
    WHERE sl.user_id = ? AND sl.listing_type = 'solar'

    UNION ALL

    SELECT 'marketplace', sl.listing_id, sl.saved_at,
           ml.title, ml.price, 'Marketplace' AS location, ml.status
    FROM saved_listings sl
    JOIN marketplace_listings ml ON sl.listing_id = ml.id
    WHERE sl.user_id = ? AND sl.listing_type = 'marketplace'

    ORDER BY saved_at DESC
");
$saved->execute([$user_id, $user_id, $user_id, $user_id]);
$items = $saved->fetchAll();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Saved Listings - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .user-container{max-width:1100px;margin:0 auto;padding:30px}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
        .listings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px}
        .listing-card{background:white;border-radius:16px;border:1px solid #E0E0E0;overflow:hidden;transition:all .3s}
        .listing-card:hover{transform:translateY(-4px);border-color:#C6A43F;box-shadow:0 8px 24px rgba(0,0,0,.08)}
        .listing-img{height:180px;background:linear-gradient(135deg,#F5F7FA,#E0E0E0);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .listing-img i{font-size:3rem;color:#C6A43F}
        .listing-type-badge{position:absolute;top:10px;left:10px;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:600;background:rgba(198,164,63,.9);color:#0A0A0A}
        .listing-body{padding:18px}
        .listing-title{font-size:15px;font-weight:600;color:#0A0A0A;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .listing-price{font-size:18px;font-weight:700;color:#C6A43F;margin-bottom:6px}
        .listing-meta{font-size:12px;color:#999;margin-bottom:14px}
        .listing-meta i{margin-right:4px;color:#C6A43F}
        .listing-actions{display:flex;justify-content:space-between;align-items:center}
        .btn-view{background:#F5F7FA;color:#333;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;border:1px solid #E0E0E0;transition:all .2s}
        .btn-view:hover{background:#E0E0E0}
        .btn-unsave{background:#FEF2F2;color:#DC2626;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;transition:all .2s}
        .btn-unsave:hover{background:#FECACA}
        .empty-state{text-align:center;padding:70px 20px;background:white;border-radius:16px;border:1px solid #E0E0E0;color:#999}
        .empty-state i{font-size:3rem;color:#E0E0E0;margin-bottom:14px;display:block}
        .empty-state a{color:#C6A43F;text-decoration:none;font-weight:600}
        @media(max-width:640px){.listings-grid{grid-template-columns:1fr}}
    </style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php' ?>
<main style="padding-top:80px">
<div class="user-container">
    <div class="page-header">
        <h1><i class="fas fa-heart" style="color:#C6A43F;margin-right:10px"></i>Saved Listings</h1>
        <span style="color:#666;font-size:14px"><?= count($items) ?> saved item<?= count($items)!==1?'s':'' ?></span>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="fas fa-heart-broken"></i>
        <p>You haven't saved any listings yet.<br>Browse our <a href="/">divisions</a> and click the heart icon to save items here.</p>
    </div>
    <?php else: ?>
    <div class="listings-grid">
        <?php foreach ($items as $item):
            $icons = ['car'=>'fa-car','property'=>'fa-home','solar'=>'fa-solar-panel','marketplace'=>'fa-store'];
            $icon  = $icons[$item['listing_type']] ?? 'fa-tag';
            $label = ucfirst($item['listing_type']);
        ?>
        <div class="listing-card">
            <div class="listing-img">
                <i class="fas <?= $icon ?>"></i>
                <span class="listing-type-badge"><?= $label ?></span>
            </div>
            <div class="listing-body">
                <div class="listing-title" title="<?= htmlspecialchars($item['title']) ?>"><?= htmlspecialchars($item['title']) ?></div>
                <div class="listing-price">₦<?= number_format($item['price']) ?></div>
                <div class="listing-meta">
                    <i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($item['location'] ?? '—') ?>
                    &nbsp;·&nbsp; Saved <?= date('M j', strtotime($item['saved_at'])) ?>
                </div>
                <div class="listing-actions">
                    <a class="btn-view" href="/<?= $item['listing_type'] === 'car' ? 'divisions/kinas-automobile' : ($item['listing_type'] === 'property' ? 'divisions/williams-connect-home' : 'divisions/'.$item['listing_type']) ?>/listing.php?id=<?= $item['listing_id'] ?>">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="listing_id" value="<?= $item['listing_id'] ?>">
                        <input type="hidden" name="listing_type" value="<?= $item['listing_type'] ?>">
                        <button class="btn-unsave" onclick="return confirm('Remove from saved?')"><i class="fas fa-heart-broken"></i> Unsave</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>


</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
