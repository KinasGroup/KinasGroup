<?php
// Authenticated, per-session content
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';
require_once '../includes/notify.php';

SessionManager::requireLogin();

if ($_SESSION['user_role'] !== 'agent') {
    header('Location: /user/dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get current verification status
$stmt = $db->prepare("
    SELECT ap.verification_status, ap.kyc_provider, ap.kyc_verification_id,
           ap.kyb_status, ap.kyb_verification_id,
           u.phone_verified_at
    FROM agent_profiles ap
    JOIN users u ON u.id = ap.user_id
    WHERE ap.user_id = ?
");
$stmt->execute([$user_id]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$status) {
    // Create profile if missing
    $db->prepare("INSERT INTO agent_profiles (user_id) VALUES (?)")->execute([$user_id]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle KYC / KYB status updates from Didit (if needed)
$verificationStatus = $status['verification_status'] ?? 'pending';

// Simplified flow: Remove business document upload step
$steps = [
    1 => ['title' => 'Email Verified', 'status' => 'completed', 'icon' => 'fa-envelope'],
    2 => ['title' => 'Phone Verified', 'status' => !empty($status['phone_verified_at']) ? 'completed' : 'pending', 'icon' => 'fa-phone'],
    3 => ['title' => 'Identity Verification (KYC)', 'status' => in_array($status['verification_status'], ['kyc_passed','approved']) ? 'completed' : 'pending', 'icon' => 'fa-id-card'],
    4 => ['title' => 'Business Verification (KYB via Didit)', 'status' => in_array($status['kyb_status'], ['approved','verified']) ? 'completed' : 'pending', 'icon' => 'fa-building'],
];

$pageTitle = 'Agent Verification - KINAS GROUP';
$headerDepth = '../';
include '../templates/header.php';
?>

<style>
.verification-timeline {
    max-width: 800px;
    margin: 40px auto;
}
.step {
    display: flex;
    gap: 20px;
    margin-bottom: 40px;
    position: relative;
}
.step:last-child { margin-bottom: 0; }
.step::before {
    content: '';
    position: absolute;
    left: 23px;
    top: 50px;
    bottom: -30px;
    width: 3px;
    background: #e0e0e0;
}
.step:last-child::before { display: none; }
.step-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    z-index: 1;
}
.step.completed .step-icon { background: #2E7D32; color: white; }
.step.pending .step-icon { background: #C6A43F; color: white; }
.step-content {
    flex: 1;
    padding-top: 8px;
}
.step h3 { margin: 0 0 8px 0; font-size: 18px; }
.step p { color: #666; margin: 0; }
.btn-start { background: #C6A43F; color: #0A0A0A; }
</style>

<div class="je-dash-shell">
<?php include "../includes/partials/agent-sidebar.php"; ?>

<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-user-check" style="color:#C6A43F"></i> Account Verification</h1>
        <p>Complete these steps to activate your agent account</p>
    </div>

    <div class="verification-timeline">
        <?php foreach ($steps as $num => $step): ?>
        <div class="step <?= $step['status'] ?>">
            <div class="step-icon">
                <i class="fas <?= $step['icon'] ?>"></i>
            </div>
            <div class="step-content">
                <h3>Step <?= $num ?>: <?= htmlspecialchars($step['title']) ?></h3>
                <p><?= $step['status'] === 'completed' ? '✓ Completed' : 'Pending' ?></p>
                
                <?php if ($step['status'] !== 'completed'): ?>
                    <?php if ($num === 2): ?>
                        <a href="/api/auth/send-otp.php" class="btn btn-start">Verify Phone</a>
                    <?php elseif ($num === 3): ?>
                        <a href="/api/agent/kyc-start.php" class="btn btn-start">Start Identity Verification</a>
                    <?php elseif ($num === 4): ?>
                        <a href="/api/agent/kyb-start.php" class="btn btn-start">Start Business Verification (Didit)</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($verificationStatus === 'approved'): ?>
    <div class="alert alert-success" style="text-align:center; padding:30px;">
        <h3>✅ Your account is fully verified!</h3>
        <p>You can now create listings and use all agent features.</p>
        <a href="/agent/dashboard.php" class="btn btn-gold">Go to Dashboard</a>
    </div>
    <?php endif; ?>
</main>
</div>

<?php include '../templates/footer.php'; ?>
