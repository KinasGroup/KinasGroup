<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireLogin();

$page_title = 'My Dashboard';
$current_page = 'dashboard';

$user_id = $_SESSION['user_id'];

$db = Database::getInstance()->getConnection();

// ============================================================
// STATS - Using proper tables
// ============================================================

// Saved Listings (from favorites table)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$saved_listings = $stmt->fetch()['total'];

// Messages Sent
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE sender_id = ?");
$stmt->execute([$user_id]);
$messages_sent = $stmt->fetch()['total'];

// Replies Received
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ?");
$stmt->execute([$user_id]);
$replies_received = $stmt->fetch()['total'];

// Unread Messages
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_messages = $stmt->fetch()['total'];

// ============================================================
// RECENT SAVED LISTINGS - With images
// ============================================================
$stmt = $db->prepare("
    SELECT * FROM (
        SELECT 
            CONCAT('car_', f.listing_id) as unique_id, 
            f.created_at as saved_at,
            cl.title, 
            cl.price,
            CONCAT_WS(', ', cl.city, cl.state) AS location,
            cl.status,
            (SELECT url FROM listing_images WHERE listing_id = cl.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) as thumbnail,
            'car' as listing_type
        FROM favorites f
        JOIN car_listings cl ON f.listing_id = cl.id AND f.listing_type = 'car'
        WHERE f.user_id = ? AND cl.status = 'active'

        UNION ALL

        SELECT 
            CONCAT('property_', f.listing_id) as unique_id, 
            f.created_at as saved_at,
            pl.title, 
            pl.price, 
            CONCAT_WS(', ', pl.city, pl.state) AS location,
            pl.status,
            (SELECT url FROM listing_images WHERE listing_id = pl.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) as thumbnail,
            'property' as listing_type
        FROM favorites f
        JOIN property_listings pl ON f.listing_id = pl.id AND f.listing_type = 'property'
        WHERE f.user_id = ? AND pl.status = 'active'

        UNION ALL

        SELECT 
            CONCAT('solar_', f.listing_id) as unique_id, 
            f.created_at as saved_at,
            sol.title, 
            sol.price, 
            CONCAT_WS(', ', sol.city, sol.state) AS location,
            sol.status,
            (SELECT url FROM listing_images WHERE listing_id = sol.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) as thumbnail,
            'solar' as listing_type
        FROM favorites f
        JOIN solar_listings sol ON f.listing_id = sol.id AND f.listing_type = 'solar'
        WHERE f.user_id = ? AND sol.status = 'active'

        UNION ALL

        SELECT 
            CONCAT('marketplace_', f.listing_id) as unique_id, 
            f.created_at as saved_at,
            ml.title, 
            ml.price, 
            CONCAT_WS(', ', ml.city, ml.state) AS location,
            ml.status,
            (SELECT url FROM listing_images WHERE listing_id = ml.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) as thumbnail,
            'marketplace' as listing_type
        FROM favorites f
        JOIN marketplace_listings ml ON f.listing_id = ml.id AND f.listing_type = 'marketplace'
        WHERE f.user_id = ? AND ml.status = 'active'
    ) as combined
    ORDER BY saved_at DESC
    LIMIT 6
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$recent_saved = $stmt->fetchAll();

// ============================================================
// RECENT MESSAGES
// ============================================================
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

/* ============================================================ */
/* WELCOME BANNER */
/* ============================================================ */
.welcome-banner { 
    background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); 
    border-radius: 20px; 
    padding: 40px; 
    margin-bottom: 30px; 
    position: relative; 
    overflow: hidden; 
}
.welcome-banner::before { 
    content: ''; 
    position: absolute; 
    top: -50%; 
    right: -50%; 
    width: 200%; 
    height: 200%; 
    background: radial-gradient(circle, rgba(198,164,63,0.1) 0%, transparent 70%); 
    animation: shimmer 15s infinite; 
}
@keyframes shimmer { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-5%, -5%); } }
.welcome-banner h1 { 
    font-family: 'Prata', serif; 
    font-size: 32px; 
    color: white; 
    margin-bottom: 10px; 
    position: relative; 
    z-index: 1; 
}
.welcome-banner p { 
    color: rgba(255,255,255,0.7); 
    position: relative; 
    z-index: 1; 
}
.member-since { 
    display: inline-block; 
    background: rgba(198,164,63,0.2); 
    padding: 8px 16px; 
    border-radius: 30px; 
    font-size: 13px; 
    color: #C6A43F; 
    margin-top: 15px; 
    position: relative; 
    z-index: 1; 
}

/* ============================================================ */
/* STATS GRID - WITH GOLD TRIM */
/* ============================================================ */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(4, 1fr); 
    gap: 25px; 
    margin-bottom: 40px; 
}
.stat-card { 
    background: white; 
    border-radius: 16px; 
    padding: 25px; 
    border: 1px solid #E0E0E0; 
    transition: all 0.3s; 
    position: relative; 
    overflow: hidden; 
}
.stat-card::before { 
    content: ''; 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    height: 3px; 
    background: #C6A43F; 
}
.stat-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
}
.stat-icon { 
    width: 50px; 
    height: 50px; 
    background: rgba(198,164,63,0.1); 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin-bottom: 15px; 
    color: #C6A43F; 
    font-size: 24px; 
}
.stat-icon.blue   { background: rgba(59,130,246,.1);  color: #3B82F6; }
.stat-icon.green  { background: rgba(34,197,94,.1);   color: #22C55E; }
.stat-icon.gold   { background: rgba(198,164,63,.12); color: #C6A43F; }
.stat-icon.red    { background: rgba(220,38,38,.12);  color: #DC2626; }
.stat-icon.orange { background: rgba(245,158,11,.12); color: #F59E0B; }
.stat-number { font-size: 32px; font-weight: 700; color: #0A0A0A; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }

/* ============================================================ */
/* SECTION HEADERS */
/* ============================================================ */
.section-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 20px; 
    flex-wrap: wrap; 
    gap: 15px; 
}
.section-title { 
    font-family: 'Prata', serif; 
    font-size: 24px; 
    color: #0A0A0A; 
    position: relative; 
    padding-bottom: 10px; 
}
.section-title::after { 
    content: ''; 
    position: absolute; 
    bottom: 0; 
    left: 0; 
    width: 50px; 
    height: 3px; 
    background: #C6A43F; 
}
.view-all { 
    color: #C6A43F; 
    text-decoration: none; 
    font-weight: 600; 
    transition: all 0.3s; 
}
.view-all:hover { 
    color: #A8882E; 
    transform: translateX(5px); 
}

/* ============================================================ */
/* SAVED LISTINGS GRID */
/* ============================================================ */
.listings-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
    gap: 25px; 
    margin-bottom: 40px; 
}
.listing-card { 
    background: white; 
    border-radius: 16px; 
    overflow: hidden; 
    transition: all 0.3s; 
    border: 1px solid #E0E0E0; 
}
.listing-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
    border-color: #C6A43F; 
}
.listing-card a { text-decoration: none; color: inherit; display: block; }
.listing-image { 
    width: 100%; 
    height: 200px; 
    object-fit: cover; 
    background: #f0f0f0; 
}
.listing-image-placeholder {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 48px;
}
.listing-details { padding: 20px; }
.listing-title { 
    font-weight: 700; 
    font-size: 16px; 
    color: #0A0A0A; 
    margin-bottom: 8px; 
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.listing-price { 
    font-size: 20px; 
    font-weight: 700; 
    color: #C6A43F; 
    margin-bottom: 8px; 
}
.listing-location { 
    font-size: 13px; 
    color: #666; 
    margin-bottom: 10px; 
    display: flex; 
    align-items: center; 
    gap: 5px; 
}
.saved-date { font-size: 11px; color: #999; }

/* ============================================================ */
/* MESSAGE ITEMS */
/* ============================================================ */
.message-item { 
    background: #fff; 
    border-radius: 10px; 
    padding: 16px 20px; 
    border: 1px solid #e8e5e0; 
    margin-bottom: 10px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    text-decoration: none; 
    color: inherit; 
    transition: 0.2s; 
}
.message-item:hover { border-color: #C6A43F; }
.message-item.unread { 
    border-left: 4px solid #C6A43F; 
    background: #FFFDF5; 
}
.message-item .sender { font-weight: 600; font-size: 14px; color: #0A0A0A; }
.message-item .preview { font-size: 13px; color: #666; margin-top: 2px; }
.message-item .time { font-size: 12px; color: #999; flex-shrink: 0; }

/* ============================================================ */
/* EMPTY STATE */
/* ============================================================ */
.empty-state { 
    text-align: center; 
    padding: 60px 20px; 
    background: #fff; 
    border-radius: 12px; 
    border: 1px solid #e8e5e0; 
}
.empty-state i { font-size: 48px; color: #C6A43F; margin-bottom: 20px; display: block; }
.empty-state p { color: #666; margin-bottom: 20px; }

/* ============================================================ */
/* QUICK ACTIONS */
/* ============================================================ */
.quick-actions { 
    background: #fff; 
    border-radius: 16px; 
    padding: 30px; 
    margin-top: 30px; 
    border: 1px solid #E0E0E0; 
}
.actions-grid { 
    display: grid; 
    grid-template-columns: repeat(5, 1fr); 
    gap: 15px; 
    margin-top: 20px; 
}
.action-btn { 
    background: #F8F8F8; 
    padding: 20px 15px; 
    text-align: center; 
    border-radius: 12px; 
    text-decoration: none; 
    transition: all 0.3s; 
    border: 1px solid #E0E0E0; 
}
.action-btn:hover { 
    background: #C6A43F; 
    transform: translateY(-3px); 
    border-color: #C6A43F; 
}
.action-btn i { 
    font-size: 28px; 
    color: #C6A43F; 
    margin-bottom: 10px; 
    display: block; 
}
.action-btn span { 
    color: #333; 
    font-weight: 600; 
    font-size: 13px; 
}
.action-btn:hover i, .action-btn:hover span { color: #0A0A0A; }

.btn-view { 
    background: #C6A43F; 
    color: #0A0A0A; 
    padding: 6px 14px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-size: 12px; 
    font-weight: 600; 
    transition: all 0.3s; 
    display: inline-block; 
}
.btn-view:hover { background: #A8882E; transform: translateY(-2px); }

/* ============================================================ */
/* RESPONSIVE */
/* ============================================================ */
@media (max-width: 992px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) { 
    .dashboard-container { padding: 20px 15px; } 
    .welcome-banner { padding: 24px; }
    .welcome-banner h1 { font-size: 24px; }
    .section-title { font-size: 20px; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 15px; }
    .actions-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .actions-grid { grid-template-columns: 1fr; }
}

/* ============================================================
   DARK MODE — force this page's own styling to stay identical
   to light mode. Auto-generated from every hardcoded
   background/color/border-color rule already on this page.
   ============================================================ */
@media (prefers-color-scheme: dark) {
    body { background: #F5F7FA !important; }
    .welcome-banner { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%) !important; }
    .welcome-banner::before { background: radial-gradient(circle, rgba(198,164,63,0.1) 0%, transparent 70%) !important; }
    .welcome-banner h1 { color: white !important; }
    .welcome-banner p { color: rgba(255,255,255,0.7) !important; }
    .member-since { background: rgba(198,164,63,0.2) !important; color: #C6A43F !important; }
    .stat-card { background: white !important; }
    .stat-card::before { background: #C6A43F !important; }
    .stat-icon { background: rgba(198,164,63,0.1) !important; color: #C6A43F !important; }
    .stat-icon.blue { background: rgba(59,130,246,.1) !important; color: #3B82F6 !important; }
    .stat-icon.green { background: rgba(34,197,94,.1) !important; color: #22C55E !important; }
    .stat-icon.gold { background: rgba(198,164,63,.12) !important; color: #C6A43F !important; }
    .stat-icon.red { background: rgba(220,38,38,.12) !important; color: #DC2626 !important; }
    .stat-icon.orange { background: rgba(245,158,11,.12) !important; color: #F59E0B !important; }
    .stat-number { color: #0A0A0A !important; }
    .stat-label { color: #666 !important; }
    .section-title { color: #0A0A0A !important; }
    .section-title::after { background: #C6A43F !important; }
    .view-all { color: #C6A43F !important; }
    .view-all:hover { color: #A8882E !important; }
    .listing-card { background: white !important; }
    .listing-card:hover { border-color: #C6A43F !important; }
    .listing-image { background: #f0f0f0 !important; }
    .listing-image-placeholder { background: linear-gradient(135deg, #f5f5f5, #e8e8e8) !important; color: #ccc !important; }
    .listing-title { color: #0A0A0A !important; }
    .listing-price { color: #C6A43F !important; }
    .listing-location { color: #666 !important; }
    .saved-date { color: #999 !important; }
    .message-item { background: #fff !important; }
    .message-item:hover { border-color: #C6A43F !important; }
    .message-item.unread { background: #FFFDF5 !important; }
    .message-item .sender { color: #0A0A0A !important; }
    .message-item .preview { color: #666 !important; }
    .message-item .time { color: #999 !important; }
    .empty-state { background: #fff !important; }
    .empty-state i { color: #C6A43F !important; }
    .empty-state p { color: #666 !important; }
    .quick-actions { background: #fff !important; }
    .action-btn { background: #F8F8F8 !important; }
    .action-btn:hover { background: #C6A43F !important; border-color: #C6A43F !important; }
    .action-btn i { color: #C6A43F !important; }
    .action-btn span { color: #333 !important; }
    .action-btn:hover i, .action-btn:hover span { color: #0A0A0A !important; }
    .btn-view { background: #C6A43F !important; color: #0A0A0A !important; }
    .btn-view:hover { background: #A8882E !important; }
}
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>
<main class="je-dash-main">
<div class="dashboard-container">
    <!-- ============================================================ -->
    <!-- WELCOME BANNER -->
    <!-- ============================================================ -->
    <div class="welcome-banner">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p>Your exortic journey continues here</p>
            <div class="member-since">
                <i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STATS GRID -->
    <!-- ============================================================ -->
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

    <!-- ============================================================ -->
    <!-- RECENTLY SAVED LISTINGS -->
    <!-- ============================================================ -->
    <div class="section-header">
        <h2 class="section-title">Recently Saved Listings</h2>
        <a href="saved-listings.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    
    <?php if (empty($recent_saved)): ?>
        <div class="empty-state">
            <i class="fas fa-heart-broken"></i>
            <p>You haven't saved any listings yet.</p>
            <a href="/" class="btn-view" style="display: inline-block;">Browse Listings</a>
        </div>
    <?php else: ?>
        <div class="listings-grid">
            <?php foreach ($recent_saved as $listing):
                $listing_id = str_replace($listing['listing_type'] . '_', '', $listing['unique_id']);
                $thumbnail = $listing['thumbnail'] ?? '';
                
                // Build detail URL
                $divisionMap = [
                    'car' => 'kinas-automobile',
                    'property' => 'williams-connect-home',
                    'solar' => 'kinas-volt',
                    'marketplace' => 'kinas-marketplace'
                ];
                $folder = $divisionMap[$listing['listing_type']] ?? 'kinas-automobile';
                $detailUrl = '/divisions/' . $folder . '/detail.php?id=' . $listing_id;
            ?>
            <div class="listing-card">
                <a href="<?php echo $detailUrl; ?>">
                    <?php if (!empty($thumbnail)): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-image" loading="lazy">
                    <?php else: ?>
                        <div class="listing-image-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="listing-details">
                        <div class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></div>
                        <div class="listing-price">₦<?php echo number_format($listing['price'] ?? 0); ?></div>
                        <div class="listing-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location'] ?? 'N/A'); ?></div>
                        <div class="saved-date"><i class="far fa-clock"></i> Saved <?php echo date('M j, Y', strtotime($listing['saved_at'])); ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- RECENT MESSAGES -->
    <!-- ============================================================ -->
    <div class="section-header">
        <h2 class="section-title">Recent Messages</h2>
        <a href="messages.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>

    <?php if (empty($recent_messages)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No messages yet. Start a conversation with an agent!</p>
        </div>
    <?php else: ?>
        <?php foreach ($recent_messages as $msg): 
            $isSender = ($msg['sender_id'] == $user_id);
            $otherName = $isSender ? ($msg['other_name'] ?? 'Agent') : ($msg['other_name'] ?? 'User');
            $unread = ($msg['receiver_id'] == $user_id && $msg['is_read'] == 0);
            $isViewing = !empty($msg['is_viewing_request']) && $msg['is_viewing_request'] == 1;
            $badge = $isViewing ? '📅 Viewing Request' : '';
        ?>
        <a href="messages.php" class="message-item <?php echo $unread ? 'unread' : ''; ?>">
            <div>
                <div class="sender">
                    <?php echo htmlspecialchars($otherName); ?>
                    <?php if ($badge): ?>
                        <span style="font-size:10px;background:#E8F5E9;color:#2E7D32;padding:2px 10px;border-radius:12px;font-weight:400;"><?php echo $badge; ?></span>
                    <?php endif; ?>
                    <?php if ($unread): ?>
                        <span style="font-size:10px;background:#C6A43F;color:#fff;padding:2px 10px;border-radius:12px;font-weight:400;">New</span>
                    <?php endif; ?>
                </div>
                <div class="preview"><?php echo htmlspecialchars(substr($msg['body'] ?? '', 0, 60) ?: 'No message content'); ?></div>
            </div>
            <div class="time"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ============================================================ -->
    <div class="quick-actions">
        <h3 style="font-family: 'Prata', serif; margin-bottom: 15px; font-size: 18px;">Quick Actions</h3>
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
