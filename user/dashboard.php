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
// STATS - Using messages table
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
// RECENT CONVERSATIONS - Fixed for sql_mode=only_full_group_by
// ============================================================
$stmt = $db->prepare("
    SELECT 
        conversation_key,
        MAX(last_message_time) AS last_message_time,
        MAX(last_message) AS last_message,
        MAX(last_sender_id) AS last_sender_id,
        MAX(other_party_name) AS other_party_name,
        MAX(other_party_email) AS other_party_email,
        MAX(listing_title) AS listing_title,
        MAX(listing_type) AS listing_type,
        MAX(unread_count) AS unread_count
    FROM (
        SELECT 
            CONCAT(
                LEAST(m.sender_id, m.receiver_id), '_', 
                GREATEST(m.sender_id, m.receiver_id), '_', 
                COALESCE(m.listing_id, 0)
            ) AS conversation_key,
            m.created_at AS last_message_time,
            m.body AS last_message,
            m.sender_id AS last_sender_id,
            (SELECT name FROM users WHERE id = 
                (CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id 
                    ELSE m.sender_id 
                END)
            ) AS other_party_name,
            (SELECT email FROM users WHERE id = 
                (CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id 
                    ELSE m.sender_id 
                END)
            ) AS other_party_email,
            COALESCE(
                (SELECT title FROM car_listings WHERE id = m.listing_id),
                (SELECT title FROM property_listings WHERE id = m.listing_id),
                (SELECT title FROM solar_listings WHERE id = m.listing_id),
                (SELECT title FROM marketplace_listings WHERE id = m.listing_id)
            ) AS listing_title,
            m.listing_type,
            (SELECT COUNT(*) FROM messages m2 
             WHERE (m2.sender_id = m.sender_id AND m2.receiver_id = m.receiver_id AND m2.listing_id = m.listing_id)
                OR (m2.sender_id = m.receiver_id AND m2.receiver_id = m.sender_id AND m2.listing_id = m.listing_id)
                AND m2.receiver_id = ? AND m2.is_read = 0) AS unread_count
        FROM messages m
        WHERE m.sender_id = ? OR m.receiver_id = ?
    ) AS conversations
    GROUP BY conversation_key
    ORDER BY last_message_time DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$recent_conversations = $stmt->fetchAll();

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

/* Conversation Cards */
.conversation-card { background: white; border-radius: 14px; border: 1px solid #E0E0E0; margin-bottom: 12px; overflow: hidden; transition: all 0.3s; display: block; text-decoration: none; color: inherit; }
.conversation-card:hover { transform: translateX(4px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-color: #C6A43F; }
.conversation-card.unread { border-left: 4px solid #C6A43F; background: #FFFDF5; }
.conversation-card .conv-body { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.conversation-card .conv-info { flex: 1; min-width: 0; }
.conversation-card .conv-sender { font-weight: 600; font-size: 14px; color: #0A0A0A; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.conversation-card .conv-sender .badge { font-size: 10px; font-weight: 600; padding: 2px 10px; border-radius: 12px; background: #FFF3E0; color: #F57C00; }
.conversation-card .conv-sender .badge.viewing { background: #E8F5E9; color: #2E7D32; }
.conversation-card .conv-preview { font-size: 13px; color: #666; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conversation-card .conv-meta { display: flex; align-items: center; gap: 12px; font-size: 12px; color: #999; flex-shrink: 0; }
.conversation-card .conv-meta .unread-dot { width: 8px; height: 8px; background: #C6A43F; border-radius: 50%; display: inline-block; }
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

@media (max-width: 768px) { .dashboard-container { padding: 20px 15px; } .welcome-banner h1 { font-size: 24px; } .section-title { font-size: 20px; } .conversation-card .conv-body { flex-direction: column; align-items: flex-start; } }
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
        <h2 class="section-title">Recent Conversations</h2>
        <a href="messages.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <?php if (empty($recent_conversations)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>You haven't sent or received any messages yet.</p>
            <a href="/" class="btn-view" style="display: inline-block;">Browse Listings</a>
        </div>
    <?php else: ?>
        <?php foreach ($recent_conversations as $conv):
            $isSender = ($conv['last_sender_id'] == $user_id);
            $otherName = $conv['other_party_name'] ?? 'User';
            $unread = ($conv['unread_count'] ?? 0) > 0;
            $unreadCount = $conv['unread_count'] ?? 0;
            
            $body = $conv['last_message'] ?? '';
            $preview = strlen($body) > 80 ? substr($body, 0, 80) . '...' : $body;
            $listingTitle = $conv['listing_title'] ?? '';
            $isViewing = $conv['listing_type'] === 'viewing';
            
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
        ?>
        <a href="messages.php" class="conversation-card <?php echo $unread ? 'unread' : ''; ?>">
            <div class="conv-body">
                <div class="conv-info">
                    <div class="conv-sender">
                        <?php echo htmlspecialchars($otherName); ?>
                        <?php if ($badgeText): ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                        <?php endif; ?>
                        <?php if ($unread && $unreadCount > 0): ?>
                            <span class="badge" style="background:#C6A43F;color:#fff;"><?php echo $unreadCount; ?> new</span>
                        <?php endif; ?>
                        <?php if ($listingTitle): ?>
                            <span style="font-size:11px;color:#C6A43F;font-weight:400;">· <?php echo htmlspecialchars($listingTitle); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="conv-preview"><?php echo htmlspecialchars($preview ?: 'No message content'); ?></div>
                </div>
                <div class="conv-meta">
                    <?php if ($unread): ?>
                        <span class="unread-dot"></span>
                    <?php endif; ?>
                    <span><?php echo date('M j, g:i A', strtotime($conv['last_message_time'])); ?></span>
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
