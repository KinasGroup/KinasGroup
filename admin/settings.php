<?php
/**
 * KINAS GROUP — User Settings (Fixed: $pdo→$db, require_login→SessionManager)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireLogin();

$db      = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $success = '';
    } else {
        $notif_email  = isset($_POST['notifications_email']) ? 1 : 0;
        $notif_sms    = isset($_POST['notifications_sms'])   ? 1 : 0;
        $marketing    = isset($_POST['marketing_emails'])    ? 1 : 0;
        $db->prepare("UPDATE users SET notifications_email=?, notifications_sms=?, marketing_emails=? WHERE id=?")
           ->execute([$notif_email, $notif_sms, $marketing, $user_id]);
        $success = 'Settings saved successfully.';
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$csrf = Security::generateCSRFToken();
$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Account Settings - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .user-container{max-width:800px;margin:0 auto;padding:30px}
        .page-header{margin-bottom:28px}.page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A;margin-bottom:4px}.page-header p{color:#666;font-size:14px}
        .flash.success{background:#E8F5E9;color:#2E7D32;padding:13px 18px;border-radius:10px;margin-bottom:18px;font-size:14px;font-weight:500}
        .settings-card{background:white;border-radius:18px;padding:28px;border:1px solid #E0E0E0;margin-bottom:24px}
        .settings-card h3{font-size:16px;font-weight:600;color:#C6A43F;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #C6A43F;display:inline-block}
        .toggle-row{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid #F0F0F0}
        .toggle-row:last-of-type{border-bottom:none}
        .toggle-label h4{font-size:14px;font-weight:600;color:#0A0A0A;margin-bottom:3px}
        .toggle-label p{font-size:12px;color:#666}
        .toggle{position:relative;width:48px;height:26px;flex-shrink:0}
        .toggle input{opacity:0;width:0;height:0}
        .slider{position:absolute;inset:0;background:#E0E0E0;border-radius:13px;cursor:pointer;transition:.3s}
        .slider:before{content:'';position:absolute;width:20px;height:20px;left:3px;top:3px;background:white;border-radius:50%;transition:.3s}
        input:checked + .slider{background:#C6A43F}
        input:checked + .slider:before{transform:translateX(22px)}
        .btn-save{background:#C6A43F;color:#0A0A0A;border:none;padding:12px 28px;border-radius:40px;font-weight:600;cursor:pointer;margin-top:20px;transition:all .3s}
        .btn-save:hover{background:#A8882E;transform:translateY(-2px)}
        .danger-zone{background:#FEF2F2;border:1px solid #FECACA;border-radius:18px;padding:24px;margin-top:24px}
        .danger-zone h3{color:#DC2626;font-size:16px;font-weight:600;margin-bottom:10px}
        .danger-zone p{color:#666;font-size:13px;margin-bottom:16px}
        .btn-danger{background:#DC2626;color:white;border:none;padding:10px 22px;border-radius:40px;cursor:pointer;font-weight:600}
        @media(max-width:768px){.user-container{padding:20px}}
    </style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/user-sidebar.php'; ?>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php' ?>
<main style="padding-top:80px">
<div class="user-container">
    <div class="page-header">
        <h1><i class="fas fa-cog" style="color:#C6A43F;margin-right:10px"></i>Account Settings</h1>
        <p>Manage your notification preferences</p>
    </div>

    <?php if ($success): ?><div class="flash success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="settings-card">
            <h3>Notifications</h3>
            <div class="toggle-row">
                <div class="toggle-label"><h4>Email Notifications</h4><p>Receive updates, inquiries and alerts via email</p></div>
                <label class="toggle"><input type="checkbox" name="notifications_email" <?= ($user['notifications_email'] ?? 1) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-label"><h4>SMS Notifications</h4><p>Receive OTP and important alerts by SMS</p></div>
                <label class="toggle"><input type="checkbox" name="notifications_sms" <?= ($user['notifications_sms'] ?? 1) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-label"><h4>Marketing Emails</h4><p>New listings, promotions and platform news</p></div>
                <label class="toggle"><input type="checkbox" name="marketing_emails" <?= ($user['marketing_emails'] ?? 0) ? 'checked' : '' ?>><span class="slider"></span></label>
            </div>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>

    <div class="danger-zone">
        <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
        <p>Once you delete your account, all your data will be permanently removed. This action cannot be undone.</p>
        <button class="btn-danger" onclick="return confirm('Delete your account permanently? This cannot be undone.')">Delete My Account</button>
    </div>
</div>


</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
