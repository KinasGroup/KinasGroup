<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Require login
SessionManager::requireLogin();
$userId = SessionManager::getUserId();
$userName = $_SESSION['user_name'] ?? 'Agent';

// Get database connection
$db = Database::getInstance()->getConnection();

// ─────────────────────────────────────────────────────────────
// FETCH AGENT STATISTICS
// ─────────────────────────────────────────────────────────────

// Total listings by this agent across all listing tables
$stat_listings = 0;

// Car listings
$stmt = $db->prepare("SELECT COUNT(*) FROM car_listings WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_listings += (int)$stmt->fetchColumn();

// Property listings
$stmt = $db->prepare("SELECT COUNT(*) FROM property_listings WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_listings += (int)$stmt->fetchColumn();

// Solar listings
$stmt = $db->prepare("SELECT COUNT(*) FROM solar_listings WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_listings += (int)$stmt->fetchColumn();

// Marketplace listings
$stmt = $db->prepare("SELECT COUNT(*) FROM marketplace_listings WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_listings += (int)$stmt->fetchColumn();

// Total views (only from marketplace_listings since it has views column)
$stat_views = 0;
$stmt = $db->prepare("SELECT COALESCE(SUM(views), 0) FROM marketplace_listings WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_views = (int)$stmt->fetchColumn();

// Total inquiries for this agent
$stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE agent_id = ?");
$stmt->execute([$userId]);
$stat_inquiries = (int)$stmt->fetchColumn();

// Unread inquiries
$stmt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE agent_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$stat_unread = (int)$stmt->fetchColumn();

// Total earnings (paid commissions).
// Reads from `transactions` (the source of truth — matches
// agent/earnings.php and database/fresh_schema.sql).
$stmt = $db->prepare("SELECT COALESCE(SUM(commission), 0) FROM transactions WHERE agent_id = ? AND status = 'paid'");
$stmt->execute([$userId]);
$stat_earnings = (float)$stmt->fetchColumn();

// KYC status (with phone verification)
$stmt = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$kycStatus = (string)($stmt->fetchColumn() ?: 'pending');

$stmt = $db->prepare("SELECT phone_verified_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$phoneVerifiedAt = $stmt->fetchColumn();
$phoneVerified = !empty($phoneVerifiedAt);

// Get inquiry counts by division for charts
$inquiryStats = ['car' => 0, 'property' => 0, 'solar' => 0, 'marketplace' => 0];
$stmt = $db->prepare("SELECT listing_type, COUNT(*) as cnt FROM inquiries WHERE agent_id = ? GROUP BY listing_type");
$stmt->execute([$userId]);
while ($row = $stmt->fetch()) {
    $type = $row['listing_type'];
    if (isset($inquiryStats[$type])) $inquiryStats[$type] = (int)$row['cnt'];
}

// Get weekly view data for chart (last 4 weeks from marketplace)
$weeklyViews = [0, 0, 0, 0];
$stmt = $db->prepare("
    SELECT WEEK(created_at) as week_num, SUM(views) as total_views 
    FROM marketplace_listings 
    WHERE agent_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
    GROUP BY WEEK(created_at)
    ORDER BY week_num DESC
    LIMIT 4
");
$stmt->execute([$userId]);
$weeklyData = $stmt->fetchAll();
$weeklyViews = array_reverse(array_column($weeklyData, 'total_views'));
if (count($weeklyViews) < 4) {
    $weeklyViews = array_pad($weeklyViews, 4, 0);
}

require_once __DIR__ . '/../templates/header.php';
?>

<style>
/* Additional styles to fix layout spacing */
.agent-main {
    margin-top: 80px; /* Creates space below the fixed header */
    min-height: calc(100vh - 80px);
}

.agent-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
}

/* Ensure content isn't hidden under fixed header */
body {
    font-family: 'Inter', sans-serif;
    background: #F5F7FA;
}

/* Stats cards styling */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid #E0E0E0;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: #C6A43F;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: rgba(198,164,63,0.1);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #C6A43F;   /* visible against the 10% gold tint */
    font-size: 28px;
}
/* Color variants — used via class on the <div class="stat-icon">. Each
   uses a darker color over a faint tinted background so the icon is
   always visible (avoids the gold-on-gold problem). */
.stat-icon.blue   { background: rgba(59,130,246,.1);  color: #3B82F6; }
.stat-icon.green  { background: rgba(34,197,94,.1);   color: #22C55E; }
.stat-icon.gold   { background: rgba(198,164,63,.12); color: #C6A43F; }
.stat-icon.orange { background: rgba(245,158,11,.12); color: #F59E0B; }
.stat-icon.purple { background: rgba(139,92,246,.12); color: #8B5CF6; }
.stat-icon.red    { background: rgba(220,38,38,.12);  color: #DC2626; }

.stat-info h3 {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #0A0A0A;
}

.stat-change {
    font-size: 12px;
    color: #2E7D32;
    margin-top: 4px;
    display: inline-block;
}

/* Charts row */
.charts-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.chart-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid #E0E0E0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-header h3 {
    font-family: 'Prata', serif;
    font-size: 18px;
    color: #0A0A0A;
}

/* Quick actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 40px;
}

.action-card {
    background: white;
    border: 1px solid #E0E0E0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: #0A0A0A;
    transition: all 0.3s;
}

.action-card:hover {
    transform: translateY(-3px);
    border-color: #C6A43F;
    background: #FEFBF5;
}

.action-card i {
    font-size: 28px;
    color: #C6A43F;
    margin-bottom: 10px;
    display: block;
}

.action-card strong {
    font-size: 14px;
}

/* Section headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h3 {
    font-family: 'Prata', serif;
    font-size: 20px;
    color: #0A0A0A;
}

.view-all {
    color: #C6A43F;
    text-decoration: none;
    font-weight: 600;
}

.view-all:hover {
    text-decoration: underline;
}

/* Listings grid */
.listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.listing-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #E0E0E0;
    transition: all 0.3s;
}

.listing-card:hover {
    transform: translateY(-5px);
    border-color: #C6A43F;
}

.listing-image {
    position: relative;
    height: 200px;
    background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.listing-image i {
    font-size: 48px;
    color: #C6A43F;
    opacity: 0.5;
}

.listing-status {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.listing-status.active {
    background: #E8F5E9;
    color: #2E7D32;
}

.listing-status.pending {
    background: #FFF3E0;
    color: #F57C00;
}

.listing-details {
    padding: 20px;
}

.listing-details h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 8px;
}

.listing-price {
    font-size: 20px;
    font-weight: 700;
    color: #C6A43F;
    margin-bottom: 12px;
}

.listing-stats {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: #666;
    margin-bottom: 16px;
}

.listing-stats i {
    color: #C6A43F;
    margin-right: 4px;
}

.listing-actions {
    display: flex;
    gap: 12px;
}

.btn-edit, .btn-view {
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-edit {
    background: rgba(198,164,63,0.1);
    color: #C6A43F;
    border: 1px solid rgba(198,164,63,0.2);
}

.btn-view {
    background: #F5F5F5;
    color: #666;
}

.btn-edit:hover, .btn-view:hover {
    transform: translateY(-2px);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 40px;
    background: white;
    border-radius: 16px;
    border: 1px solid #E0E0E0;
}

.empty-state i {
    font-size: 48px;
    color: #C6A43F;
    margin-bottom: 16px;
    display: block;
}

.empty-state p {
    color: #666;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .agent-main {
        margin-top: 120px;
    }
    .agent-container {
        padding: 20px;
    }
    .charts-row {
        grid-template-columns: 1fr;
    }
    .listings-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-main">
    <div class="agent-container">
        <!-- Header -->
        <div class="agent-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px;">
            <div>
                <h1 style="font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px;">
                    <i class="fas fa-chart-line" style="color: #C6A43F; margin-right: 12px;"></i> Agent Dashboard
                </h1>
                <p style="color: #666;">Welcome back, <?php echo htmlspecialchars($userName); ?></p>
            </div>
            <a href="/agent/add-listing.php" class="btn-primary" style="background: #C6A43F; color: #0A0A0A; padding: 12px 24px; border-radius: 40px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus"></i> Add New Listing
            </a>
        </div>

        <?php if (!in_array($kycStatus, ['approved'], true)): ?>
        <div style="background:linear-gradient(135deg,#FFF8E1,#FFF3E0); border:1px solid #FFE0B2; border-radius:16px; padding:18px 24px; margin-bottom:24px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
            <i class="fas fa-shield-alt" style="color:#E65100; font-size:24px;"></i>
            <div style="flex:1; min-width:240px;">
                <strong style="color:#BF360C;">Complete your verification</strong>
                <span style="color:#5D4037; font-size:13px; display:block; margin-top:2px;">
                    <?php if (empty($phoneVerified)): ?>
                        Verify your phone number first — we send a 6-digit code via SMS to confirm you control this device.
                    <?php elseif (in_array($kycStatus, ['kyc_passed','documents_submitted','approved'], true)): ?>
                        Your personal KYC is done. Upload your business documents to finish.
                    <?php elseif ($kycStatus === 'rejected'): ?>
                        Your previous verification was declined. Please re-submit.
                    <?php else: ?>
                        Verified agents get priority ranking, higher limits, and a trust badge on their listings.
                    <?php endif; ?>
                </span>
            </div>
            <a href="<?= empty($phoneVerified) ? '/auth/verify-phone.php' : '/agent/verification.php' ?>" style="background:#BF360C; color:white; padding:10px 20px; border-radius:999px; text-decoration:none; font-weight:600; font-size:13px; white-space:nowrap;">
                <?php
                    if (empty($phoneVerified)) echo 'Verify Phone →';
                    elseif ($kycStatus === 'documents_submitted') echo 'Awaiting Review';
                    elseif (in_array($kycStatus, ['kyc_passed'], true)) echo 'Upload Business Docs →';
                    else echo 'Continue Verification →';
                ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-list-alt"></i></div>
                <div class="stat-info">
                    <h3>Total Listings</h3>
                    <div class="stat-number"><?= number_format($stat_listings) ?></div>
                    <span class="stat-change">Across all divisions</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-eye"></i></div>
                <div class="stat-info">
                    <h3>Total Views</h3>
                    <div class="stat-number"><?= number_format($stat_views) ?></div>
                    <span class="stat-change">Marketplace views</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <h3>Inquiries</h3>
                    <div class="stat-number"><?= number_format($stat_inquiries) ?></div>
                    <span class="stat-change"><?= $stat_unread ?> unread</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-naira-sign"></i></div>
                <div class="stat-info">
                    <h3>Total Earnings</h3>
                    <div class="stat-number">₦<?= number_format($stat_earnings, 2) ?></div>
                    <span class="stat-change">Paid commissions</span>
                </div>
            </div>
        </div>

        <!-- Charts Row - RESTORED -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line" style="color: #C6A43F; margin-right: 8px;"></i> Listing Views (4 weeks)</h3>
                </div>
                <canvas id="viewsChart" height="200"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie" style="color: #C6A43F; margin-right: 8px;"></i> Inquiries by Division</h3>
                </div>
                <canvas id="inquiriesChart" height="200"></canvas>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-header">
            <h3><i class="fas fa-bolt" style="color: #C6A43F; margin-right: 8px;"></i> Quick Actions</h3>
        </div>
        <div class="quick-actions">
            <a href="/agent/add-listing.php?type=car" class="action-card">
                <i class="fas fa-car"></i>
                <strong>Add Car Listing</strong>
            </a>
            <a href="/agent/add-listing.php?type=property" class="action-card">
                <i class="fas fa-home"></i>
                <strong>Add Property</strong>
            </a>
            <a href="/agent/add-listing.php?type=solar" class="action-card">
                <i class="fas fa-solar-panel"></i>
                <strong>Add Solar/Volt</strong>
            </a>
            <a href="/agent/add-listing.php?type=marketplace" class="action-card">
                <i class="fas fa-store"></i>
                <strong>Add Marketplace</strong>
            </a>
        </div>

        <!-- Recent Listings Section -->
        <div class="section-header">
            <h3><i class="fas fa-clock" style="color: #C6A43F; margin-right: 8px;"></i> Your Recent Listings</h3>
            <a href="/agent/listings.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <?php if ($stat_listings == 0): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <p>You haven't created any listings yet.</p>
            <a href="/agent/add-listing.php" class="btn-primary" style="display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 10px 24px; border-radius: 40px; text-decoration: none;">Create Your First Listing</a>
        </div>
        <?php else: ?>
        <div class="listings-grid">
            <?php
            // Fetch recent listings from all tables
            $recentListings = [];
            
            // Get car listings
            $stmt = $db->prepare("SELECT id, title, price, status, 'car' as type, created_at FROM car_listings WHERE agent_id = ? ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $row['views'] = 0;
                $row['url'] = "/divisions/kinas-automobile/detail.php?id=" . $row['id'];
                $recentListings[] = $row;
            }
            
            // Get property listings
            $stmt = $db->prepare("SELECT id, title, price, status, 'property' as type, created_at FROM property_listings WHERE agent_id = ? ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $row['views'] = 0;
                $row['url'] = "/divisions/williams-connect-home/detail.php?id=" . $row['id'];
                $recentListings[] = $row;
            }
            
            // Get solar listings
            $stmt = $db->prepare("SELECT id, title, price, status, 'solar' as type, created_at FROM solar_listings WHERE agent_id = ? ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $row['views'] = 0;
                $row['url'] = "/divisions/kinas-volt/detail.php?id=" . $row['id'];
                $recentListings[] = $row;
            }
            
            // Get marketplace listings
            $stmt = $db->prepare("SELECT id, title, price, status, 'marketplace' as type, created_at, views FROM marketplace_listings WHERE agent_id = ? ORDER BY created_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch()) {
                $row['url'] = "/divisions/kinas-marketplace/detail.php?id=" . $row['id'];
                $recentListings[] = $row;
            }
            
            // Sort by created_at and take first 3
            usort($recentListings, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            $recentListings = array_slice($recentListings, 0, 3);
            
            foreach ($recentListings as $listing):
            ?>
            <div class="listing-card">
                <div class="listing-image">
                    <i class="fas fa-image"></i>
                    <span class="listing-status <?= $listing['status'] ?>"><?= ucfirst($listing['status']) ?></span>
                </div>
                <div class="listing-details">
                    <h4><?= htmlspecialchars($listing['title']) ?></h4>
                    <div class="listing-price">₦<?= number_format($listing['price'], 2) ?></div>
                    <div class="listing-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($listing['views'] ?? 0) ?> views</span>
                    </div>
                    <div class="listing-actions">
                        <a href="/agent/edit-listing.php?id=<?= $listing['id'] ?>&type=<?= $listing['type'] ?>" class="btn-edit">Edit</a>
                        <a href="<?= $listing['url'] ?>" class="btn-view" target="_blank"><?= $listing['status'] === 'active' ? 'View' : 'Preview' ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Views Chart
const viewsCtx = document.getElementById('viewsChart')?.getContext('2d');
if (viewsCtx) {
    new Chart(viewsCtx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Views',
                data: <?= json_encode($weeklyViews) ?>,
                borderColor: '#C6A43F',
                backgroundColor: 'rgba(198,164,63,0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#C6A43F',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { 
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Views: ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: { 
                    grid: { color: '#F0F0F0' }, 
                    ticks: { color: '#666' }, 
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Views',
                        color: '#999'
                    }
                },
                x: { 
                    grid: { display: false }, 
                    ticks: { color: '#666' } 
                }
            }
        }
    });
}

// Inquiries by Division Chart
const inquiriesCtx = document.getElementById('inquiriesChart')?.getContext('2d');
if (inquiriesCtx) {
    const inquiryData = <?= json_encode(array_values($inquiryStats)) ?>;
    const hasData = inquiryData.some(v => v > 0);
    
    new Chart(inquiriesCtx, {
        type: 'doughnut',
        data: {
            labels: ['Automobiles', 'Real Estate', 'Solar/Volt', 'Marketplace'],
            datasets: [{
                data: inquiryData,
                backgroundColor: ['#C6A43F', '#D4B96A', '#A8882E', '#F5E6B8'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { 
                    position: 'bottom', 
                    labels: { 
                        color: '#666',
                        font: { size: 12 }
                    } 
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a,b) => a + b, 0);
                            const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                            return `${context.label}: ${context.raw} inquiries (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // If no data, show a message
    if (!hasData) {
        document.getElementById('inquiriesChart').style.opacity = '0.5';
    }
}
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
