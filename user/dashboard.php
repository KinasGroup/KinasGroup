<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireLogin();

$page_title = 'My Dashboard';
$current_page = 'dashboard';

$user_id = $_SESSION['user_id'];

$db = Database::getInstance()->getConnection();

// ============================================================
// STATS - Using messages table (FIXED)
// ============================================================

// Messages Sent (sent by this user)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE sender_id = ?");
$stmt->execute([$user_id]);
$messages_sent = $stmt->fetch()['total'];

// Replies Received (messages where this user is the receiver)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$replies_received = $stmt->fetch()['total'];

// Unread Messages (messages where this user is the receiver and is_read = 0)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetch()['total'];

// Saved Listings
$stmt = $db->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$saved_listings = $stmt->fetch()['total'];

// ============================================================
// RECENT MESSAGES (FIXED: From messages table)
// ============================================================
$stmt = $db->prepare("
    SELECT 
        m.*,
        u.name AS sender_name,
        u.email AS sender_email,
        u2.name AS receiver_name,
        u2.email AS receiver_email,
        COALESCE(cl.title, pl.title, sol.title, ml.title) AS listing_title,
        CASE 
            WHEN cl.id IS NOT NULL THEN 'car'
            WHEN pl.id IS NOT NULL THEN 'property'
            WHEN sol.id IS NOT NULL THEN 'solar'
            WHEN ml.id IS NOT NULL THEN 'marketplace'
        END AS listing_type
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    LEFT JOIN car_listings cl ON m.listing_id = cl.id AND m.listing_type = 'car'
    LEFT JOIN property_listings pl ON m.listing_id = pl.id AND m.listing_type = 'property'
    LEFT JOIN solar_listings sol ON m.listing_id = sol.id AND m.listing_type = 'solar'
    LEFT JOIN marketplace_listings ml ON m.listing_id = ml.id AND m.listing_type = 'marketplace'
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$recent_messages = $stmt->fetchAll();

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.dashboard-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.welcome-banner { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); border-radius: 20px; padding: 40px; margin-bottom: 30px; position: relative; overflow: hidden; }
.welcome-banner::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(198,164,63,0.1) 0%, transparent 70%); animation: shimmer 15s infinite; }
@keyframes shimmer { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-5%, -5%); } }
.welcome-banner h1 { font-family: 'Prata', serif; font-size: 32px; color: white; margin-bottom: 10px; position: relative; z-index: 1; }
.welcome-banner p { color: rgba(255,255,255,0.7); position: relative; z-index: 1; }
.member-since { display: inline-block; background: rgba(198,164,63,0.2); padding: 8px 16px; border-radius: 30px; font-size: 13px; color: #C6A43F; margin-top: 15px; position: relative; z-index: 1; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 25px; margin-bottom: 40px; }
.stat-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #E0E0E0; transition: all 0.3s; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #C6A43F; }
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
.stat-icon { width: 50px; height: 50px; background: rgba(198,164,63,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: #C6A43F; font-size: 24px; }
.stat-icon.blue   { background: rgba(59,130,246,.1);  color: #3B82F6; }
.stat-icon.green  { background: rgba(34,197,94,.1);   color: #22C55E; }
.stat-icon.gold   { background: rgba(198,164,63,.12); color: #C6A43F; }
.stat-icon.red    { background: rgba(220,38,38,.12);  color: #DC2626; }
.stat-icon.orange { background: rgba(245,158,11,.12); color: #F59E0B; }
.stat-number { font-size: 32px; font-weight: 700; color: #0A0A0A; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-family: 'Prata', serif; font-size: 24px; color: #0A0A0A; position: relative; padding-bottom: 10px; }
.section-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 50px; height: 3px; background: #C6A43F; }
.view-all { color: #C6A43F; text-decoration: none; font-weight: 600; transition: all 0.3s; }
.view-all:hover { color: #A8882E; transform: translateX(5px); }

/* Message Cards */
.message-card { background: white; border-radius: 14px; border: 1px solid #E0E0E0; margin-bottom: 12px; overflow: hidden; transition: all 0.3s; display: block; text-decoration: none; color: inherit; }
.message-card:hover { transform: translateX(4px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-color: #C6A43F; }
.message-card.unread { border-left: 4px solid #C6A43F; background: #FFFDF5; }
.message-card .msg-body { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.message-card .msg-info { flex: 1; min-width: 0; }
.message-card .msg-sender { font-weight: 600; font-size: 14px; color: #0A0A0A; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.message-card .msg-sender .badge { font-size: 10px; font-weight: 600; padding: 2px 10px; border-radius: 12px; background: #FFF3E0; color: #F57C00; }
.message-card .msg-sender .badge.viewing { background: #E8F5E9; color: #2E7D32; }
.message-card .msg-preview { font-size: 13px; color: #666; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.message-card .msg-meta { display: flex; align-items: center; gap: 12px; font-size: 12px; color: #999; flex-shrink: 0; }
.message-card .msg-meta .unread-dot { width: 8px; height: 8px; background: #C6A43F; border-radius: 50%; display: inline-block; }
.empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; border: 1px solid #E0E0E0; color: #999; }
.empty-state i { font-size: 48px; color: #C6A43F; margin-bottom: 20px; }
.empty-state p { color: #666; margin-bottom: 20px; }
.btn-view { background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.3s; display: inline-block; }
.btn-view:hover { background: #A8882E; transform: translateY(-2px); }

.quick-actions { background: white; border-radius: 16px; padding: 30px; margin-top: 30px; border: 1px solid #E0E0E0; }
.actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
.action-btn { background: #F8F8F8; padding: 20px 15px; text-align: center; border-radius: 12px; text-decoration: none; transition: all 0.3s; border: 1px solid #E0E0E0; }
.action-btn:hover { background: #C6A43F; transform: translateY(-3px); border-color: #C6A43F; }
.action-btn i { font-size: 28px; color: #C6A43F; margin-bottom: 10px; display: block; }
.action-btn span { color: #333; font-weight: 600; font-size: 13px; }
.action-btn:hover i, .action-btn:hover span { color: #0A0A0A; }

@media (max-width: 768px) { .dashboard-container { padding: 20px 15px; } .welcome-banner h1 { font-size: 24px; } .section-title { font-size: 20px; } .message-card .msg-body { flex-direction: column; align-items: flex-start; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>
<main class="je-dash-main">
<div class="dashboard-container">
    <div class="welcome-banner">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p>Your luxury marketplace journey continues here</p>
            <div class="member-since">
                <i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-heart"></i></div>
            <div class="stat-number"><?php echo $saved_listings; ?></div>
            <div class="stat-label">Saved Listings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-number"><?php echo $messages_sent; ?></div>
            <div class="stat-label">Messages Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-reply-all"></i></div>
            <div class="stat-number"><?php echo $replies_received; ?></div>
            <div class="stat-label">Replies Received</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-envelope"></i></div>
            <div class="stat-number"><?php echo $unread_messages; ?></div>
            <div class="stat-label">Unread Messages</div>
        </div>
    </div>

    <div class="section-header">
        <h2 class="section-title">Recent Messages</h2>
        <a href="messages.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <?php if (empty($recent_messages)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>You haven't sent or received any messages yet.</p>
            <a href="/" class="btn-view" style="display: inline-block;">Browse Listings</a>
        </div>
    <?php else: ?>
        <?php foreach ($recent_messages as $msg):
            $isSender = ($msg['sender_id'] == $user_id);
            $otherName = $isSender ? ($msg['receiver_name'] ?? 'Agent') : ($msg['sender_name'] ?? 'User');
            $isViewing = !empty($msg['is_viewing_request']) && $msg['is_viewing_request'] == 1;
            $unread = ($msg['receiver_id'] == $user_id && $msg['is_read'] == 0);
            
            // Determine badge
            $badgeText = '';
            $badgeClass = '';
            if ($isViewing) {
                $badgeText = '📅 Viewing Request';
                $badgeClass = 'viewing';
            } elseif ($isSender) {
                $badgeText = 'Sent';
                $badgeClass = '';
            } else {
                $badgeText = 'Reply';
                $badgeClass = '';
            }
            
            $body = $msg['body'] ?? '';
            $preview = strlen($body) > 80 ? substr($body, 0, 80) . '...' : $body;
            $listingTitle = $msg['listing_title'] ?? '';
        ?>
        <a href="messages.php" class="message-card <?php echo $unread ? 'unread' : ''; ?>">
            <div class="msg-body">
                <div class="msg-info">
                    <div class="msg-sender">
                        <?php echo htmlspecialchars($otherName); ?>
                        <?php if ($badgeText): ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                        <?php endif; ?>
                        <?php if ($unread): ?>
                            <span class="badge" style="background:#C6A43F;color:#fff;">New</span>
                        <?php endif; ?>
                        <?php if ($listingTitle): ?>
                            <span style="font-size:11px;color:#C6A43F;font-weight:400;">· <?php echo htmlspecialchars($listingTitle); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="msg-preview"><?php echo htmlspecialchars($preview ?: 'No message content'); ?></div>
                </div>
                <div class="msg-meta">
                    <?php if ($unread): ?>
                        <span class="unread-dot"></span>
                    <?php endif; ?>
                    <span><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                    <i class="fas fa-chevron-right" style="color:#C6A43F;"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="quick-actions">
        <h3 style="font-family: 'Prata', serif; margin-bottom: 15px;">Quick Actions</h3>
        <div class="actions-grid">
            <a href="/" class="action-btn"><i class="fas fa-search"></i><span>Browse Listings</span></a>
            <a href="saved-listings.php" class="action-btn"><i class="fas fa-heart"></i><span>View Saved</span></a>
            <a href="messages.php" class="action-btn"><i class="fas fa-envelope"></i><span>Messages</span></a>
            <a href="profile.php" class="action-btn"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
            <a href="settings.php" class="action-btn"><i class="fas fa-cog"></i><span>Settings</span></a>
        </div>
    </div>
</div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
