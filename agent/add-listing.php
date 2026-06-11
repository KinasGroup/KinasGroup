<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';


// Auth: handled by SessionManager::requireAgent()

// KYC soft-guard: unverified agents are warned but can still view the page
$kycStatus = 'pending';
try {
    $st = Database::getInstance()->getConnection()
        ->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $st->execute([(int)$_SESSION['user_id']]);
    $kycStatus = $st->fetchColumn() ?: 'pending';
} catch (Exception $e) { /* table not migrated yet — allow */ }

// Division + super-agent flag (set on login by SessionManager::setUser).
// Super agents can list across all 4 divisions; regular agents are
// restricted to the division they chose at registration.
$agentDivision = $_SESSION['user_division']  ?? null;
$isSuperAgent  = !empty($_SESSION['is_super_agent']);

// Map: DB division value → listing_type (what the API expects) → label
$divisionMap = [
    'automobile'          => ['type' => 'car',         'label' => 'Kinas Automobile',      'opt' => 'automobile'],
    'williams-connect-home'=> ['type' => 'property',   'label' => 'Williams Connect Home', 'opt' => 'realestate'],
    'kinas-volt'          => ['type' => 'solar',       'label' => 'Kinas Volt',            'opt' => 'solar'],
    'kinas-marketplace'   => ['type' => 'marketplace', 'label' => 'Kinas Marketplace',     'opt' => 'marketplace'],
];

$csrf_token = Security::generateCSRFToken();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1200px; margin: 0 auto; padding: 30px; }
.agent-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.btn-secondary { background: #F5F5F5; color: #666; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; border: 1px solid #E0E0E0; }
.btn-secondary:hover { background: #E0E0E0; }
.listing-form { background: white; border-radius: 24px; border: 1px solid #E0E0E0; overflow: hidden; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.form-section { padding: 32px; }
.form-section:first-child { border-right: 1px solid #E0E0E0; }
.form-section h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.3s; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { border: 2px dashed #E0E0E0; border-radius: 16px; padding: 20px; text-align: center; transition: all 0.3s; }
.image-upload-area:hover { border-color: #C6A43F; background: rgba(198,164,63,0.02); }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.upload-placeholder p { margin-bottom: 8px; color: #666; }
.upload-placeholder span { font-size: 12px; color: #999; }
.image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 20px; }
.preview-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: #F5F5F5; }
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; font-size: 14px; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } .form-row { grid-template-columns: 1fr; gap: 0; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .form-section { padding: 24px; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><div><h1><i class="fas fa-plus-circle"></i> Add New Listing</h1><p>Create a new listing in any division</p></div><a href="/agent/listings.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Listings</a></div>

    <?php if (!in_array($kycStatus, ['approved'], true)): ?>
    <div style="background:linear-gradient(135deg,#FFF8E1,#FFF3E0); border:1px solid #FFE0B2; border-radius:16px; padding:18px 24px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
        <i class="fas fa-shield-alt" style="color:#E65100; font-size:24px;"></i>
        <div style="flex:1;">
            <strong style="color:#BF360C;">Verification recommended.</strong>
            <span style="color:#5D4037; font-size:13px;"> Listings from verified agents rank higher and get the verified badge. <a href="/agent/verification.php" style="color:#BF360C; font-weight:600;">Complete KYC →</a></span>
        </div>
    </div>
    <?php endif; ?>

    <form class="listing-form" method="POST" action="/api/listings/create.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-grid">
            <div class="form-section"><h3>Basic Information</h3>
                <div class="form-group"><label><i class="fas fa-layer-group"></i> Division *</label>
                    <?php
                    // Super agent: full 4-division dropdown.
                    // Regular agent: only their own division is available
                    // (server-side enforcement in api/listings/create.php is
                    // the source of truth — this is just UX).
                    $allowed = $isSuperAgent
                        ? array_keys($divisionMap)
                        : ($agentDivision ? [$agentDivision] : []);
                    ?>
                    <select name="division" id="division" required>
                        <option value="">Select Division</option>
                        <?php foreach ($divisionMap as $dbDiv => $info):
                            $sel = (!$isSuperAgent && $dbDiv === $agentDivision) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($info['opt']) ?>" <?= $sel ?>>
                                <?= htmlspecialchars($info['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$isSuperAgent && $agentDivision): ?>
                        <small style="color:#666; font-size:12px;">
                            Your account is registered for <strong><?= htmlspecialchars($divisionMap[$agentDivision]['label'] ?? $agentDivision) ?></strong> only.
                            Contact an admin to be promoted to a Super Agent.
                        </small>
                    <?php endif; ?>
                    <!-- Server reads listing_type (car/property/solar/marketplace), not the frontend 'division' key. -->
                    <input type="hidden" name="listing_type" id="listing_type" value="">
                </div>
                <div class="form-group"><label><i class="fas fa-heading"></i> Listing Title *</label><input type="text" name="title" placeholder="e.g., 2024 Mercedes-Benz S-Class" required></div>
                <div class="form-group"><label><i class="fas fa-align-left"></i> Description *</label><textarea name="description" rows="6" placeholder="Provide a detailed description of your listing..." required></textarea></div>
                <div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Price *</label><div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" placeholder="0" step="0.01" required></div></div><div class="form-group"><label>Price Type</label><select name="price_type"><option value="fixed">Fixed Price</option><option value="negotiable">Negotiable</option><option value="contact">Contact for Price</option></select></div></div>
                <h3>Location</h3>
                <div class="form-row"><div class="form-group"><label>City</label><input type="text" name="city" placeholder="e.g., Lagos"></div><div class="form-group"><label>State/Province</label><input type="text" name="state" placeholder="e.g., Lagos State"></div></div>
                <div class="form-group"><label>Address</label><input type="text" name="address" placeholder="Full address"></div>
            </div>
            <div class="form-section"><h3>Images</h3>
                <div class="image-upload-area"><input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;"><div class="upload-placeholder" onclick="document.getElementById('imageUpload').click()"><i class="fas fa-cloud-upload-alt"></i><p>Click or drag images here</p><span>Upload up to 10 images (Max 5MB each)</span></div><div class="image-preview-grid" id="imagePreviewGrid"></div></div>
                <h3 style="margin-top: 24px;">Additional Details</h3>
                <div class="form-group" id="automobileFields" style="display:none;"><label>Vehicle Details</label><div class="form-row"><select name="make" placeholder="Make"><option value="">Select Make</option><option>Mercedes-Benz</option><option>BMW</option><option>Audi</option></select><input type="text" name="model" placeholder="Model"></div><div class="form-row"><input type="number" name="year" placeholder="Year"><input type="text" name="mileage" placeholder="Mileage (km)"></div><select name="condition"><option value="">Condition</option><option>Brand New</option><option>Like New</option><option>Excellent</option></select></div>
                <div class="form-group" id="realestateFields" style="display:none;"><label>Property Details</label><div class="form-row"><input type="number" name="bedrooms" placeholder="Bedrooms"><input type="number" name="bathrooms" placeholder="Bathrooms"></div><div class="form-row"><input type="text" name="area" placeholder="Area (sq ft)"><select name="property_type"><option value="">Property Type</option><option>Villa</option><option>Apartment</option><option>Land</option></select></div></div>
                <div class="form-group" id="solarFields" style="display:none;"><label>Solar Details</label><div class="form-row"><input type="text" name="capacity" placeholder="Capacity (kW)"><select name="solar_type"><option value="">System Type</option><option>Residential</option><option>Commercial</option><option>Industrial</option></select></div></div>
                <div class="checkbox-group"><label class="checkbox-label"><input type="checkbox" name="featured" value="1"><span>Feature this listing for premium visibility</span></label></div>
                <div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit">Publish Listing</button></div>
            </div>
        </div>
    </form>
</div>

<script>
// Map frontend <select name="division"> values (automobile / realestate /
// solar / marketplace) to the listing_type the API expects
// (car / property / solar / marketplace) and keep them in sync.
const divisionToType = { automobile: 'car', realestate: 'property', solar: 'solar', marketplace: 'marketplace' };

function syncListingType() {
    const d = document.getElementById('division')?.value || '';
    document.getElementById('listing_type').value = divisionToType[d] || '';
    document.getElementById('automobileFields').style.display = d === 'automobile'  ? 'block' : 'none';
    document.getElementById('realestateFields').style.display = d === 'realestate' ? 'block' : 'none';
    document.getElementById('solarFields').style.display       = d === 'solar'      ? 'block' : 'none';
}
document.getElementById('division')?.addEventListener('change', syncListingType);
syncListingType(); // initialise (regular-agent pre-selected option needs to set listing_type too)

const imageUpload = document.getElementById('imageUpload'), previewGrid = document.getElementById('imagePreviewGrid'); let selectedFiles = [];
imageUpload?.addEventListener('change', function(e) { selectedFiles = [...selectedFiles, ...Array.from(e.target.files)]; updatePreview(); });
function updatePreview() { previewGrid.innerHTML = ''; selectedFiles.forEach((file, index) => { const reader = new FileReader(); reader.onload = function(e) { const div = document.createElement('div'); div.className = 'preview-item'; div.innerHTML = `<img src="${e.target.result}"><div class="preview-remove" onclick="removeImage(${index})">&times;</div>`; previewGrid.appendChild(div); }; reader.readAsDataURL(file); }); }
function removeImage(index) { selectedFiles.splice(index, 1); updatePreview(); const dt = new DataTransfer(); selectedFiles.forEach(f => dt.items.add(f)); imageUpload.files = dt.files; }
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
