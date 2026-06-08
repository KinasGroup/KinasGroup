<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';


// Auth: handled by SessionManager::requireAgent()

$listing_id = $_GET['id'] ?? 0;
if (!$listing_id) { header('Location: /agent/listings.php'); exit; }

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
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; }
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
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } }
@media (max-width: 768px) { .agent-container { padding: 20px; } .form-row { grid-template-columns: 1fr; gap: 0; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><div><h1><i class="fas fa-edit"></i> Edit Listing</h1><p>Update your listing information</p></div><a href="/agent/listings.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Listings</a></div>

    <form class="listing-form" method="POST" action="/api/listings/update.php" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>"><input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
        <div class="form-grid">
            <div class="form-section"><h3>Basic Information</h3>
                <div class="form-group"><label><i class="fas fa-layer-group"></i> Division</label><select name="division" id="division"><option value="automobile" selected>Kinas Automobile</option><option value="realestate">Williams Connect Home</option><option value="solar">Kinas Volt</option><option value="marketplace">Kinas Marketplace</option></select></div>
                <div class="form-group"><label><i class="fas fa-heading"></i> Listing Title</label><input type="text" name="title" value="2024 Mercedes-Benz S-Class" required></div>
                <div class="form-group"><label><i class="fas fa-align-left"></i> Description</label><textarea name="description" rows="6" required>Experience unparalleled luxury with the 2024 Mercedes-Benz S-Class. Features include leather interior, advanced safety systems, and a powerful V8 engine.</textarea></div>
                <div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Price</label><div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" value="185000000" step="0.01" required></div></div><div class="form-group"><label>Price Type</label><select name="price_type"><option value="fixed" selected>Fixed Price</option><option value="negotiable">Negotiable</option><option value="contact">Contact for Price</option></select></div></div>
                <h3>Location</h3><div class="form-row"><div class="form-group"><label>City</label><input type="text" name="city" value="Lagos"></div><div class="form-group"><label>State/Province</label><input type="text" name="state" value="Lagos State"></div></div>
                <div class="form-group"><label>Address</label><input type="text" name="address" value="123 Victoria Island"></div>
            </div>
            <div class="form-section"><h3>Images</h3>
                <div class="image-upload-area"><input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;"><div class="upload-placeholder" onclick="document.getElementById('imageUpload').click()"><i class="fas fa-cloud-upload-alt"></i><p>Add more images</p><span>Upload up to 10 images</span></div><div class="image-preview-grid"><div class="preview-item"><img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=100&q=80"><div class="preview-remove" onclick="removeExistingImage(1)">&times;</div></div></div></div>
                <h3 style="margin-top:24px;">Additional Details</h3>
                <div class="form-group" id="automobileFields"><label>Vehicle Details</label><div class="form-row"><select name="make"><option selected>Mercedes-Benz</option><option>BMW</option><option>Audi</option></select><input type="text" name="model" value="S-Class"></div><div class="form-row"><input type="number" name="year" value="2024"><input type="text" name="mileage" value="5,000 km"></div><select name="condition"><option selected>Brand New</option><option>Like New</option><option>Excellent</option></select></div>
                <div class="form-group"><label>Status</label><select name="status"><option value="active" selected>Active</option><option value="pending">Pending Review</option><option value="inactive">Inactive</option></select></div>
                <div class="checkbox-group"><label class="checkbox-label"><input type="checkbox" name="featured" value="1"><span>Feature this listing for premium visibility</span></label></div>
                <div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit">Save Changes</button></div>
            </div>
        </div>
    </form>
</div>

<script>
function removeExistingImage(id) { if(confirm('Remove this image?')) alert('Image removed'); }
document.getElementById('division')?.addEventListener('change', function() { const d = this.value; document.getElementById('automobileFields').style.display = d === 'automobile' ? 'block' : 'none'; });
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
