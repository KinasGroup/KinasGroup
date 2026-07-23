<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db = Database::getInstance()->getConnection();
$userId = SessionManager::getUserId();
$csrf = Security::generateCSRFToken();

$flash = $_SESSION['rb_flash'] ?? null;
unset($_SESSION['rb_flash']);

$bookings = $db->prepare("
    SELECT b.*, c.title AS car_title,
           (SELECT url FROM listing_images WHERE listing_id = c.id AND listing_type = 'car' ORDER BY sort_order LIMIT 1) AS thumbnail,
           u.name AS renter_name, u.email AS renter_email, u.phone AS renter_phone
    FROM car_rental_bookings b
    JOIN car_listings c ON c.id = b.car_id
    JOIN users u ON u.id = b.user_id
    WHERE b.agent_id = ?
    ORDER BY FIELD(b.status, 'pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected'), b.start_date ASC
");
$bookings->execute([$userId]);
$bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.rb-wrap { max-width: 1100px; }
.rb-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 20px; }
.rb-card h1 { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; margin: 0 0 20px; }
.rb-flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
.rb-flash.success { background: #E8F5E9; color: #2E7D32; }
.rb-flash.error { background: #FFEBEE; color: #C62828; }
.rb-row { display: flex; gap: 16px; align-items: center; padding: 16px 0; border-bottom: 1px solid #F0F0F0; }
.rb-row:last-child { border-bottom: none; }
.rb-thumb { width: 80px; height: 60px; border-radius: 8px; object-fit: cover; background: #f0f0f0; flex-shrink: 0; }
.rb-info { flex: 1; min-width: 0; }
.rb-info .title { font-weight: 700; font-size: 14px; color: #0A0A0A; }
.rb-info .meta { font-size: 12px; color: #717171; margin-top: 4px; }
.rb-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 8px; }
.rb-badge.pending { background: #FFF3E0; color: #E65100; }
.rb-badge.confirmed { background: #E8F5E9; color: #2E7D32; }
.rb-badge.active { background: #E3F2FD; color: #1565C0; }
.rb-badge.completed { background: #EEE; color: #666; }
.rb-badge.cancelled, .rb-badge.rejected { background: #FFEBEE; color: #C62828; }
.rb-actions { display: flex; gap: 8px; }
.rb-btn { padding: 8px 16px; border-radius: 40px; font-weight: 600; font-size: 12px; cursor: pointer; border: none; }
.rb-btn-confirm { background: #2E7D32; color: #fff; }
.rb-btn-reject { background: #fff; color: #C62828; border: 1px solid #C62828; }
.rb-empty { text-align: center; padding: 40px; color: #999; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/agent-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="rb-wrap">
        <?php if ($flash): ?>
            <div class="rb-flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <div class="rb-card">
            <h1>Rental Bookings</h1>

            <?php if (empty($bookings)): ?>
                <div class="rb-empty">No rental bookings yet.</div>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                <div class="rb-row">
                    <img class="rb-thumb" src="<?= htmlspecialchars($b['thumbnail'] ?: '/assets/images/placeholder/product-placeholder.svg') ?>" alt=""
                         onerror="this.onerror=null;this.src='/assets/images/placeholder/product-placeholder.svg';">
                    <div class="rb-info">
                        <div class="title"><?= htmlspecialchars($b['car_title']) ?> <span class="rb-badge <?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span></div>
                        <div class="meta">
                            <?= date('M j', strtotime($b['start_date'])) ?> – <?= date('M j, Y', strtotime($b['end_date'])) ?>
                            (<?= (int)$b['total_days'] ?> day<?= $b['total_days'] == 1 ? '' : 's' ?>) ·
                            ₦<?= number_format((float)$b['total_price']) ?> ·
                            <?= htmlspecialchars($b['renter_name']) ?> (<?= htmlspecialchars($b['renter_email']) ?>)
                        </div>
                    </div>
                    <?php if ($b['status'] === 'pending'): ?>
                    <div class="rb-actions">
                        <form method="POST" action="/api/agent/rental-booking-respond.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="rb-btn rb-btn-confirm">Confirm</button>
                        </form>
                        <form method="POST" action="/api/agent/rental-booking-respond.php" style="display:inline;" onsubmit="return confirm('Reject this booking request?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="rb-btn rb-btn-reject">Reject</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
