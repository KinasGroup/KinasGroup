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
    margin: 30px auto; /* Reduced from 40px */
}
.step {
    display: flex;
    gap: 16px; /* Reduced from 20px */
    margin-bottom: 28px; /* Reduced from 40px */
    position: relative;
}
.step:last-child { margin-bottom: 0; }
.step::before {
    content: '';
    position: absolute;
    left: 20px; /* Adjusted from 23px */
    top: 42px; /* Adjusted from 50px */
    bottom: -22px; /* Adjusted from -30px */
    width: 2px; /* Reduced from 3px */
    background: #e0e0e0;
}
.step:last-child::before { display: none; }
.step-icon {
    width: 40px; /* Reduced from 48px */
    height: 40px; /* Reduced from 48px */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px; /* Reduced from 20px */
    flex-shrink: 0;
    z-index: 1;
}
.step.completed .step-icon { background: #2E7D32; color: white; }
.step.pending .step-icon { background: #C6A43F; color: white; }
.step-content {
    flex: 1;
    padding-top: 4px; /* Reduced from 8px */
}
.step h3 { 
    margin: 0 0 4px 0; /* Reduced bottom margin from 8px */
    font-size: 15px; /* Reduced from 18px */
    font-weight: 600; /* Added for better readability at smaller size */
}
.step p { 
    color: #666; 
    margin: 0;
    font-size: 13px; /* Added explicit smaller size */
}
.btn-start { 
    background: #C6A43F; 
    color: #0A0A0A;
    font-size: 13px; /* Added smaller button text */
    padding: 6px 16px; /* Reduced padding */
    border-radius: 4px;
    display: inline-block;
    text-decoration: none;
    margin-top: 6px;
}
.btn-start:hover {
    background: #b3942e;
    color: #0A0A0A;
}
/* Page header styles */
.page-header h1 {
    font-size: 22px; /* Reduced from default */
    margin-bottom: 4px;
}
.page-header p {
    font-size: 14px; /* Reduced from default */
    color: #666;
}
/* Alert styles */
.alert-success h3 {
    font-size: 18px; /* Reduced from default */
    margin-bottom: 8px;
}
.alert-success p {
    font-size: 14px; /* Reduced from default */
}
.alert-success .btn-gold {
    font-size: 14px; /* Reduced from default */
    padding: 8px 24px;
}
</style>

<div class="je-dash-shell">
<?php include "../includes/partials/agent-sidebar.php"; ?>

<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-user-check" style="color:#C6A43F; font-size:20px;"></i> Account Verification</h1>
        <p style="font-size:14px; color:#666; margin-top:4px;">Complete these steps to activate your agent account</p>
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
    <div class="alert alert-success" style="text-align:center; padding:24px 20px; margin-top:20px;">
        <h3 style="font-size:18px; margin-bottom:6px;">✅ Your account is fully verified!</h3>
        <p style="font-size:14px; margin-bottom:12px;">You can now create listings and use all agent features.</p>
        <a href="/agent/dashboard.php" class="btn btn-gold" style="font-size:14px; padding:8px 28px; display:inline-block; background:#C6A43F; color:#0A0A0A; text-decoration:none; border-radius:4px;">Go to Dashboard</a>
    </div>
    <?php endif; ?>
</main>
</div>

<?php include '../templates/footer.php'; ?>
