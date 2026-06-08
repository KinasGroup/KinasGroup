<?php
/**
 * KINAS GROUP — My Inquiries (Fixed: uses inquiries table from dashboard_patch.sql)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Mark inquiry read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $iid = (int)($_POST['inquiry_id'] ?? 0);
    if ($iid) $db->prepare("UPDATE inquiries SET is_read=1 WHERE id=? AND user_id=?")->execute([$iid, $user_id]);
}

$inquiries = $db->prepare("
    SELECT i.*,
           u.name AS agent_name, u.email AS agent_email,
           COALESCE(cl.title, pl.title, sol.title, ml.title) AS listing_title
    FROM inquiries i
    LEFT JOIN users u ON i.agent_id = u.id
    LEFT JOIN car_listings cl         ON i.listing_id = cl.id AND i.listing_type = 'car'
    LEFT JOIN property_listings pl    ON i.listing_id = pl.id AND i.listing_type = 'property'
    LEFT JOIN solar_listings sol      ON i.listing_id = sol.id AND i.listing_type = 'solar'
    LEFT JOIN marketplace_listings ml ON i.listing_id = ml.id AND i.listing_type = 'marketplace'
    WHERE i.user_id = ?
    ORDER BY i.created_at DESC
");
$inquiries->execute([$user_id]);
$items = $inquiries->fetchAll();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Inquiries - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .user-container{max-width:900px;margin:0 auto;padding:30px}
        .page-header{margin-bottom:24px}.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A;margin-bottom:4px}.page-header p{color:#666;font-size:14px}
        .inq-card{background:white;border-radius:14px;border:1px solid #E0E0E0;margin-bottom:16px;overflow:hidden;transition:all .3s}
        .inq-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.07)}
        .inq-card.unread{border-left:4px solid #C6A43F}
        .inq-header{display:flex;justify-content:space-between;align-items:flex-start;padding:16px 20px;background:#F8F8F8;border-bottom:1px solid #E0E0E0;flex-wrap:wrap;gap:10px}
        .inq-listing{font-size:15px;font-weight:600;color:#0A0A0A}
        .inq-agent{font-size:12px;color:#666;margin-top:3px}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600}
        .status-badge.new{background:#FFF3E0;color:#F57C00}
        .status-badge.replied{background:#E8F5E9;color:#2E7D32}
        .status-badge.read{background:#E3F2FD;color:#1565C0}
        .status-badge.closed{background:#F5F5F5;color:#666}
        .inq-body{padding:16px 20px}
        .inq-message{font-size:13px;color:#333;margin-bottom:10px;line-height:1.6}
        .inq-reply{margin-top:12px;padding:12px;background:#F5F7FA;border-radius:10px;border-left:3px solid #C6A43F}
        .inq-reply p{font-size:13px;color:#333}
        .inq-reply strong{color:#C6A43F;display:block;margin-bottom:4px;font-size:12px}
        .inq-footer{display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid #F0F0F0}
        .inq-time{font-size:11px;color:#999}
        .btn-read{background:#E3F2FD;color:#1565C0;border:none;padding:6px 14px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600}
        .empty-state{text-align:center;padding:60px 20px;background:white;border-radius:14px;border:1px solid #E0E0E0;color:#999}
        .empty-state i{font-size:2.5rem;color:#E0E0E0;margin-bottom:12px;display:block}
        @media(max-width:640px){.user-container{padding:20px}}
    </style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php' ?>
<main style="padding-top:80px">
<div class="user-container">
    <div class="page-header">
        <h1><i class="fas fa-comments" style="color:#C6A43F;margin-right:10px"></i>My Inquiries</h1>
        <p>Track your messages and agent replies</p>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="fas fa-comment-slash"></i>
        <p>You haven't sent any inquiries yet.<br>Contact an agent from any listing to get started.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $inq): ?>
    <div class="inq-card <?= (!$inq['is_read'] && $inq['status']==='new') ? 'unread' : '' ?>">
        <div class="inq-header">
            <div>
                <div class="inq-listing"><?= htmlspecialchars($inq['listing_title'] ?? 'Deleted listing') ?></div>
                <div class="inq-agent">Agent: <?= htmlspecialchars($inq['agent_name'] ?? '—') ?></div>
            </div>
            <span class="status-badge <?= $inq['status'] ?>"><?= ucfirst($inq['status']) ?></span>
        </div>
        <div class="inq-body">
            <p class="inq-message"><?= htmlspecialchars($inq['message']) ?></p>
            <?php if ($inq['reply']): ?>
            <div class="inq-reply">
                <strong>Agent Reply · <?= date('M j, Y', strtotime($inq['replied_at'])) ?></strong>
                <p><?= htmlspecialchars($inq['reply']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="inq-footer">
            <span class="inq-time"><?= date('M j, Y g:ia', strtotime($inq['created_at'])) ?></span>
            <?php if ($inq['status'] === 'new'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                <button class="btn-read"><i class="fas fa-check"></i> Mark Read</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>


</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
