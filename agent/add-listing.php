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

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Map: DB division value → listing_type (what the API expects) → label
$divisionMap = [
    'kinas-automobile'    => ['type' => 'car',         'label' => 'Kinas Automobile',      'opt' => 'automobile'],
    'williams-connect-home'=> ['type' => 'property',   'label' => 'Williams Connect Home', 'opt' => 'realestate'],
    'kinas-volt'          => ['type' => 'solar',       'label' => 'Kinas Volt',            'opt' => 'solar'],
    'kinas-marketplace'   => ['type' => 'marketplace', 'label' => 'Kinas Marketplace',     'opt' => 'marketplace'],
];

// Generate CSRF token BEFORE including header
$csrf_token = Security::generateCSRFToken();

// Set page title before including header
$pageTitle = 'Add Listing - Agent Dashboard';

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
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.3s; background: #fff; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.1); }
.form-group select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { border: 2px dashed #E0E0E0; border-radius: 16px; padding: 20px; text-align: center; transition: all 0.3s; position: relative; }
.image-upload-area:hover { border-color: #C6A43F; background: rgba(198,164,63,0.02); }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.upload-placeholder p { margin-bottom: 8px; color: #666; }
.upload-placeholder span { font-size: 12px; color: #999; }
.image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 20px; }
.preview-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: #F5F5F5; }
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; font-size: 14px; border: none; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
.automobile-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.automobile-fields-grid .form-group { margin-bottom: 0; }
.full-width { grid-column: 1 / -1; }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } .form-row { grid-template-columns: 1fr; gap: 0; } .automobile-fields-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .form-section { padding: 24px; } }

/* Upload loading overlay */
.upload-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', sans-serif;
    border-radius: 16px;
    z-index: 10;
}
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

    <?php if ($flashSuccess): ?>
        <div style="background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div style="background:#FFEBEE; border:1px solid #EF9A9A; color:#B71C1C; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <form class="listing-form" method="POST" action="/api/listings/create.php" enctype="multipart/form-data" id="listingForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
                <div class="form-row"><div class="form-group"><label><i class="fas fa-map-marker-alt"></i> City</label><input type="text" name="city" placeholder="e.g., Bellevue"></div><div class="form-group"><label>State/Province</label><input type="text" name="state" placeholder="e.g., WA"></div></div>
                <div class="form-group"><label><i class="fas fa-location-dot"></i> Address</label><input type="text" name="address" placeholder="Full address, e.g., 1880 136th Place NE"></div>
                <div class="form-group"><label><i class="fas fa-globe"></i> Country</label><input type="text" name="country" placeholder="e.g., United States" value="Nigeria"></div>
            </div>
            <div class="form-section"><h3>Images</h3>
                <div class="image-upload-area"><input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;"><div class="upload-placeholder" onclick="if (document.getElementById('imageUpload').dataset.kinasProcessing === '1') return; document.getElementById('imageUpload').click()"><i class="fas fa-cloud-upload-alt"></i><p>Click or drag images here</p><span>Upload up to 10 images (Max 5MB each)</span></div><div class="image-preview-grid" id="imagePreviewGrid"></div></div>

                <!-- AUTOMOBILE DETAILS SECTION -->
                <div id="automobileFields" style="display:none; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-car"></i> Automobile Details</h3>
                    <div class="automobile-fields-grid">
                        <!-- Make (Brand) -->
                        <div class="form-group"><label><i class="fas fa-tag"></i> Make *</label>
                            <select name="brand" required>
                                <option value="">Select Make</option>
                                <option value="Acura">Acura</option>
                                <option value="Alfa Romeo">Alfa Romeo</option>
                                <option value="Aston Martin">Aston Martin</option>
                                <option value="Audi">Audi</option>
                                <option value="Bentley">Bentley</option>
                                <option value="BMW">BMW</option>
                                <option value="Bugatti">Bugatti</option>
                                <option value="Buick">Buick</option>
                                <option value="Cadillac">Cadillac</option>
                                <option value="Chevrolet">Chevrolet</option>
                                <option value="Chrysler">Chrysler</option>
                                <option value="Citroen">Citroen</option>
                                <option value="Dodge">Dodge</option>
                                <option value="Ferrari">Ferrari</option>
                                <option value="Fiat">Fiat</option>
                                <option value="Ford">Ford</option>
                                <option value="Genesis">Genesis</option>
                                <option value="GMC">GMC</option>
                                <option value="Honda">Honda</option>
                                <option value="Hyundai">Hyundai</option>
                                <option value="Infiniti">Infiniti</option>
                                <option value="Jaguar">Jaguar</option>
                                <option value="Jeep">Jeep</option>
                                <option value="Kia">Kia</option>
                                <option value="Lamborghini">Lamborghini</option>
                                <option value="Land Rover">Land Rover</option>
                                <option value="Lexus">Lexus</option>
                                <option value="Maserati">Maserati</option>
                                <option value="Mazda">Mazda</option>
                                <option value="McLaren">McLaren</option>
                                <option value="Mercedes-Benz">Mercedes-Benz</option>
                                <option value="Mini">Mini</option>
                                <option value="Mitsubishi">Mitsubishi</option>
                                <option value="Nissan">Nissan</option>
                                <option value="Porsche">Porsche</option>
                                <option value="Ram">Ram</option>
                                <option value="Rolls-Royce">Rolls-Royce</option>
                                <option value="Subaru">Subaru</option>
                                <option value="Tesla">Tesla</option>
                                <option value="Toyota">Toyota</option>
                                <option value="Volkswagen">Volkswagen</option>
                                <option value="Volvo">Volvo</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <!-- Model -->
                        <div class="form-group"><label><i class="fas fa-car"></i> Model *</label><input type="text" name="model" placeholder="e.g., S-Class" required></div>
                        <div class="form-group"><label><i class="fas fa-calendar"></i> Year *</label><input type="number" name="year" placeholder="e.g., 2018" min="1900" max="2099" required></div>
                        <div class="form-group"><label><i class="fas fa-tachometer-alt"></i> Mileage *</label><input type="text" name="mileage" placeholder="e.g., 19592 mi (31530 km)" required></div>
                        <div class="form-group"><label><i class="fas fa-cog"></i> Engine</label><input type="text" name="engine" placeholder="e.g., 6 Cylinder"></div>
                        <div class="form-group"><label><i class="fas fa-cogs"></i> Gearbox / Transmission</label>
                            <select name="gearbox"><option value="">Select Gearbox</option><option value="Automatic">Automatic</option><option value="Manual">Manual</option><option value="Semi-Automatic">Semi-Automatic</option><option value="CVT">CVT</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-car-side"></i> Car Type</label>
                            <select name="car_type"><option value="">Select Car Type</option><option value="Coupe">Coupe</option><option value="Sedan">Sedan</option><option value="SUV">SUV</option><option value="Convertible">Convertible</option><option value="Hatchback">Hatchback</option><option value="Wagon">Wagon</option><option value="Truck">Truck</option><option value="Van">Van</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-steering-wheel"></i> Drive</label>
                            <select name="drive"><option value="">Select Drive</option><option value="LHD">LHD (Left-Hand Drive)</option><option value="RHD">RHD (Right-Hand Drive)</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-road"></i> Drive Train</label>
                            <select name="drive_train"><option value="">Select Drive Train</option><option value="AWD">AWD (All-Wheel Drive)</option><option value="FWD">FWD (Front-Wheel Drive)</option><option value="RWD">RWD (Rear-Wheel Drive)</option><option value="4WD">4WD (Four-Wheel Drive)</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-gas-pump"></i> Fuel Type</label>
                            <select name="fuel_type"><option value="">Select Fuel Type</option><option value="Petrol">Petrol</option><option value="Diesel">Diesel</option><option value="Electric">Electric</option><option value="Hybrid">Hybrid</option><option value="Plugin-Hybrid">Plugin Hybrid</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-clipboard-check"></i> Condition</label>
                            <select name="condition"><option value="">Select Condition</option><option value="Brand New">Brand New</option><option value="Like New">Like New</option><option value="Excellent">Excellent</option><option value="Very Good">Very Good</option><option value="Good">Good</option><option value="Fair">Fair</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-barcode"></i> VIN</label><input type="text" name="vin" placeholder="e.g., 19UNC1B01JY000027"></div>
                        <div class="form-group"><label><i class="fas fa-palette"></i> Color</label><input type="text" name="color" placeholder="e.g., Silver"></div>
                        <div class="form-group"><label><i class="fas fa-palette"></i> Interior Color</label><input type="text" name="interior_color" placeholder="e.g., Grey"></div>
                        <div class="form-group"><label><i class="fas fa-door-open"></i> Doors</label>
                            <select name="doors"><option value="">Select Doors</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option></select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-users"></i> Seats</label>
                            <select name="seats"><option value="">Select Seats</option><option value="2">2</option><option value="4">4</option><option value="5">5</option><option value="7">7</option><option value="8">8</option></select>
                        </div>
                        <div class="form-group full-width"><label><i class="fas fa-check-circle"></i> Features (comma separated)</label><input type="text" name="features" placeholder="e.g., Leather seats, Sunroof, Navigation, Backup camera"></div>
                    </div>
                </div>

                <!-- PROPERTY DETAILS SECTION -->
                <div id="realestateFields" style="display:none; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-home"></i> Property Details</h3>
                    <div class="automobile-fields-grid">
                        <div class="form-group"><label>Listing Type</label>
                            <select name="listing_type_purpose" id="listing_type_purpose">
                                <option value="sale">For Sale</option>
                                <option value="rent">For Rent</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Bedrooms</label><input type="number" name="bedrooms" placeholder="e.g., 3"></div>
                        <div class="form-group"><label>Bathrooms</label><input type="number" name="bathrooms" placeholder="e.g., 2"></div>
                        <div class="form-group"><label>Area (sq ft)</label><input type="text" name="area" placeholder="e.g., 2500"></div>
                        <div class="form-group"><label>Property Type</label>
                            <select name="property_type"><option value="">Select Type</option><option value="Villa">Villa</option><option value="Apartment">Apartment</option><option value="Land">Land</option><option value="House">House</option><option value="Condo">Condo</option><option value="Townhouse">Townhouse</option></select>
                        </div>
                    </div>
                </div>

                <!-- SOLAR DETAILS SECTION -->
                <div id="solarFields" style="display:none; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-sun"></i> Solar Details</h3>
                    <div class="automobile-fields-grid">
                        <div class="form-group"><label>Capacity (kW)</label><input type="text" name="capacity" placeholder="e.g., 10"></div>
                        <div class="form-group"><label>System Type</label>
                            <select name="solar_type"><option value="">Select Type</option><option value="Residential">Residential</option><option value="Commercial">Commercial</option><option value="Industrial">Industrial</option></select>
                        </div>
                    </div>
                </div>

                <div class="checkbox-group" style="margin-top:24px;"><label class="checkbox-label"><input type="checkbox" name="featured" value="1"><span>Feature this listing for premium visibility</span></label></div>
                <div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit" id="submitBtn">Publish Listing</button></div>
            </div>
        </div>
    </form>
</div>

<script>
// ============================================
// FIX: SYNC LISTING TYPE ON PAGE LOAD AND CHANGE
// ============================================
const divisionToType = { 
    automobile: 'car', 
    realestate: 'property', 
    solar: 'solar', 
    marketplace: 'marketplace' 
};

function syncListingType() {
    const d = document.getElementById('division')?.value || '';
    const listingType = divisionToType[d] || '';
    document.getElementById('listing_type').value = listingType;
    
    // Show/hide division-specific fields
    const automobileDiv = document.getElementById('automobileFields');
    const realestateDiv = document.getElementById('realestateFields');
    const solarDiv = document.getElementById('solarFields');
    
    if (automobileDiv) automobileDiv.style.display = d === 'automobile' ? 'block' : 'none';
    if (realestateDiv) realestateDiv.style.display = d === 'realestate' ? 'block' : 'none';
    if (solarDiv) solarDiv.style.display = d === 'solar' ? 'block' : 'none';
    
    console.log('Division selected:', d, 'Listing type set to:', listingType);
}

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial state
    syncListingType();
    
    // Attach event listener
    const divisionSelect = document.getElementById('division');
    if (divisionSelect) {
        divisionSelect.addEventListener('change', syncListingType);
    }
});

// ============================================
// FORM SUBMISSION HANDLER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('listingForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Validate required fields
            const division = document.getElementById('division').value;
            const title = form.querySelector('input[name="title"]')?.value?.trim() || '';
            const price = form.querySelector('input[name="price"]')?.value?.trim() || '';
            const description = form.querySelector('textarea[name="description"]')?.value?.trim() || '';
            
            if (!division) {
                alert('Please select a division.');
                return;
            }
            if (!title) {
                alert('Please enter a listing title.');
                return;
            }
            if (!price || parseFloat(price) <= 0) {
                alert('Please enter a valid price greater than zero.');
                return;
            }
            if (!description) {
                alert('Please enter a description.');
                return;
            }
            
            // Make sure listing_type is set
            syncListingType();
            const listingType = document.getElementById('listing_type')?.value || '';
            
            if (!listingType) {
                alert('Please select a valid division.');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
            
            // Submit the form
            form.submit();
        });
    }
});

// ============================================
// IMAGE PREVIEW
// ============================================
const imageUpload = document.getElementById('imageUpload');
const previewGrid = document.getElementById('imagePreviewGrid');
let selectedFiles = [];
let selectedFilePreviewUrls = []; // objectURLs, kept 1:1 with selectedFiles so we can revoke them

function syncInputFiles() { 
    const dt = new DataTransfer(); 
    selectedFiles.forEach(f => dt.items.add(f)); 
    if (imageUpload) imageUpload.files = dt.files; 
}

function updatePreview() { 
    if (!previewGrid) return;
    previewGrid.innerHTML = ''; 

    // Rebuild the objectURL cache to match selectedFiles 1:1 (revoking any
    // URLs we no longer need so we don't leak memory).
    selectedFilePreviewUrls.forEach(url => URL.revokeObjectURL(url));
    selectedFilePreviewUrls = selectedFiles.map(file => URL.createObjectURL(file));

    // createObjectURL is synchronous, so — unlike the previous FileReader
    // version — there's no async gap where a second, overlapping call to
    // updatePreview() could interleave and append images out of order or
    // bind a "remove" button to the wrong index.
    selectedFiles.forEach((file, index) => { 
        const div = document.createElement('div'); 
        div.className = 'preview-item'; 
        div.innerHTML = `<img src="${selectedFilePreviewUrls[index]}"><button type="button" class="preview-remove" onclick="removeImage(${index})">&times;</button>`; 
        previewGrid.appendChild(div); 
    }); 
}

function removeImage(index) { 
    selectedFiles.splice(index, 1); 
    updatePreview(); 
    syncInputFiles(); 
}

if (imageUpload) {
    // NOTE: we intentionally listen for 'kinas:images-ready' (dispatched by
    // assets/js/image-upload.js once it has finished compressing) rather
    // than the native 'change' event. The compression module used to
    // re-dispatch 'change' on this same input after compressing, which
    // re-triggered this exact listener (and the compression listener)
    // over and over — a single file selection could add the same image
    // 100+ times in a couple of seconds. 'kinas:images-ready' only ever
    // fires once per genuine selection, so that loop can't happen.
    imageUpload.addEventListener('kinas:images-ready', function(e) {
        const newFiles = e.detail && Array.isArray(e.detail.files) ? e.detail.files : Array.from(imageUpload.files || []);
        selectedFiles = [...selectedFiles, ...newFiles];
        syncInputFiles();
        updatePreview();
    });
}

</script>

</main>
</div>

<!-- Image Optimization - Client-side compression -->
<?php $__imgUploadJsV = @filemtime(__DIR__ . '/../assets/js/image-upload.js') ?: time(); ?>
<script src="/assets/js/image-upload.js?v=<?= $__imgUploadJsV ?>"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
