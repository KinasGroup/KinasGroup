<?php
/**
 * KINAS GROUP — My Inquiries
 * FIXED: Handles individual inquiry view via id parameter
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Get specific inquiry ID if viewing a single inquiry
$inquiryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$viewingInquiry = $inquiryId > 0;

// Mark inquiry as read if viewing it
if ($viewingInquiry) {
    $db->prepare("UPDATE inquiries SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$inquiryId, $user_id]);
}

// Mark inquiry read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $iid = (int)($_POST['inquiry_id'] ?? 0);
    if ($iid) {
        $db->prepare("UPDATE inquiries SET is_read=1, status='read' WHERE id=? AND user_id=?")->execute([$iid, $user_id]);
        // Redirect to refresh
        header('Location: /user/my-inquiries.php');
        exit;
    }
}

// If viewing a specific inquiry, get just that one
if ($viewingInquiry) {
    $stmt = $db->prepare("
        SELECT i.*,
               u.name AS agent_name, u.email AS agent_email,
               COALESCE(cl.title, pl.title, sol.title, ml.title) AS listing_title,
               CASE 
                   WHEN cl.id IS NOT NULL THEN 'car'
                   WHEN pl.id IS NOT NULL THEN 'property'
                   WHEN sol.id IS NOT NULL THEN 'solar'
                   WHEN ml.id IS NOT NULL THEN 'marketplace'
               END AS listing_type
        FROM inquiries i
        LEFT JOIN users u ON i.agent_id = u.id
        LEFT JOIN car_listings cl ON i.listing_id = cl.id AND i.listing_type = 'car'
        LEFT JOIN property_listings pl ON i.listing_id = pl.id AND i.listing_type = 'property'
        LEFT JOIN solar_listings sol ON i.listing_id = sol.id AND i.listing_type = 'solar'
        LEFT JOIN marketplace_listings ml ON i.listing_id = ml.id AND i.listing_type = 'marketplace'
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$inquiryId, $user_id]);
    $singleInquiry = $stmt->fetch();
    
    if (!$singleInquiry) {
        header('Location: /user/my-inquiries.php?error=notfound');
        exit;
    }
}

// Get all inquiries for the user (for the list view)
$inquiries = $db->prepare("
    SELECT i.*,
           u.name AS agent_name, u.email AS agent_email,
           COALESCE(cl.title, pl.title, sol.title, ml.title) AS listing_title
    FROM inquiries i
    LEFT JOIN users u ON i.agent_id = u.id
    LEFT JOIN car_listings cl ON i.listing_id = cl.id AND i.listing_type = 'car'
    LEFT JOIN property_listings pl ON i.listing_id = pl.id AND i.listing_type = 'property'
    LEFT JOIN solar_listings sol ON i.listing_id = sol.id AND i.listing_type = 'solar'
    LEFT JOIN marketplace_listings ml ON i.listing_id = ml.id AND i.listing_type = 'marketplace'
    WHERE i.user_id = ?
    ORDER BY i.created_at DESC
");
$inquiries->execute([$user_id]);
$items = $inquiries->fetchAll();

$csrf = Security::generateCSRFToken();
$pageTitle = $viewingInquiry ? 'Inquiry Details - KINAS GROUP' : 'My Inquiries - KINAS GROUP';
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>

<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
.user-container{max-width:1100px;margin:0 auto;padding:30px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
.page-header h1 i{color:#C6A43F;margin-right:10px}
.page-header .back-link{color:#C6A43F;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:8px}
.page-header .back-link:hover{color:#A8882E}

.inq-card{background:white;border-radius:14px;border:1px solid #E0E0E0;margin-bottom:16px;overflow:hidden;transition:all .3s}
.inq-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.07)}
.inq-card.unread{border-left:4px solid #C6A43F}
.inq-header{display:flex;justify-content:space-between;align-items:flex-start;padding:16px 20px;background:#F8F8F8;border-bottom:1px solid #E0E0E0;flex-wrap:wrap;gap:10px}
.inq-listing{font-size:15px;font-weight:600;color:#0A0A0A}
.inq-agent{font-size:12px;color:#666;margin-top:3px}
.inq-agent i{color:#C6A43F;margin-right:4px}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600}
.status-badge.new{background:#FFF3E0;color:#F57C00}
.status-badge.replied{background:#E8F5E9;color:#2E7D32}
.status-badge.read{background:#E3F2FD;color:#1565C0}
.status-badge.closed{background:#F5F5F5;color:#666}
.inq-body{padding:16px 20px}
.inq-message{font-size:13px;color:#333;margin-bottom:10px;line-height:1.6;white-space:pre-wrap}
.inq-reply{margin-top:12px;padding:12px 16px;background:#F5F7FA;border-radius:10px;border-left:3px solid #C6A43F}
.inq-reply p{font-size:13px;color:#333;margin:0}
.inq-reply strong{color:#C6A43F;display:block;margin-bottom:4px;font-size:12px}
.inq-footer{display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-top:1px solid #F0F0F0;flex-wrap:wrap;gap:8px}
.inq-time{font-size:11px;color:#999}
.btn-read{background:#E3F2FD;color:#1565C0;border:none;padding:6px 14px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600;transition:all .2s}
.btn-read:hover{background:#BBDEFB}
.btn-view{background:#C6A43F;color:#0A0A0A;padding:6px 14px;border-radius:7px;text-decoration:none;font-size:12px;font-weight:600;transition:all .2s;display:inline-block}
.btn-view:hover{background:#A8882E;transform:translateY(-2px)}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:14px;border:1px solid #E0E0E0;color:#999}
.empty-state i{font-size:2.5rem;color:#E0E0E0;margin-bottom:12px;display:block}
.detail-section{margin-bottom:12px}
.detail-label{font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:0.5px}
.detail-value{font-size:14px;color:#333;margin-top:2px}
.divider{border:none;border-top:1px solid #E8E8E8;margin:12px 0}
@media(max-width:640px){.user-container{padding:20px}}
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

<main style="padding-top:80px">
<div class="user-container">

    <?php if ($viewingInquiry && $singleInquiry): ?>
    <!-- ============================================================ -->
    <!-- SINGLE INQUIRY VIEW -->
    <!-- ============================================================ -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-envelope"></i> Inquiry Details</h1>
        </div>
        <a href="/user/my-inquiries.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Inquiries</a>
    </div>

    <div class="inq-card">
        <div class="inq-header">
            <div>
                <div class="inq-listing"><?= htmlspecialchars($singleInquiry['listing_title'] ?? 'Deleted listing') ?></div>
                <div class="inq-agent"><i class="fas fa-user-circle"></i> Agent: <?= htmlspecialchars($singleInquiry['agent_name'] ?? '—') ?></div>
                <div class="inq-agent"><i class="fas fa-envelope"></i> Email: <?= htmlspecialchars($singleInquiry['agent_email'] ?? '—') ?></div>
            </div>
            <span class="status-badge <?= $singleInquiry['status'] ?>"><?= ucfirst($singleInquiry['status'] ?? 'New') ?></span>
        </div>
        <div class="inq-body">
            <div class="detail-section">
                <div class="detail-label">Your Message</div>
                <div class="detail-value"><?= nl2br(htmlspecialchars($singleInquiry['message'] ?? '')) ?></div>
            </div>
            
            <?php if (!empty($singleInquiry['reply'])): ?>
                <hr class="divider">
                <div class="detail-section">
                    <div class="detail-label">Agent Reply</div>
                    <div class="inq-reply" style="margin-top:0;">
                        <strong><i class="fas fa-reply"></i> Reply from <?= htmlspecialchars($singleInquiry['agent_name'] ?? 'Agent') ?> · <?= date('M j, Y g:ia', strtotime($singleInquiry['replied_at'])) ?></strong>
                        <p><?= nl2br(htmlspecialchars($singleInquiry['reply'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <hr class="divider">
            <div style="display:flex; flex-wrap:wrap; gap:15px; font-size:13px; color:#666;">
                <span><i class="far fa-calendar-alt"></i> Sent: <?= date('M j, Y g:ia', strtotime($singleInquiry['created_at'])) ?></span>
                <?php if ($singleInquiry['listing_type']): ?>
                    <span><i class="fas fa-tag"></i> Type: <?= ucfirst($singleInquiry['listing_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($singleInquiry['listing_id'])): ?>
                    <span><i class="fas fa-hashtag"></i> Listing ID: #<?= $singleInquiry['listing_id'] ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="inq-footer">
            <span class="inq-time"><i class="far fa-clock"></i> <?= time_ago($singleInquiry['created_at']) ?></span>
            <?php if ($singleInquiry['status'] === 'new'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="inquiry_id" value="<?= $singleInquiry['id'] ?>">
                    <button class="btn-read"><i class="fas fa-check"></i> Mark Read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- ALL INQUIRIES LIST VIEW -->
    <!-- ============================================================ -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-comments"></i> My Inquiries</h1>
            <p style="color:#666;font-size:14px;margin-top:4px;">Track your messages and agent replies</p>
        </div>
        <span style="color:#666;font-size:14px;"><?= count($items) ?> inquiry<?= count($items)!==1?'s':'' ?></span>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'notfound'): ?>
        <div style="background:#FFF3E0;border:1px solid #FFE0B2;color:#E65100;padding:12px 18px;border-radius:8px;margin-bottom:16px;">
            <i class="fas fa-exclamation-triangle"></i> Inquiry not found or you don't have permission to view it.
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="fas fa-comment-slash"></i>
        <p>You haven't sent any inquiries yet.<br>Contact an agent from any listing to get started.</p>
        <a href="/" class="btn-view" style="display:inline-block;margin-top:16px;">Browse Listings</a>
    </div>
    <?php else: ?>
    <?php foreach ($items as $inq): ?>
    <div class="inq-card <?= (!$inq['is_read'] && $inq['status']==='new') ? 'unread' : '' ?>">
        <div class="inq-header">
            <div>
                <div class="inq-listing"><?= htmlspecialchars($inq['listing_title'] ?? 'Deleted listing') ?></div>
                <div class="inq-agent"><i class="fas fa-user-circle"></i> Agent: <?= htmlspecialchars($inq['agent_name'] ?? '—') ?></div>
            </div>
            <span class="status-badge <?= $inq['status'] ?>"><?= ucfirst($inq['status'] ?? 'New') ?></span>
        </div>
        <div class="inq-body">
            <p class="inq-message"><?= htmlspecialchars(substr($inq['message'] ?? '', 0, 150)) . (strlen($inq['message'] ?? '') > 150 ? '...' : '') ?></p>
            <?php if (!empty($inq['reply'])): ?>
            <div class="inq-reply">
                <strong><i class="fas fa-reply"></i> Agent Reply · <?= date('M j, Y', strtotime($inq['replied_at'])) ?></strong>
                <p><?= htmlspecialchars(substr($inq['reply'], 0, 100)) . (strlen($inq['reply']) > 100 ? '...' : '') ?></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="inq-footer">
            <span class="inq-time"><i class="far fa-clock"></i> <?= time_ago($inq['created_at']) ?></span>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="my-inquiries.php?id=<?= $inq['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                <?php if ($inq['status'] === 'new'): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                    <button class="btn-read"><i class="fas fa-check"></i> Mark Read</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <?php endif; ?>

</div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
