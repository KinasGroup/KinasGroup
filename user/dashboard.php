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
// FIXED: Use 'favorites' table instead of 'saved_listings'
// ============================================================

// Get counts - using FAVORITES table
$stmt = $db->prepare("SELECT COUNT(*) as total FROM favorites WHERE user_id = ?");
$stmt->execute([$user_id]);
$saved_listings = $stmt->fetch()['total'];

// Get inquiries count (assuming inquiries table exists)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE sender_id = ? AND listing_type IS NOT NULL");
$stmt->execute([$user_id]);
$inquiries_sent = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 1");
$stmt->execute([$user_id]);
$responses_received = $stmt->fetch()['total'];

// Get recent saved listings - FIXED: Use favorites table with images
$stmt = $db->prepare("
    SELECT * FROM (
        SELECT CONCAT('car_', f.listing_id) as unique_id, f.created_at as saved_at, 
               cl.title, cl.price,
               COALESCE(NULLIF(cl.location, ''), CONCAT_WS(0x2C20, cl.city, cl.state)) AS location,
               cl.status,
               (SELECT url FROM listing_images WHERE listing_id = cl.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) as thumbnail,
               'car' as listing_type
        FROM favorites f
        JOIN car_listings cl ON f.listing_id = cl.id AND f.listing_type = 'car'
        WHERE f.user_id = ? AND cl.status = 'active'

        UNION ALL

        SELECT CONCAT('property_', f.listing_id) as unique_id, f.created_at as saved_at,
               pl.title, pl.price, CONCAT_WS(0x2C20, pl.city, pl.state) AS location, pl.status,
               (SELECT url FROM listing_images WHERE listing_id = pl.id AND listing_type = 'property' ORDER BY sort_order LIMIT 1) as thumbnail,
               'property' as listing_type
        FROM favorites f
        JOIN property_listings pl ON f.listing_id = pl.id AND f.listing_type = 'property'
        WHERE f.user_id = ? AND pl.status = 'active'

        UNION ALL

        SELECT CONCAT('solar_', f.listing_id) as unique_id, f.created_at as saved_at,
               sol.title, sol.price, CONCAT_WS(0x2C20, sol.city, sol.state) AS location, sol.status,
               (SELECT url FROM listing_images WHERE listing_id = sol.id AND listing_type = 'solar' ORDER BY sort_order LIMIT 1) as thumbnail,
               'solar' as listing_type
        FROM favorites f
        JOIN solar_listings sol ON f.listing_id = sol.id AND f.listing_type = 'solar'
        WHERE f.user_id = ? AND sol.status = 'active'

        UNION ALL

        SELECT CONCAT('marketplace_', f.listing_id) as unique_id, f.created_at as saved_at,
               ml.title, ml.price, CONCAT_WS(0x2C20, ml.city, ml.state) AS location, ml.status,
               (SELECT url FROM listing_images WHERE listing_id = ml.id AND listing_type = 'marketplace' ORDER BY sort_order LIMIT 1) as thumbnail,
               'marketplace' as listing_type
        FROM favorites f
        JOIN marketplace_listings ml ON f.listing_id = ml.id AND f.listing_type = 'marketplace'
        WHERE f.user_id = ? AND ml.status = 'active'
    ) as combined
    ORDER BY saved_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$recent_saved = $stmt->fetchAll();

// Get recent inquiries with agent and listing info
$stmt = $db->prepare("
    SELECT m.*, u.name as agent_name,
           COALESCE(cl.title, pl.title, sol.title, ml.title) as listing_title,
           CASE
               WHEN cl.title IS NOT NULL THEN 'car'
               WHEN pl.title IS NOT NULL THEN 'property'
               WHEN sol.title IS NOT NULL THEN 'solar'
               ELSE 'marketplace'
           END as listing_type
    FROM messages m
    LEFT JOIN users u ON m.receiver_id = u.id
    LEFT JOIN car_listings cl ON m.listing_id = cl.id AND m.listing_type = 'car'
    LEFT JOIN property_listings pl ON m.listing_id = pl.id AND m.listing_type = 'property'
    LEFT JOIN solar_listings sol ON m.listing_id = sol.id AND m.listing_type = 'solar'
    LEFT JOIN marketplace_listings ml ON m.listing_id = ml.id AND m.listing_type = 'marketplace'
    WHERE m.sender_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_inquiries = $stmt->fetchAll();

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
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
.stat-card { background: white; border-radius: 16px; padding: 25px; border: 1px solid #E0E0E0; transition: all 0.3s; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: #C6A43F; }
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
.stat-icon { width: 50px; height: 50px; background: rgba(198,164,63,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: #C6A43F; font-size: 24px; }
.stat-icon.blue   { background: rgba(59,130,246,.1);  color: #3B82F6; }
.stat-icon.green  { background: rgba(34,197,94,.1);   color: #22C55E; }
.stat-icon.gold   { background: rgba(198,164,63,.12); color: #C6A43F; }
.stat-icon.orange { background: rgba(245,158,11,.12); color: #F59E0B; }
.stat-icon.purple { background: rgba(139,92,246,.12); color: #8B5CF6; }
.stat-icon.red    { background: rgba(220,38,38,.12);  color: #DC2626; }
.stat-number { font-size: 32px; font-weight: 700; color: #0A0A0A; margin-bottom: 5px; }
.stat-label { color: #666; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
.section-title { font-family: 'Prata', serif; font-size: 24px; color: #0A0A0A; position: relative; padding-bottom: 10px; }
.section-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 50px; height: 3px; background: #C6A43F; }
.view-all { color: #C6A43F; text-decoration: none; font-weight: 600; transition: all 0.3s; }
.view-all:hover { color: #A8882E; transform: translateX(5px); }
.listings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
.listing-card { background: white; border-radius: 16px; overflow: hidden; transition: all 0.3s; border: 1px solid #E0E0E0; }
.listing-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); border-color: #C6A43F; }
.listing-image { width: 100%; height: 200px; object-fit: cover; background: #f0f0f0; }
.listing-details { padding: 20px; }
.listing-title { font-weight: 700; font-size: 16px; color: #0A0A0A; margin-bottom: 8px; }
.listing-price { font-size: 20px; font-weight: 700; color: #C6A43F; margin-bottom: 8px; }
.listing-location { font-size: 13px; color: #666; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
.saved-date { font-size: 11px; color: #999; }
.inquiries-table { background: white; border-radius: 16px; border: 1px solid #E0E0E0; overflow: hidden; }
.table-responsive { overflow-x: auto; }
.inquiries-table table { width: 100%; border-collapse: collapse; }
.inquiries-table th { background: #F8F8F8; padding: 15px 20px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 1px solid #E0E0E0; }
.inquiries-table td { padding: 15px 20px; border-bottom: 1px solid #E0E0E0; color: #333; font-size: 13px; }
.inquiries-table tr:hover { background: #F8F8F8; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status-read { background: #E8F5E9; color: #2E7D32; }
.status-unread { background: #FEF2F2; color: #DC2626; }
.btn-view { background: #C6A43F; color: #0A0A0A; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.3s; display: inline-block; }
.btn-view:hover { background: #A8882E; transform: translateY(-2px); }
.quick-actions { background: white; border-radius: 16px; padding: 30px; margin-top: 30px; border: 1px solid #E0E0E0; }
.actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
.action-btn { background: #F8F8F8; padding: 20px 15px; text-align: center; border-radius: 12px; text-decoration: none; transition: all 0.3s; border: 1px solid #E0E0E0; }
.action-btn:hover { background: #C6A43F; transform: translateY(-3px); border-color: #C6A43F; }
.action-btn i { font-size: 28px; color: #C6A43F; margin-bottom: 10px; display: block; }
.action-btn span { color: #333; font-weight: 600; font-size: 13px; }
.action-btn:hover i, .action-btn:hover span { color: #0A0A0A; }
.empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 16px; border: 1px solid #E0E0E0; }
.empty-state i { font-size: 48px; color: #C6A43F; margin-bottom: 20px; }
.empty-state p { color: #666; margin-bottom: 20px; }
@media (max-width: 768px) { .dashboard-container { padding: 20px 15px; } .welcome-banner h1 { font-size: 24px; } .section-title { font-size: 20px; } }
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
            <div class="stat-number"><?php echo $inquiries_sent; ?></div>
            <div class="stat-label">Inquiries Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-reply-all"></i></div>
            <div class="stat-number"><?php echo $responses_received; ?></div>
            <div class="stat-label">Responses Received</div>
        </div>
    </div>

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
                // Extract the listing ID from unique_id
                $listing_id = str_replace($listing['listing_type'] . '_', '', $listing['unique_id']);
                $thumbnail = $listing['thumbnail'] ?? '';
                // Build detail URL
                $detailUrl = '/divisions/' . ($listing['listing_type'] === 'property' ? 'williams-connect-home' : 'kinas-' . $listing['listing_type']) . '/detail.php?id=' . $listing_id;
            ?>
            <div class="listing-card">
                <a href="<?php echo $detailUrl; ?>">
                    <?php if (!empty($thumbnail)): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" class="listing-image" loading="lazy">
                    <?php else: ?>
                        <div class="listing-image" style="display:flex; align-items:center; justify-content:center; background: #f0f0f0; color: #ccc; font-size:48px;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="listing-details">
                        <div class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></div>
                        <div class="listing-price">₦<?php echo number_format($listing['price'] ?? 0); ?></div>
                        <div class="listing-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['location'] ?? 'N/A'); ?></div>
                        <div class="saved-date"><i class="far fa-clock"></i> Saved <?php echo time_ago($listing['saved_at']); ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-header">
        <h2 class="section-title">Recent Inquiries</h2>
        <a href="my-inquiries.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (empty($recent_inquiries)): ?>
        <div class="empty-state">
            <i class="fas fa-comments"></i>
            <p>You haven't sent any inquiries yet.</p>
            <a href="/" class="btn-view" style="display: inline-block;">Start Exploring</a>
        </div>
    <?php else: ?>
        <div class="inquiries-table">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Agent</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inquiries as $inquiry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inquiry['listing_title'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['agent_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars(substr($inquiry['body'] ?? '', 0, 50)) . '...'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                            <td><span class="status-badge <?php echo !empty($inquiry['is_read']) ? 'status-read' : 'status-unread'; ?>"><?php echo !empty($inquiry['is_read']) ? 'Read' : 'Unread'; ?></span></td>
                            <td><a href="my-inquiries.php?id=<?php echo $inquiry['id']; ?>" class="btn-view">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="quick-actions">
        <h3 style="font-family: 'Prata', serif; margin-bottom: 15px;">Quick Actions</h3>
        <div class="actions-grid">
            <a href="/" class="action-btn"><i class="fas fa-search"></i><span>Browse Listings</span></a>
            <a href="saved-listings.php" class="action-btn"><i class="fas fa-heart"></i><span>View Saved</span></a>
            <a href="my-inquiries.php" class="action-btn"><i class="fas fa-envelope"></i><span>My Inquiries</span></a>
            <a href="profile.php" class="action-btn"><i class="fas fa-user-edit"></i><span>Edit Profile</span></a>
            <a href="settings.php" class="action-btn"><i class="fas fa-cog"></i><span>Settings</span></a>
        </div>
    </div>
</div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
