<?php
/**
 * KINAS GROUP — User Messages (Fixed: $pdo→$db, uses `messages` table from schema)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// ── Mark message read ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $mid = (int)$_POST['message_id'];
        $db->prepare("UPDATE messages SET is_read=1 WHERE id=? AND (sender_id=? OR receiver_id=?)")->execute([$mid, $user_id, $user_id]);
    }
}

// ── Delete message ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $mid = (int)$_POST['message_id'];
        $db->prepare("DELETE FROM messages WHERE id=? AND (sender_id=? OR receiver_id=?)")->execute([$mid, $user_id, $user_id]);
    }
}

// ── Fetch user's messages (inbox + sent) ─────────────────────
$page = max(1,(int)($_GET['page']??1)); $limit=20; $offset=($page-1)*$limit;
$filter = $_GET['filter'] ?? 'inbox';
$whereClause = $filter === 'sent' ? "m.sender_id = $user_id" : "m.receiver_id = $user_id";

$msgs = $db->prepare("
    SELECT m.*, u_s.name as sender_name, u_r.name as receiver_name
    FROM messages m
    LEFT JOIN users u_s ON m.sender_id = u_s.id
    LEFT JOIN users u_r ON m.receiver_id = u_r.id
    WHERE $whereClause
    ORDER BY m.created_at DESC
    LIMIT $limit OFFSET $offset
");
$msgs->execute(); $msgs = $msgs->fetchAll();

$unread = (int)$db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0")->execute([$user_id])
    ? $db->query("SELECT COUNT(*) FROM messages WHERE receiver_id=$user_id AND is_read=0")->fetchColumn() : 0;

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Messages - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .user-container{max-width:900px;margin:0 auto;padding:30px}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A}
        .filter-tabs{display:flex;gap:8px}
        .tab{padding:8px 18px;border-radius:20px;border:1px solid #E0E0E0;background:white;color:#666;text-decoration:none;font-size:13px;font-weight:500;transition:all .2s}
        .tab.active,.tab:hover{background:#C6A43F;border-color:#C6A43F;color:#0A0A0A}
        .messages-list{background:white;border-radius:16px;border:1px solid #E0E0E0;overflow:hidden}
        .msg-item{display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid #F0F0F0;transition:background .2s}
        .msg-item:hover{background:#FEFBF5}
        .msg-item.unread{background:#FFFBEF;border-left:3px solid #C6A43F}
        .msg-avatar{width:42px;height:42px;border-radius:50%;background:#C6A43F;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0A0A0A;flex-shrink:0;font-size:14px}
        .msg-body{flex:1;min-width:0}
        .msg-from{font-weight:600;font-size:14px;color:#0A0A0A;margin-bottom:3px}
        .msg-preview{font-size:13px;color:#666;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
        .msg-time{font-size:11px;color:#999}
        .msg-actions{display:flex;gap:6px;align-items:flex-start;flex-shrink:0}
        .act-btn{width:28px;height:28px;border:none;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .2s}
        .act-btn.read{background:#E3F2FD;color:#1565C0}.act-btn.del{background:#FEF2F2;color:#DC2626}
        .act-btn:hover{transform:scale(1.1)}
        .empty-state{text-align:center;padding:60px 20px;color:#999}
        .empty-state i{font-size:3rem;color:#E0E0E0;margin-bottom:14px;display:block}
        .unread-badge{background:#C6A43F;color:#0A0A0A;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;margin-left:6px}
        @media(max-width:640px){.page-header{flex-direction:column;align-items:flex-start}.msg-actions{display:none}}
    </style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php' ?>
<main style="padding-top:80px">
<div class="user-container">
    <div class="page-header">
        <h1><i class="fas fa-envelope" style="color:#C6A43F;margin-right:10px"></i>Messages
            <?php if ($unread > 0): ?><span class="unread-badge"><?= $unread ?></span><?php endif; ?>
        </h1>
        <div class="filter-tabs">
            <a class="tab <?= $filter==='inbox'?'active':'' ?>" href="?filter=inbox">Inbox</a>
            <a class="tab <?= $filter==='sent'?'active':'' ?>" href="?filter=sent">Sent</a>
        </div>
    </div>

    <div class="messages-list">
        <?php if (empty($msgs)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p><?= $filter==='inbox' ? 'Your inbox is empty. Messages from agents will appear here.' : 'You haven\'t sent any messages yet.' ?></p>
        </div>
        <?php else: ?>
        <?php foreach ($msgs as $m):
            $other = $filter==='inbox' ? ($m['sender_name'] ?? 'Unknown') : ($m['receiver_name'] ?? 'Agent');
            $initials = strtoupper(substr($other,0,1));
            $isUnread  = !$m['is_read'] && $filter==='inbox';
        ?>
        <div class="msg-item <?= $isUnread?'unread':'' ?>">
            <div class="msg-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="msg-body">
                <div class="msg-from"><?= htmlspecialchars($other) ?></div>
                <div class="msg-preview"><?= htmlspecialchars(substr($m['message'],0,100)) ?></div>
                <div class="msg-time"><?= date('M j, Y g:ia', strtotime($m['created_at'])) ?></div>
            </div>
            <div class="msg-actions">
                <form method="POST" style="display:contents">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
                    <?php if ($isUnread): ?>
                    <button class="act-btn read" name="mark_read" value="1" title="Mark read"><i class="fas fa-check"></i></button>
                    <?php endif; ?>
                    <button class="act-btn del" name="delete" value="1" title="Delete" onclick="return confirm('Delete this message?')"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
