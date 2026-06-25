<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireLogin();

$page_title = 'My Dashboard';
$current_page = 'dashboard';
$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Simple stats - no complex grouping
$stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$stmt->execute([$user_id]);
$messages_sent = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$replies_received = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$saved_listings = $stmt->fetchColumn();

// Simple recent messages - just the last 5, no grouping
$stmt = $db->prepare("
    SELECT m.*, u.name AS other_name
    FROM messages m
    LEFT JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id = u.id ELSE m.sender_id = u.id END)
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id, $user_id]);
$recent_messages = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

include __DIR__ . '/../templates/header.php';
?>

<style>
.dashboard-container { max-width: 1200px; margin: 0 auto; padding: 30px; }
.welcome-banner { background: #0A0A0A; border-radius: 16px; padding: 30px 40px; color: #fff; margin-bottom: 30px; }
.welcome-banner h1 { font-family: 'Prata', serif; font-size: 28px; font-weight: 400; margin: 0; }
.welcome-banner p { color: rgba(255,255,255,0.7); margin: 4px 0 0 0; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e8e5e0; }
.stat-number { font-size: 28px; font-weight: 700; color: #0A0A0A; }
.stat-label { font-size: 13px; color: #888; margin-top: 4px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; }
.view-all { color: #C6A43F; text-decoration: none; font-weight: 600; }
.message-item { background: #fff; border-radius: 10px; padding: 16px 20px; border: 1px solid #e8e5e0; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
.message-item.unread { border-left: 4px solid #C6A43F; }
.message-item .sender { font-weight: 600; font-size: 14px; }
.message-item .preview { font-size: 13px; color: #666; margin-top: 2px; }
.message-item .time { font-size: 12px; color: #999; }
.empty-state { text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px solid #e8e5e0; }
.empty-state i { font-size: 40px; color: #ccc; margin-bottom: 16px; }
.quick-actions { background: #fff; border-radius: 12px; padding: 24px; margin-top: 30px; border: 1px solid #e8e5e0; }
.actions-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-top: 16px; }
.action-btn { background: #f8f6f1; padding: 16px; text-align: center; border-radius: 10px; text-decoration: none; transition: 0.2s; }
.action-btn:hover { background: #C6A43F; }
.action-btn i { font-size: 24px; color: #C6A43F; display: block; margin-bottom: 6px; }
.action-btn span { font-size: 13px; font-weight: 500; color: #333; }
.action-btn:hover i, .action-btn:hover span { color: #0A0A0A; }
@media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr 1fr; } .actions-grid { grid-template-columns: 1fr 1fr; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>
<main class="je-dash-main">
<div class="dashboard-container">
    <div class="welcome-banner">
        <h1>Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>! 👋</h1>
        <p>Your luxury marketplace journey continues here</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $saved_listings; ?></div>
            <div class="stat-label">Saved Listings</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $messages_sent; ?></div>
            <div class="stat-label">Messages Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $replies_received; ?></div>
            <div class="stat-label">Replies Received</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $unread_messages; ?></div>
            <div class="stat-label">Unread Messages</div>
        </div>
    </div>

    <div class="section-header">
        <h2 class="section-title">Recent Messages</h2>
        <a href="messages.php" class="view-all">View All →</a>
    </div>

    <?php if (empty($recent_messages)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No messages yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($recent_messages as $msg): 
            $isSender = ($msg['sender_id'] == $user_id);
            $otherName = $isSender ? ($msg['other_name'] ?? 'Agent') : ($msg['other_name'] ?? 'User');
            $unread = ($msg['receiver_id'] == $user_id && $msg['is_read'] == 0);
        ?>
        <a href="messages.php" class="message-item <?php echo $unread ? 'unread' : ''; ?>" style="text-decoration:none;color:inherit;">
            <div>
                <div class="sender"><?php echo htmlspecialchars($otherName); ?></div>
                <div class="preview"><?php echo htmlspecialchars(substr($msg['body'] ?? '', 0, 60)); ?></div>
            </div>
            <div class="time"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="quick-actions">
        <h3 style="font-family:'Prata',serif;font-size:18px;margin:0;">Quick Actions</h3>
        <div class="actions-grid">
            <a href="/" class="action-btn"><i class="fas fa-search"></i><span>Browse</span></a>
            <a href="saved-listings.php" class="action-btn"><i class="fas fa-heart"></i><span>Saved</span></a>
            <a href="messages.php" class="action-btn"><i class="fas fa-envelope"></i><span>Messages</span></a>
            <a href="profile.php" class="action-btn"><i class="fas fa-user"></i><span>Profile</span></a>
            <a href="/auth/logout.php" class="action-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>
</div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
