<?php
/**
 * KINAS GROUP — Agent: Edit Listing
 * Loads the listing from the correct table (car/property/solar/marketplace),
 * pre-fills the form, and posts to /api/listings/update.php.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db       = Database::getInstance()->getConnection();
$agent_id = $_SESSION['user_id'];
$division = $_SESSION['user_division'] ?? null;

$listing_id = (int)($_GET['id'] ?? 0);
if (!$listing_id) { header('Location: /agent/listings.php'); exit; }

// Map agent division → default table; we'll still auto-detect by scanning all 4 tables.
$divisionTableMap = [
    'kinas-automobile'      => 'car_listings',
    'williams-connect-home' => 'property_listings',
    'kinas-volt'            => 'solar_listings',
    'kinas-marketplace'     => 'marketplace_listings',
];
$defaultTable = $divisionTableMap[$division] ?? null;
$tableToType = array_flip([
    'car_listings'         => 'car',
    'property_listings'    => 'property',
    'solar_listings'       => 'solar',
    'marketplace_listings' => 'marketplace',
]);

// Auto-detect which table the listing lives in (since URLs can be shared).
$listing = null;
$listingType = null;
$listingTable = null;
foreach (['car_listings','property_listings','solar_listings','marketplace_listings'] as $t) {
    $stmt = $db->prepare("SELECT * FROM $t WHERE id = ? AND agent_id = ?");
    $stmt->execute([$listing_id, $agent_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $listing = $r;
        $listingTable = $t;
        $listingType = $tableToType[$t];
        break;
    }
}
if (!$listing) {
    $_SESSION['flash_error'] = 'Listing not found.';
    header('Location: /agent/listings.php');
    exit;
}

// Load existing images
$imgStmt = $db->prepare("SELECT id, url, sort_order FROM listing_images WHERE listing_id = ? AND listing_type = ? ORDER BY sort_order");
$imgStmt->execute([$listing_id, $listingType]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// Decode features JSON (car + property)
$features = [];
if (!empty($listing['features'])) {
    $features = is_array($listing['features']) ? $listing['features'] : (json_decode($listing['features'], true) ?: []);
}
$amenities = [];
if (!empty($listing['amenities'])) {
    $amenities = is_array($listing['amenities']) ? $listing['amenities'] : (json_decode($listing['amenities'], true) ?: []);
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

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
.btn-secondary { background: #F5F5F5; color: #666; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #E0E0E0; }
.listing-form { background: white; border-radius: 24px; border: 1px solid #E0E0E0; overflow: hidden; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.form-section { padding: 32px; }
.form-section:first-child { border-right: 1px solid #E0E0E0; }
.form-section h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { border: 2px dashed #E0E0E0; border-radius: 16px; padding: 20px; text-align: center; }
.image-upload-area:hover { border-color: #C6A43F; }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 20px; }
.preview-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: #F5F5F5; }
.preview-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; font-size: 14px; line-height: 1; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.division-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.division-tag { padding: 6px 14px; background: #F0F0F0; border-radius: 20px; font-size: 12px; color: #666; font-weight: 600; }
.division-tag.active { background: #C6A43F; color: #0A0A0A; }
.empty-images { padding: 30px 10px; color: #999; font-size: 13px; text-align: center; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .form-row { grid-template-columns: 1fr; gap: 0; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="agent-header">
        <div>
            <h1><i class="fas fa-edit"></i> Edit Listing</h1>
            <p>Update your listing information · ID #<?= (int)$listing_id ?></p>
        </div>
        <div style="display:flex; gap:10px;">
            <?php
                $detailSlug = ['car' => 'kinas-automobile','property' => 'williams-connect-home','solar' => 'kinas-volt','marketplace' => 'kinas-marketplace'][$listingType];
            ?>
            <a href="/divisions/<?= $detailSlug ?>/detail.php?id=<?= (int)$listing_id ?>" target="_blank" class="btn-secondary"><i class="fas fa-external-link-alt"></i> View Live</a>
            <a href="/agent/listings.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Listings</a>
        </div>
    </div>

    <div class="division-tabs">
        <?php
            $divisionNames = ['car' => 'Kinas Automobile','property' => 'Williams Connect Home','solar' => 'Kinas Volt','marketplace' => 'Kinas Marketplace'];
        ?>
        <span class="division-tag active"><i class="fas fa-tag"></i> <?= htmlspecialchars($divisionNames[$listingType] ?? $listingType) ?></span>
        <span class="division-tag" style="background:#F8F8F8;">Status: <strong style="margin-left:4px;"><?= htmlspecialchars(ucfirst($listing['status'] ?? 'active')) ?></strong></span>
    </div>

    <form class="listing-form" method="POST" action="/api/listings/update.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="listing_id" value="<?= (int)$listing_id ?>">
        <input type="hidden" name="listing_type" value="<?= htmlspecialchars($listingType) ?>">
        <input type="hidden" name="redirect" value="/agent/listings.php">
        <div class="form-grid">
            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> Listing Title *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($listing['title'] ?? '') ?>" placeholder="e.g., 2024 Mercedes-Benz S-Class">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description *</label>
                    <textarea name="description" rows="6" required placeholder="Describe your listing in detail…"><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Price (₦) *</label>
                        <div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" step="0.01" min="0" required value="<?= htmlspecialchars((string)($listing['price'] ?? '')) ?>" placeholder="0"></div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php
                                $opts = $listingType === 'solar' ? ['active','inactive','flagged','removed'] : ['active','pending','sold','flagged','removed'];
                                foreach ($opts as $s):
                            ?>
                                <option value="<?= $s ?>" <?= ($listing['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h3>Location</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($listing['city'] ?? '') ?>" placeholder="e.g., Lagos">
                    </div>
                    <div class="form-group">
                        <label>State / Province</label>
                        <input type="text" name="state" value="<?= htmlspecialchars($listing['state'] ?? '') ?>" placeholder="e.g., Lagos State">
                    </div>
                </div>
                <?php if ($listingType === 'property'): ?>
                <div class="form-group">
                    <label>Street Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($listing['address'] ?? '') ?>" placeholder="Full street address">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section">
                <?php
                    $showCarFields   = $listingType === 'car';
                    $showPropFields  = $listingType === 'property';
                    $showSolarFields = $listingType === 'solar';
                    $showMarketFields= $listingType === 'marketplace';
                ?>

                <?php if ($showCarFields): ?>
                <h3>Vehicle Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?= htmlspecialchars($listing['brand'] ?? '') ?>" placeholder="e.g., Mercedes-Benz">
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" value="<?= htmlspecialchars($listing['model'] ?? '') ?>" placeholder="e.g., S-Class">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" name="year" min="1900" max="<?= date('Y') + 1 ?>" value="<?= htmlspecialchars((string)($listing['year'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Mileage (km)</label>
                        <input type="number" name="mileage" min="0" value="<?= htmlspecialchars((string)($listing['mileage'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Transmission</label>
                        <select name="transmission">
                            <?php foreach (['Automatic','Manual','CVT','Semi-Automatic'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['transmission'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fuel Type</label>
                        <select name="fuel_type">
                            <?php foreach (['Petrol','Diesel','Hybrid','Electric','LPG'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['fuel_type'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Body Type</label>
                        <input type="text" name="body_type" value="<?= htmlspecialchars($listing['body_type'] ?? '') ?>" placeholder="e.g., Sedan, SUV">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color" value="<?= htmlspecialchars($listing['color'] ?? '') ?>" placeholder="e.g., Obsidian Black">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Drivetrain</label>
                        <select name="drivetrain">
                            <?php foreach (['RWD','FWD','AWD','4WD'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['drivetrain'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Doors</label>
                        <input type="number" name="doors" min="0" max="10" value="<?= htmlspecialchars((string)($listing['doors'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition_status">
                        <?php foreach (['Brand New','Like New','Excellent','Good','Fair'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= ($listing['condition_status'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>VIN (Vehicle Identification Number)</label>
                    <input type="text" name="vin" value="<?= htmlspecialchars($listing['vin'] ?? '') ?>" placeholder="Optional">
                </div>
                <?php endif; ?>

                <?php if ($showPropFields): ?>
                <h3>Property Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Property Type</label>
                        <select name="property_type">
                            <?php foreach (['Villa','Apartment','Townhouse','Penthouse','Land','Commercial','Office'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['property_type'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Listing Type</label>
                        <select name="listing_type">
                            <?php foreach (['sale','rent'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['listing_type'] ?? '') === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bedrooms</label>
                        <input type="number" name="beds" min="0" value="<?= htmlspecialchars((string)($listing['beds'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Bathrooms</label>
                        <input type="number" name="baths" min="0" value="<?= htmlspecialchars((string)($listing['baths'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Area (sq ft)</label>
                        <input type="number" name="sqft" min="0" value="<?= htmlspecialchars((string)($listing['sqft'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Year Built</label>
                        <input type="number" name="year_built" min="1800" max="<?= date('Y') ?>" value="<?= htmlspecialchars((string)($listing['year_built'] ?? '')) ?>">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($showSolarFields): ?>
                <h3>Solar System Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Service Type</label>
                        <select name="service_type">
                            <?php foreach (['residential','commercial','industrial','maintenance','financing'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['service_type'] ?? '') === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?= htmlspecialchars($listing['brand'] ?? '') ?>" placeholder="e.g., Jinko Solar">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Capacity (kW)</label>
                        <input type="number" name="capacity_kw" step="0.01" min="0" value="<?= htmlspecialchars((string)($listing['capacity_kw'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Warranty (years)</label>
                        <input type="number" name="warranty_years" min="0" max="50" value="<?= htmlspecialchars((string)($listing['warranty_years'] ?? '')) ?>">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($showMarketFields): ?>
                <h3>Marketplace Item</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" value="<?= htmlspecialchars($listing['brand'] ?? '') ?>" placeholder="e.g., Rolex">
                    </div>
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition_status">
                            <?php foreach (['Brand New','Like New','Excellent','Good','For Parts'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($listing['condition_status'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <h3 style="margin-top:24px;">Images</h3>
                <div class="image-upload-area">
                    <input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;">
                    <div class="upload-placeholder" onclick="document.getElementById('imageUpload').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to add more images</p>
                        <span style="font-size:12px; color:#999;">Upload up to 10 images (Max 5MB each)</span>
                    </div>
                    <div class="image-preview-grid" id="imagePreviewGrid">
                        <?php if (empty($images)): ?>
                            <div class="empty-images" style="grid-column: 1 / -1;">No images uploaded yet. Use the upload area above to add some.</div>
                        <?php else: ?>
                            <?php foreach ($images as $img): ?>
                                <div class="preview-item" data-img-id="<?= (int)$img['id'] ?>">
                                    <img src="<?= htmlspecialchars($img['url']) ?>" alt="">
                                    <button type="button" class="preview-remove" onclick="removeExistingImage(<?= (int)$img['id'] ?>, this)" title="Remove">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="checkbox-group" style="margin-top:24px;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="featured" value="1" <?= !empty($listing['featured']) ? 'checked' : '' ?>>
                        <span>Feature this listing for premium visibility</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button>
                    <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function removeExistingImage(imgId, btn) {
    if (!confirm('Remove this image? This cannot be undone.')) return;
    var formData = new FormData();
    formData.append('csrf_token', '<?= $csrf_token ?>');
    formData.append('image_id', imgId);
    fetch('/api/listings/delete-image.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                var item = btn.closest('.preview-item');
                if (item) item.remove();
                // If no images left, show empty state
                var grid = document.getElementById('imagePreviewGrid');
                if (grid && grid.children.length === 0) {
                    grid.innerHTML = '<div class="empty-images" style="grid-column: 1 / -1;">No images uploaded yet. Use the upload area above to add some.</div>';
                }
            } else {
                alert(d.error || 'Failed to remove image.');
            }
        })
        .catch(() => alert('Network error.'));
}

// Preview new uploads
document.getElementById('imageUpload')?.addEventListener('change', function() {
    var grid = document.getElementById('imagePreviewGrid');
    if (!grid) return;
    // Clear "no images" placeholder
    var placeholder = grid.querySelector('.empty-images');
    if (placeholder) placeholder.remove();
    Array.from(this.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var div = document.createElement('div');
            div.className = 'preview-item';
            div.style.border = '2px solid #C6A43F';
            div.innerHTML = '<img src="' + e.target.result + '" alt="">';
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

// Form: disable button on submit
document.querySelector('.listing-form')?.addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
});
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
