<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
SessionManager::requireAdmin();
require_once __DIR__ . '/../api/config/database.php';
$db = Database::getInstance()->getConnection();
$headerDepth = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; padding: 30px; background: #F5F7FA; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; margin-bottom: 8px; }
        .page-header p { color: #666; font-size: 14px; }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 25px; }
        .settings-card { background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; }
        .card-header { padding: 20px 25px; background: #F8F8F8; border-bottom: 1px solid #E0E0E0; }
        .card-header h2 { font-family: 'Prata', serif; font-size: 20px; color: #0A0A0A; }
        .card-header h2 i { color: #C6A43F; margin-right: 10px; }
        .card-body { padding: 25px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .form-group label i { color: #C6A43F; margin-right: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .checkbox-group { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .checkbox-group input { width: 18px; height: 18px; cursor: pointer; accent-color: #C6A43F; }
        .checkbox-group label { margin: 0; cursor: pointer; font-weight: normal; }
        .btn-save { background: #C6A43F; color: #0A0A0A; border: none; padding: 14px 28px; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; transition: all 0.3s; width: 100%; }
        .btn-save:hover { background: #A8882E; transform: translateY(-2px); }
        .danger-zone { margin-top: 30px; border: 2px solid #DC2626; background: #FEF2F2; }
        .danger-zone .card-header { background: #FEE; border-bottom-color: #DC2626; }
        .danger-zone .card-header h2 { color: #DC2626; }
        .btn-danger { background: #DC2626; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-danger:hover { background: #B91C1C; transform: translateY(-2px); }
        @media (max-width: 768px) { .admin-main { padding: 20px; } .settings-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; gap: 0; } }
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-sliders-h" style="color: #C6A43F; margin-right: 10px;"></i>Platform Settings</h1>
        <p>Configure your marketplace settings and preferences</p>
    </div>

    <div class="settings-grid">
        <!-- General Settings -->
        <div class="settings-card">
            <div class="card-header">
                <h2><i class="fas fa-globe"></i> General Settings</h2>
            </div>
            <div class="card-body">
                <div class="form-group"><label><i class="fas fa-building"></i> Site Name</label><input type="text" value="KINAS GROUP" class="form-control"></div>
                <div class="form-group"><label><i class="fas fa-envelope"></i> Admin Email</label><input type="email" value="admin@kinasgroup.com" class="form-control"></div>
                <div class="form-group"><label><i class="fas fa-phone"></i> Support Phone</label><input type="tel" value="+234 800 123 4567" class="form-control"></div>
                <div class="form-group"><label><i class="fas fa-language"></i> Default Language</label><select class="form-control"><option>English</option><option>French</option><option>Arabic</option></select></div>
            </div>
        </div>

        <!-- Commission & Fees -->
        <div class="settings-card">
            <div class="card-header">
                <h2><i class="fas fa-percent"></i> Commission & Fees</h2>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label><i class="fas fa-car"></i> Automobile Commission</label><input type="number" value="5" class="form-control"><small style="color:#666;">% of sale price</small></div>
                    <div class="form-group"><label><i class="fas fa-home"></i> Real Estate Commission</label><input type="number" value="3" class="form-control"><small style="color:#666;">% of sale price</small></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label><i class="fas fa-store"></i> Marketplace Fee</label><input type="number" value="8" class="form-control"><small style="color:#666;">% of product price</small></div>
                    <div class="form-group"><label><i class="fas fa-solar-panel"></i> Solar Commission</label><input type="number" value="10" class="form-control"><small style="color:#666;">% of project value</small></div>
                </div>
            </div>
        </div>

        <!-- Listing Settings -->
        <div class="settings-card">
            <div class="card-header">
                <h2><i class="fas fa-list-ul"></i> Listing Settings</h2>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label><i class="fas fa-clock"></i> Listing Duration (days)</label><input type="number" value="90" class="form-control"></div>
                    <div class="form-group"><label><i class="fas fa-images"></i> Max Images Per Listing</label><input type="number" value="20" class="form-control"></div>
                </div>
                <div class="form-group"><label><i class="fas fa-check-circle"></i> Auto-approve Listings</label><select class="form-control"><option>Yes, auto-approve</option><option>No, require review</option></select></div>
                <div class="checkbox-group"><input type="checkbox" checked><label>Allow featured listings</label></div>
                <div class="checkbox-group"><input type="checkbox" checked><label>Enable virtual tours</label></div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="settings-card">
            <div class="card-header">
                <h2><i class="fas fa-bell"></i> Notification Settings</h2>
            </div>
            <div class="card-body">
                <div class="checkbox-group"><input type="checkbox" checked><label>New user registration alerts</label></div>
                <div class="checkbox-group"><input type="checkbox" checked><label>Agent approval requests</label></div>
                <div class="checkbox-group"><input type="checkbox" checked><label>Flagged listing alerts</label></div>
                <div class="checkbox-group"><input type="checkbox"><label>Daily digest emails</label></div>
                <div class="form-group"><label><i class="fas fa-envelope"></i> Notification Email</label><input type="email" value="notifications@kinasgroup.com" class="form-control"></div>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div style="margin-top: 25px; text-align: right;">
        <button class="btn-save"><i class="fas fa-save"></i> Save All Settings</button>
    </div>

    <!-- Danger Zone -->
    <div class="settings-card danger-zone" style="margin-top: 30px;">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
        </div>
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div><strong>Clear All System Cache</strong><br><small style="color:#666;">Remove temporary files and cached data</small></div>
                <button class="btn-danger"><i class="fas fa-trash-alt"></i> Clear Cache</button>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #FEE;">
                <div><strong>Reset All Settings to Default</strong><br><small style="color:#666;">Restore factory default configuration</small></div>
                <button class="btn-danger"><i class="fas fa-undo-alt"></i> Reset Settings</button>
            </div>
        </div>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
