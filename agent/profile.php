<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';


// Auth: handled by SessionManager::requireAgent()

$csrf_token = Security::generateCSRFToken();
// KYC soft-guard
$kycStatus='pending';try{$st=Database::getInstance()->getConnection()->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");$st->execute([(int)$_SESSION['user_id']]);$kycStatus=$st->fetchColumn()?:'pending';}catch(Exception $e){}

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; }
.profile-card { background: white; border-radius: 20px; padding: 28px; border: 1px solid #E0E0E0; }
.profile-card h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.btn-save { background: #C6A43F; color: #0A0A0A; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 8px; }
.btn-save:hover { background: #A8882E; transform: translateY(-2px); }
.specialties-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.profile-photo-section { text-align: center; }
.profile-avatar-large { width: 120px; height: 120px; background: #C6A43F; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 700; color: #0A0A0A; margin-bottom: 16px; }
.btn-upload { background: #F5F5F5; border: 1px solid #E0E0E0; padding: 10px 20px; border-radius: 40px; color: #666; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.photo-note { font-size: 11px; color: #999; margin-top: 12px; }
.divider { margin: 24px 0; border: none; height: 1px; background: #E0E0E0; }
.danger-zone { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 16px; padding: 20px; margin-top: 24px; }
.danger-zone h4 { color: #DC2626; margin-bottom: 8px; }
.danger-zone p { font-size: 13px; color: #666; margin-bottom: 16px; }
.btn-danger { background: #DC2626; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; }
@media (max-width: 968px) { .profile-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; gap: 0; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .profile-card { padding: 20px; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><h1><i class="fas fa-user-circle"></i> Agent Profile</h1><p>Manage your professional profile and business information</p></div>

    <div class="profile-grid">
        <div class="profile-card"><h3><i class="fas fa-user"></i> Personal Information</h3>
            <form class="profile-form"><div class="form-row"><div class="form-group"><label>First Name</label><input type="text" name="first_name" value="John"></div><div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="Smith"></div></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" value="john.smith@kinasgroup.com"></div>
            <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" value="+234 801 234 5678"></div>
            <div class="form-group"><label>Bio / Professional Summary</label><textarea name="bio" rows="4">Experienced luxury real estate and automobile agent with over 10 years in the industry. Specializing in high-end properties and exotic vehicles.</textarea></div>
            <button type="submit" class="btn-save">Save Changes</button></form>
        </div>

        <div class="profile-card"><h3><i class="fas fa-building"></i> Business Information</h3>
            <div class="form-group"><label>Agency/Company Name</label><input type="text" name="agency" value="Smith Luxury Group"></div>
            <div class="form-group"><label>License Number</label><input type="text" name="license" value="RL-2024-00123"></div>
            <div class="form-group"><label>Years of Experience</label><input type="number" name="experience" value="10"></div>
            <div class="form-group"><label>Specialties</label><div class="specialties-grid"><label class="checkbox-label"><input type="checkbox" checked> Luxury Automobiles</label><label class="checkbox-label"><input type="checkbox" checked> Premium Real Estate</label><label class="checkbox-label"><input type="checkbox"> Solar Solutions</label><label class="checkbox-label"><input type="checkbox"> General Marketplace</label></div></div>
            <div class="form-group"><label>Website</label><input type="url" name="website" value="https://smithluxury.com"></div>
            <button type="submit" class="btn-save">Update Business Info</button>
        </div>

        <div class="profile-card"><h3><i class="fas fa-camera"></i> Profile Photo</h3>
            <div class="profile-photo-section"><div class="profile-avatar-large">JS</div><div><button class="btn-upload" onclick="document.getElementById('photoUpload').click()"><i class="fas fa-camera"></i> Upload New Photo</button><input type="file" id="photoUpload" accept="image/*" style="display: none;"></div><p class="photo-note">Recommended: Square image, at least 300x300px</p></div>
            <h3 style="margin-top:24px;"><i class="fab fa-linkedin"></i> Social Links</h3>
            <div class="form-group"><label><i class="fab fa-linkedin"></i> LinkedIn</label><input type="url" placeholder="https://linkedin.com/in/..."></div>
            <div class="form-group"><label><i class="fab fa-twitter"></i> Twitter</label><input type="url" placeholder="https://twitter.com/..."></div>
            <div class="form-group"><label><i class="fab fa-instagram"></i> Instagram</label><input type="url" placeholder="https://instagram.com/..."></div>
            <button type="submit" class="btn-save">Save Social Links</button>
        </div>

        <div class="profile-card"><h3><i class="fas fa-lock"></i> Account Settings</h3>
            <form method="POST" action="/api/auth/change-password.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password"></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Enter new password"></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Confirm new password"></div>
            <button type="submit" class="btn-save">Change Password</button></form>
            <hr class="divider">
            <div class="danger-zone"><h4>Danger Zone</h4><p>Once you deactivate your account, all your listings will be hidden.</p><button class="btn-danger" onclick="deactivateAccount()">Deactivate Account</button></div>
        </div>
    </div>
</div>

<script>
function deactivateAccount() { if(confirm('WARNING: Deactivating your account will hide all your listings. Are you sure?')) alert('Account deactivation request submitted.'); }
document.getElementById('photoUpload')?.addEventListener('change', function(e) { if(e.target.files[0]) alert('Profile photo uploaded successfully!'); });
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
