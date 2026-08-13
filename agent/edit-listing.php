<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';
if (!isset($_SESSION['user_id'])) { header('Location: /auth/login.php'); exit; }
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent') { header('Location: /auth/login.php'); exit; }
$db = Database::getInstance()->getConnection();
$agentId = (int)$_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$divisionParam = isset($_GET['division']) ? $_GET['division'] : '';
if (!$listingId || !$divisionParam) { header('Location: listings.php?error=Invalid listing'); exit; }
$tableMap = ['solar'=>'solar_listings','car'=>'car_listings','property'=>'property_listings','marketplace'=>'marketplace_listings'];
if (!isset($tableMap[$divisionParam])) { header('Location: listings.php?error=Invalid division'); exit; }
$table = $tableMap[$divisionParam];
$stmt = $db->prepare("SELECT * FROM $table WHERE id = ? AND agent_id = ?");
$stmt->execute([$listingId, $agentId]);
$listing = $stmt->fetch();
if (!$listing) { header('Location: listings.php?error=Listing not found'); exit; }
$imageStmt = $db->prepare("SELECT id, url FROM listing_images WHERE listing_id = ? AND listing_type = ? ORDER BY sort_order");
$imageStmt->execute([$listingId, $divisionParam]);
$existingImages = $imageStmt->fetchAll();
$kycStatus = 'pending'; $isBusinessAgent = false; $kybApprovedAgent = false;
try {
$st = $db->prepare("SELECT verification_status, company_name, kyb_status FROM agent_profiles WHERE user_id = ?");
$st->execute([$agentId]);
$agentRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$kycStatus = $agentRow['verification_status'] ?? 'pending';
$isBusinessAgent = trim((string)($agentRow['company_name'] ?? '')) !== '';
$kybApprovedAgent = ($agentRow['kyb_status'] ?? '') === 'approved';
} catch (Exception $e) {}
$canOfferRental = $isBusinessAgent && $kybApprovedAgent;
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$divisionMap = [
'kinas-automobile'    => ['type' => 'car',         'label' => 'Kinas Automobile',      'opt' => 'automobile'],
'williams-connect-home'=> ['type' => 'property',   'label' => 'Williams Connect Home', 'opt' => 'realestate'],
'kinas-volt'          => ['type' => 'solar',       'label' => 'Kinas Volt',            'opt' => 'solar'],
'kinas-marketplace'   => ['type' => 'marketplace', 'label' => 'Kinas Marketplace',     'opt' => 'marketplace'],
];
$csrf_token = Security::generateCSRFToken();
$pageTitle = 'Edit Listing - Agent Dashboard';
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
.listing-form { background: white; border-radius: 24px; border: 1px solid #E0E0E0; overflow: hidden; width: 100%; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.form-section { padding: 32px; }
.form-section:first-child { border-right: 1px solid #E0E0E0; }
.form-section h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 12px; font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.3s; background: #fff; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #C6A43F; box-shadow: 0 0 0 3px rgba(198,164,63,0.1); }
.form-group select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; padding-right: 40px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { border: 2px dashed #E0E0E0; border-radius: 16px; padding: 20px; text-align: center; transition: all 0.3s; width: 100%; position: relative; }
.image-upload-area:hover { border-color: #C6A43F; background: rgba(198,164,63,0.02); }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.upload-placeholder p { margin-bottom: 8px; color: #666; }
.upload-placeholder span { font-size: 12px; color: #999; }
.image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 20px; }
.preview-item { position: relative; border-radius: 12px; overflow: hidden; aspect-ratio: 1; background: #F5F5F5; border: 2px solid #E0E0E0; }
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-item.existing { border-color: #2E7D32; }
.preview-item.existing .preview-badge { position: absolute; top: 4px; left: 4px; background: #2E7D32; color: white; font-size: 9px; padding: 2px 8px; border-radius: 4px; font-weight: 600; }
.preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; font-size: 14px; border: none; transition: all 0.3s; }
.preview-remove:hover { background: #C62828; }
.preview-remove.loading { opacity: 0.5; pointer-events: none; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #E0E0E0; flex-wrap: wrap; }
.btn-cancel { padding: 12px 28px; background: #F5F5F5; border: none; border-radius: 40px; color: #666; cursor: pointer; font-weight: 600; }
.btn-submit { padding: 12px 32px; background: #C6A43F; border: none; border-radius: 40px; font-weight: 600; color: #0A0A0A; cursor: pointer; transition: all 0.3s; }
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
.automobile-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.automobile-fields-grid .form-group { margin-bottom: 0; }
.full-width { grid-column: 1 / -1; }
.upload-loading-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); color:white; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:'Inter',sans-serif; border-radius:16px; z-index:10; }
@media (max-width: 968px) { .form-grid { grid-template-columns: 1fr; } .form-section:first-child { border-right: none; border-bottom: 1px solid #E0E0E0; } .form-row { grid-template-columns: 1fr; gap: 0; } .automobile-fields-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .agent-container { padding: 15px !important; } .form-section { padding: 16px !important; } .agent-header h1 { font-size: 22px !important; } .form-section h3 { font-size: 16px !important; } .form-group input, .form-group select, .form-group textarea { font-size: 14px !important; padding: 10px 14px !important; } .btn-submit, .btn-cancel { width: 100% !important; justify-content: center !important; text-align: center !important; } .form-actions { flex-direction: column !important; gap: 10px !important; } .image-preview-grid { grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)) !important; gap: 8px !important; } .preview-item { aspect-ratio: 1 !important; } }
@media (max-width: 480px) { .agent-container { padding: 10px !important; } .form-section { padding: 12px !important; } .agent-header { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; } .btn-secondary { width: 100% !important; justify-content: center !important; } .image-preview-grid { grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)) !important; gap: 6px !important; } }
#kinasConfirmOverlay{display:none;position:fixed;inset:0;z-index:99999;background:rgba(10,10,10,.72);backdrop-filter:blur(4px);align-items:center;justify-content:center;animation:kinasOverlayIn .2s ease}
#kinasConfirmOverlay.active{display:flex}
@keyframes kinasOverlayIn{from{opacity:0}to{opacity:1}}
#kinasConfirmBox{background:#fff;border-radius:16px;width:min(440px,92vw);overflow:hidden;box-shadow:0 32px 80px rgba(0,0,0,.28),0 0 0 1px rgba(0,0,0,.06);animation:kinasBoxIn .25s cubic-bezier(.34,1.56,.64,1)}
@keyframes kinasBoxIn{from{opacity:0;transform:scale(.88) translateY(16px)}to{opacity:1;transform:scale(1) translateY(0)}}
#kinasConfirmHead{padding:28px 28px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:flex-start;gap:16px}
#kinasConfirmIconWrap{width:48px;height:48px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(220,38,38,.1);font-size:20px;color:#DC2626}
#kinasConfirmIconWrap.is-warning{background:rgba(245,158,11,.12);color:#D97706}
#kinasConfirmIconWrap.is-gold{background:rgba(198,164,63,.12);color:#C6A43F}
#kinasConfirmTitleWrap{flex:1}
#kinasConfirmTitle{font-family:'Prata',Georgia,serif;font-size:18px;font-weight:400;color:#0A0A0A;margin:0 0 4px;line-height:1.3}
#kinasConfirmSubtitle{font-size:13px;color:#777;margin:0;line-height:1.5;font-family:'Inter',sans-serif}
#kinasConfirmMsg{padding:18px 28px 0;font-size:14px;color:#444;line-height:1.65;font-family:'Inter',sans-serif;background:#fafafa;margin:0}
#kinasConfirmWarningBadge{display:none;margin:16px 28px 0;background:#FEF9EC;border:1px solid #F5E6B0;border-radius:8px;padding:10px 14px;font-size:12px;color:#92660A;font-family:'Inter',sans-serif;align-items:center;gap:8px}
#kinasConfirmWarningBadge.visible{display:flex}
#kinasConfirmActions{display:flex;gap:10px;padding:20px 28px 24px;background:#fafafa;justify-content:flex-end}
#kinasConfirmCancel{padding:10px 22px;border-radius:999px;border:1.5px solid #e0e0e0;background:#fff;color:#555;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;letter-spacing:.3px;cursor:pointer;transition:all .2s;text-transform:uppercase}
#kinasConfirmCancel:hover{border-color:#aaa;color:#222}
#kinasConfirmProceed{padding:10px 22px;border-radius:999px;border:none;background:#DC2626;color:#fff;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;letter-spacing:.3px;cursor:pointer;transition:all .2s;text-transform:uppercase;display:flex;align-items:center;gap:7px}
#kinasConfirmProceed:hover{background:#b91c1c;transform:translateY(-1px)}
#kinasConfirmProceed.is-warning{background:#D97706}
#kinasConfirmProceed.is-warning:hover{background:#b45309}
#kinasConfirmProceed.is-gold{background:#C6A43F;color:#0A0A0A}
#kinasToastContainer{position:fixed;bottom:28px;right:28px;z-index:100000;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.kinas-toast{min-width:280px;max-width:380px;background:#1A1A1A;color:#fff;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 32px rgba(0,0,0,.22);font-family:'Inter',sans-serif;font-size:13.5px;line-height:1.45;pointer-events:all;position:relative;animation:kinasToastIn .3s cubic-bezier(.34,1.4,.64,1);border-left:3px solid #C6A43F}
.kinas-toast.is-error{border-left-color:#EF4444}.kinas-toast.is-success{border-left-color:#22C55E}
.kinas-toast i{font-size:15px;flex-shrink:0;color:#C6A43F}.kinas-toast.is-error i{color:#EF4444}.kinas-toast.is-success i{color:#22C55E}
@keyframes kinasToastIn{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}
@keyframes kinasToastOut{from{opacity:1;transform:translateX(0);max-height:100px}to{opacity:0;transform:translateX(60px);max-height:0}}
@media(max-width:480px){#kinasConfirmBox{width:94vw}#kinasConfirmActions{flex-direction:column-reverse}#kinasToastContainer{left:14px;right:14px;bottom:20px}.kinas-toast{min-width:unset;max-width:unset}}
@media (prefers-color-scheme: dark) {
body { background: #F5F7FA !important; }
.agent-header h1 { color: #0A0A0A !important; } .agent-header h1 i { color: #C6A43F !important; }
.btn-secondary { background: #F5F5F5 !important; color: #666 !important; }
.listing-form { background: white !important; }
.form-section h3 { color: #C6A43F !important; }
.form-group label { color: #333 !important; } .form-group label i { color: #C6A43F !important; }
.form-group input, .form-group select, .form-group textarea { background: #fff !important; }
.prefix { color: #C6A43F !important; }
.preview-item { background: #F5F5F5 !important; }
.preview-item.existing { border-color: #2E7D32 !important; }
.btn-cancel { background: #F5F5F5 !important; color: #666 !important; }
.btn-submit { background: #C6A43F !important; color: #0A0A0A !important; }
}
</style>
<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:15px;">
<div class="agent-container">
<div class="agent-header"><div><h1><i class="fas fa-edit"></i> Edit Listing</h1><p>Update your listing details</p></div><a href="/agent/listings.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Listings</a></div>
<?php if (!in_array($kycStatus, ['approved'], true)): ?>
<div style="background:linear-gradient(135deg,#FFF8E1,#FFF3E0); border:1px solid #FFE0B2; border-radius:16px; padding:18px 24px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
<i class="fas fa-shield-alt" style="color:#E65100; font-size:24px;"></i>
<div style="flex:1;"><strong style="color:#BF360C;">Verification recommended.</strong>
<span style="color:#5D4037; font-size:13px;"> Listings from verified agents rank higher and get the verified badge. <a href="/agent/verification.php" style="color:#BF360C; font-weight:600;">Complete KYC →</a></span></div>
</div>
<?php endif; ?>
<?php if ($flashSuccess): ?><div style="background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div style="background:#FFEBEE; border:1px solid #EF9A9A; color:#B71C1C; border-radius:8px; padding:14px 20px; margin-bottom:24px;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<form class="listing-form" method="POST" action="/api/listings/update.php" enctype="multipart/form-data" id="editForm">
<input type="hidden" name="csrf_token" id="csrfTokenInput" value="<?php echo htmlspecialchars($csrf_token); ?>">
<input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
<input type="hidden" name="division" value="<?php echo $divisionParam; ?>">
<input type="hidden" name="listing_type" id="listing_type" value="<?php echo $divisionParam; ?>">
<input type="hidden" name="redirect" value="/agent/listings.php">
<div class="form-grid">
<div class="form-section">
<h3>Basic Information</h3>
<div class="form-group"><label><i class="fas fa-layer-group"></i> Division</label>
<?php $divisionLabel=''; foreach ($divisionMap as $dbDiv=>$info){ if($info['type']===$divisionParam){$divisionLabel=$info['label'];break;} } ?>
<input type="text" value="<?php echo htmlspecialchars($divisionLabel ?: $divisionParam); ?>" disabled style="background:#f5f5f5;">
<small style="color:#666; font-size:12px;">Division cannot be changed after creation.</small></div>
<div class="form-group"><label><i class="fas fa-heading"></i> Listing Title *</label>
<input type="text" name="title" value="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>" placeholder="e.g., 2024 Mercedes-Benz S-Class" required></div>
<div class="form-group"><label><i class="fas fa-align-left"></i> Description *</label>
<textarea name="description" rows="6" placeholder="Provide a detailed description of your listing..." required><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea></div>
<div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Price *</label>
<div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" value="<?php echo htmlspecialchars($listing['price'] ?? 0); ?>" placeholder="0" step="0.01" required></div></div>
<div class="form-group"><label>Price Type</label>
<select name="price_type">
<option value="fixed" <?php echo ($listing['price_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
<option value="negotiable" <?php echo ($listing['price_type'] ?? '') === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
<option value="contact" <?php echo ($listing['price_type'] ?? '') === 'contact' ? 'selected' : ''; ?>>Contact for Price</option>
</select></div></div>
<h3>Location</h3>
<div class="form-row"><div class="form-group"><label><i class="fas fa-map-marker-alt"></i> City</label>
<input type="text" name="city" value="<?php echo htmlspecialchars($listing['city'] ?? ''); ?>" placeholder="e.g., Bellevue"></div>
<div class="form-group"><label>State/Province</label>
<input type="text" name="state" value="<?php echo htmlspecialchars($listing['state'] ?? ''); ?>" placeholder="e.g., WA"></div></div>
<div class="form-group"><label><i class="fas fa-location-dot"></i> Address</label>
<input type="text" name="address" value="<?php echo htmlspecialchars($listing['address'] ?? ''); ?>" placeholder="Full address, e.g., 1880 136th Place NE"></div>
<div class="form-group"><label><i class="fas fa-globe"></i> Country</label>
<input type="text" name="country" value="<?php echo htmlspecialchars($listing['country'] ?? 'Nigeria'); ?>" placeholder="e.g., United States"></div>
<!-- AUTOMOBILE -->
<div id="automobileFields" style="display:<?php echo $divisionParam === 'car' ? 'block' : 'none'; ?>; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-car"></i> Automobile Details</h3>
<div class="automobile-fields-grid">
<div class="form-group"><label><i class="fas fa-key"></i> Listing Purpose *</label>
<select name="car_listing_type" id="carListingType" required>
<option value="sale" <?php echo ($listing['listing_type'] ?? 'sale') === 'sale' ? 'selected' : ''; ?>>For Sale</option>
<?php if ($canOfferRental || ($listing['listing_type'] ?? '') === 'rental'): ?>
<option value="rental" <?php echo ($listing['listing_type'] ?? '') === 'rental' ? 'selected' : ''; ?>>For Rental</option>
<?php endif; ?>
</select>
<?php if (!$canOfferRental): ?><span style="display:block;font-size:12px;color:#888;margin-top:4px;">Rental listings are only available to verified registered businesses.</span><?php endif; ?></div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Inspection Fee (₦) <span style="font-weight:400;color:#888;">(optional)</span></label>
<input type="number" name="inspection_fee" min="0" step="0.01" value="<?php echo htmlspecialchars($listing['inspection_fee'] ?? ''); ?>" placeholder="Leave blank for free inspections"></div>
<div class="form-group"><label><i class="fas fa-tag"></i> Make *</label>
<select name="brand" required>
<option value="">Select Make</option>
<?php foreach (['Acura','Alfa Romeo','Aston Martin','Audi','Bentley','BMW','Bugatti','Buick','Cadillac','Chevrolet','Chrysler','Citroen','Dodge','Ferrari','Fiat','Ford','Genesis','GMC','Honda','Hyundai','Infiniti','Jaguar','Jeep','Kia','Lamborghini','Land Rover','Lexus','Maserati','Mazda','McLaren','Mercedes-Benz','Mini','Mitsubishi','Nissan','Porsche','Ram','Rolls-Royce','Subaru','Tesla','Toyota','Volkswagen','Volvo','Other'] as $mk): ?>
<option value="<?php echo $mk; ?>" <?php echo ($listing['brand'] ?? '') === $mk ? 'selected' : ''; ?>><?php echo $mk; ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label><i class="fas fa-car"></i> Model *</label>
<input type="text" name="model" value="<?php echo htmlspecialchars($listing['model'] ?? ''); ?>" placeholder="e.g., S-Class" required></div>
<div class="form-group"><label><i class="fas fa-calendar"></i> Year *</label>
<input type="number" name="year" value="<?php echo htmlspecialchars($listing['year'] ?? ''); ?>" placeholder="e.g., 2018" min="1900" max="2099" required></div>
<div class="form-group"><label><i class="fas fa-tachometer-alt"></i> Mileage *</label>
<input type="text" name="mileage" value="<?php echo htmlspecialchars($listing['mileage'] ?? ''); ?>" placeholder="e.g., 19592 mi (31530 km)" required></div>
<div class="form-group"><label><i class="fas fa-cog"></i> Engine</label>
<input type="text" name="engine" value="<?php echo htmlspecialchars($listing['engine'] ?? ''); ?>" placeholder="e.g., 6 Cylinder"></div>
<div class="form-group"><label><i class="fas fa-cogs"></i> Gearbox / Transmission</label>
<select name="gearbox">
<option value="">Select Gearbox</option>
<option value="Automatic" <?php echo ($listing['gearbox'] ?? '') === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
<option value="Manual" <?php echo ($listing['gearbox'] ?? '') === 'Manual' ? 'selected' : ''; ?>>Manual</option>
<option value="Semi-Automatic" <?php echo ($listing['gearbox'] ?? '') === 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
<option value="CVT" <?php echo ($listing['gearbox'] ?? '') === 'CVT' ? 'selected' : ''; ?>>CVT</option>
</select></div>
<div class="form-group"><label><i class="fas fa-car-side"></i> Car Type</label>
<select name="car_type">
<option value="">Select Car Type</option>
<?php foreach (['Coupe','Sedan','SUV','Convertible','Hatchback','Wagon','Truck','Van'] as $ct): ?>
<option value="<?php echo $ct; ?>" <?php echo ($listing['car_type'] ?? '') === $ct ? 'selected' : ''; ?>><?php echo $ct; ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label><i class="fas fa-steering-wheel"></i> Drive</label>
<select name="drive">
<option value="">Select Drive</option>
<option value="LHD" <?php echo ($listing['drive'] ?? '') === 'LHD' ? 'selected' : ''; ?>>LHD (Left-Hand Drive)</option>
<option value="RHD" <?php echo ($listing['drive'] ?? '') === 'RHD' ? 'selected' : ''; ?>>RHD (Right-Hand Drive)</option>
</select></div>
<div class="form-group"><label><i class="fas fa-road"></i> Drive Train</label>
<select name="drive_train">
<option value="">Select Drive Train</option>
<option value="AWD" <?php echo ($listing['drive_train'] ?? '') === 'AWD' ? 'selected' : ''; ?>>AWD (All-Wheel Drive)</option>
<option value="FWD" <?php echo ($listing['drive_train'] ?? '') === 'FWD' ? 'selected' : ''; ?>>FWD (Front-Wheel Drive)</option>
<option value="RWD" <?php echo ($listing['drive_train'] ?? '') === 'RWD' ? 'selected' : ''; ?>>RWD (Rear-Wheel Drive)</option>
<option value="4WD" <?php echo ($listing['drive_train'] ?? '') === '4WD' ? 'selected' : ''; ?>>4WD (Four-Wheel Drive)</option>
</select></div>
<div class="form-group"><label><i class="fas fa-gas-pump"></i> Fuel Type</label>
<select name="fuel_type">
<option value="">Select Fuel Type</option>
<?php foreach (['Petrol','Diesel','Electric','Hybrid','Plugin-Hybrid'] as $ft): ?>
<option value="<?php echo $ft; ?>" <?php echo ($listing['fuel_type'] ?? '') === $ft ? 'selected' : ''; ?>><?php echo $ft; ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Condition</label>
<select name="condition">
<option value="">Select Condition</option>
<?php foreach (['Brand New','Like New','Excellent','Very Good','Good','Fair'] as $cd): ?>
<option value="<?php echo $cd; ?>" <?php echo ($listing['condition_status'] ?? '') === $cd ? 'selected' : ''; ?>><?php echo $cd; ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label><i class="fas fa-barcode"></i> VIN</label>
<input type="text" name="vin" value="<?php echo htmlspecialchars($listing['vin'] ?? ''); ?>" placeholder="e.g., 19UNC1B01JY000027"></div>
<div class="form-group"><label><i class="fas fa-palette"></i> Color</label>
<input type="text" name="color" value="<?php echo htmlspecialchars($listing['color'] ?? ''); ?>" placeholder="e.g., Silver"></div>
<div class="form-group"><label><i class="fas fa-palette"></i> Interior Color</label>
<input type="text" name="interior_color" value="<?php echo htmlspecialchars($listing['interior_color'] ?? ''); ?>" placeholder="e.g., Grey"></div>
<div class="form-group"><label><i class="fas fa-door-open"></i> Doors</label>
<select name="doors">
<option value="">Select Doors</option>
<option value="2" <?php echo ($listing['doors'] ?? '') == 2 ? 'selected' : ''; ?>>2</option>
<option value="3" <?php echo ($listing['doors'] ?? '') == 3 ? 'selected' : ''; ?>>3</option>
<option value="4" <?php echo ($listing['doors'] ?? '') == 4 ? 'selected' : ''; ?>>4</option>
<option value="5" <?php echo ($listing['doors'] ?? '') == 5 ? 'selected' : ''; ?>>5</option>
</select></div>
<div class="form-group"><label><i class="fas fa-users"></i> Seats</label>
<select name="seats">
<option value="">Select Seats</option>
<option value="2" <?php echo ($listing['seats'] ?? '') == 2 ? 'selected' : ''; ?>>2</option>
<option value="4" <?php echo ($listing['seats'] ?? '') == 4 ? 'selected' : ''; ?>>4</option>
<option value="5" <?php echo ($listing['seats'] ?? '') == 5 ? 'selected' : ''; ?>>5</option>
<option value="7" <?php echo ($listing['seats'] ?? '') == 7 ? 'selected' : ''; ?>>7</option>
<option value="8" <?php echo ($listing['seats'] ?? '') == 8 ? 'selected' : ''; ?>>8</option>
</select></div>
<div class="form-group full-width"><label><i class="fas fa-check-circle"></i> Features (comma separated)</label>
<?php
$featuresDisplay = '';
if (!empty($listing['features'])) {
if (is_array($listing['features'])) { $featuresDisplay = implode(', ', $listing['features']); }
else { $decoded = json_decode($listing['features'], true); $featuresDisplay = is_array($decoded) ? implode(', ', $decoded) : (is_string($decoded)&&$decoded!==''?$decoded:$listing['features']); }
}
?>
<input type="text" name="features" value="<?php echo htmlspecialchars($featuresDisplay); ?>" placeholder="e.g., Leather seats, Sunroof, Navigation, Backup camera"></div>
</div>
</div>
<!-- PROPERTY -->
<div id="realestateFields" style="display:<?php echo $divisionParam === 'property' ? 'block' : 'none'; ?>; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-home"></i> Property Details</h3>
<div class="automobile-fields-grid">
<div class="form-group"><label>Bedrooms</label><input type="number" name="beds" value="<?php echo htmlspecialchars($listing['beds'] ?? ''); ?>" placeholder="e.g., 3"></div>
<div class="form-group"><label>Bathrooms</label><input type="number" name="baths" value="<?php echo htmlspecialchars($listing['baths'] ?? ''); ?>" placeholder="e.g., 2"></div>
<div class="form-group"><label>Area (sq ft)</label><input type="text" name="sqft" value="<?php echo htmlspecialchars($listing['sqft'] ?? ''); ?>" placeholder="e.g., 2500"></div>
<div class="form-group"><label>Property Type</label>
<select name="property_type">
<option value="">Select Type</option>
<?php foreach (['Villa','Apartment','Land','House','Condo','Townhouse'] as $pt): ?>
<option value="<?php echo $pt; ?>" <?php echo ($listing['property_type'] ?? '') === $pt ? 'selected' : ''; ?>><?php echo $pt; ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label><i class="fas fa-clipboard-check"></i> Inspection Fee (₦) <span style="font-weight:400;color:#888;">(optional)</span></label>
<input type="number" name="inspection_fee" min="0" step="0.01" value="<?php echo htmlspecialchars($listing['inspection_fee'] ?? ''); ?>" placeholder="Leave blank for free inspections"></div>
</div>
<div style="margin-top:20px;">
<label style="display:block;font-weight:600;margin-bottom:8px;"><i class="fas fa-street-view"></i> Virtual Tour <span style="font-weight:400;color:#888;">(optional)</span></label>
<?php $vtType = $listing['virtual_tour_type'] ?? 'link'; $vtUrl = $listing['virtual_tour_url'] ?? ''; $vtThumbnail = $listing['virtual_tour_thumbnail'] ?? ''; ?>
<div style="display:flex;gap:20px;margin-bottom:12px;">
<label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
<input type="radio" name="virtual_tour_type" value="link" id="vtTypeLink" <?php echo $vtType !== 'video' ? 'checked' : ''; ?>> Paste a link (YouTube, Vimeo, Matterport)</label>
<label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;">
<input type="radio" name="virtual_tour_type" value="video" id="vtTypeVideo" <?php echo $vtType === 'video' ? 'checked' : ''; ?>> Upload a video file</label>
</div>
<div id="vtLinkField" class="form-group" style="display:<?php echo $vtType !== 'video' ? '' : 'none'; ?>;">
<input type="url" name="virtual_tour_url" id="vtUrlInput" placeholder="https://youtube.com/watch?v=..." value="<?php echo $vtType !== 'video' ? htmlspecialchars($vtUrl) : ''; ?>"></div>
<div id="vtVideoField" class="form-group" style="display:<?php echo $vtType === 'video' ? '' : 'none'; ?>;">
<?php if ($vtType === 'video' && $vtUrl): ?>
<div style="display:flex;align-items:center;gap:12px;background:#f9f9f9;border:1px solid #eee;border-radius:8px;padding:10px;margin-bottom:10px;">
<?php if ($vtThumbnail): ?><img src="<?php echo htmlspecialchars($vtThumbnail); ?>" alt="Video preview" style="width:96px;height:64px;object-fit:cover;border-radius:6px;background:#000;flex-shrink:0;"><?php else: ?><div style="width:96px;height:64px;border-radius:6px;background:#151515;color:#888;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-video"></i></div><?php endif; ?>
<div style="flex:1;min-width:0;"><p style="margin:0 0 4px;font-size:13px;font-weight:600;">Current video attached</p><a href="<?php echo htmlspecialchars($vtUrl); ?>" target="_blank" rel="noopener" style="font-size:13px;">View full video</a></div>
<button type="submit" name="remove_virtual_tour" value="1" class="btn-cancel" style="padding:6px 14px;font-size:13px;flex-shrink:0;" onclick="return confirm('Remove the uploaded virtual tour video? This can\'t be undone.');"><i class="fas fa-trash"></i> Remove</button>
</div>
<?php endif; ?>
<input type="file" name="virtual_tour_video" id="vtVideoInput" accept="video/mp4,video/quicktime,video/webm">
<span style="display:block;font-size:12px;color:#888;margin-top:4px;">MP4, MOV, or WebM — up to 150MB. <?php echo $vtType === 'video' && $vtUrl ? 'Leave blank to keep the current video.' : ''; ?></span>
</div>
</div>
</div>
<!-- SOLAR (HARDWARE PARTITIONING) -->
<div id="solarFields" style="display:<?php echo $divisionParam === 'solar' ? 'block' : 'none'; ?>; margin-top:24px;">
<h3 style="margin-bottom:16px;"><i class="fas fa-sun"></i> Solar Details</h3>
<div class="form-group"><label><i class="fas fa-microchip"></i> Hardware Type *</label>
<select name="hardware_type" id="hardwareType">
<option value="solar_panel" <?php echo ($listing['hardware_type'] ?? 'solar_panel') === 'solar_panel' ? 'selected' : ''; ?>>Solar Panel</option>
<option value="inverter" <?php echo ($listing['hardware_type'] ?? '') === 'inverter' ? 'selected' : ''; ?>>Inverter</option>
<option value="battery" <?php echo ($listing['hardware_type'] ?? '') === 'battery' ? 'selected' : ''; ?>>Battery</option>
<option value="power_station" <?php echo ($listing['hardware_type'] ?? '') === 'power_station' ? 'selected' : ''; ?>>Power Station</option>
</select></div>
<div class="automobile-fields-grid">
<div class="form-group" id="panelWattsGroup"><label>Panel Capacity (W)</label>
<input type="number" name="panel_watts" step="1" value="<?php echo htmlspecialchars($listing['panel_watts'] ?? ''); ?>" placeholder="e.g., 550"></div>
<div class="form-group" id="inverterKvaGroup"><label>Inverter Capacity (kW/kVA)</label>
<input type="number" name="inverter_kva" step="0.1" value="<?php echo htmlspecialchars($listing['inverter_kva'] ?? ''); ?>" placeholder="e.g., 5"></div>
<div class="form-group" id="batteryKwhGroup"><label>Battery Capacity (kWh)</label>
<input type="number" name="battery_kwh" step="0.1" value="<?php echo htmlspecialchars($listing['battery_kwh'] ?? ''); ?>" placeholder="e.g., 10"></div>
<div class="form-group"><label>System Type</label>
<select name="solar_type">
<option value="">Select Type</option>
<option value="Residential" <?php echo ($listing['service_type'] ?? '') === 'Residential' ? 'selected' : ''; ?>>Residential</option>
<option value="Commercial" <?php echo ($listing['service_type'] ?? '') === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
<option value="Industrial" <?php echo ($listing['service_type'] ?? '') === 'Industrial' ? 'selected' : ''; ?>>Industrial</option>
</select></div>
</div>
</div>
<div class="checkbox-group"><label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?php echo (!empty($listing['featured'])) ? 'checked' : ''; ?>><span>Feature this listing for premium visibility</span></label></div>
<div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit" id="updateBtn">Update Listing</button></div>
</div>
<div class="form-section">
<h3>Images</h3>
<div class="image-upload-area">
<input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;">
<div class="upload-placeholder" onclick="if (document.getElementById('imageUpload').dataset.kinasProcessing === '1') return; document.getElementById('imageUpload').click()">
<i class="fas fa-cloud-upload-alt"></i><p>Click or drag images here</p><span>Upload up to 10 images (Max 5MB each)</span></div>
<div class="image-preview-grid" id="imagePreviewGrid"></div>
</div>
</div>
</div>
</form>
</div>
<div id="kinasConfirmOverlay" role="dialog" aria-modal="true" aria-labelledby="kinasConfirmTitle">
<div id="kinasConfirmBox">
<div id="kinasConfirmHead">
<div id="kinasConfirmIconWrap"><i class="fas fa-trash-alt"></i></div>
<div id="kinasConfirmTitleWrap"><h3 id="kinasConfirmTitle">Confirm Action</h3><p id="kinasConfirmSubtitle">This action cannot be undone.</p></div>
</div>
<p id="kinasConfirmMsg"></p>
<div id="kinasConfirmWarningBadge"><i class="fas fa-exclamation-triangle"></i><span id="kinasConfirmWarningText"></span></div>
<div id="kinasConfirmActions">
<button id="kinasConfirmCancel" type="button">Cancel</button>
<button id="kinasConfirmProceed" type="button"><i class="fas fa-trash-alt"></i><span>Delete</span></button>
</div>
</div>
</div>
<div id="kinasToastContainer" aria-live="polite"></div>
<script>
window.kinasConfirm = function(message, onConfirm, opts) {
opts = opts || {};
var variant=opts.variant||'danger', title=opts.title||'Confirm Action', subtitle=opts.subtitle||'This action cannot be undone.', label=opts.confirm||'Delete', icon=opts.icon||'fa-trash-alt';
var overlay=document.getElementById('kinasConfirmOverlay'), iconWrap=document.getElementById('kinasConfirmIconWrap'), badge=document.getElementById('kinasConfirmWarningBadge'), badgeTxt=document.getElementById('kinasConfirmWarningText'), proceedBtn=document.getElementById('kinasConfirmProceed'), proceedIcon=proceedBtn.querySelector('i'), proceedLbl=proceedBtn.querySelector('span');
document.getElementById('kinasConfirmTitle').textContent=title;
document.getElementById('kinasConfirmSubtitle').textContent=subtitle;
document.getElementById('kinasConfirmMsg').textContent=message;
iconWrap.className='is-'+variant; iconWrap.innerHTML='<i class="fas '+icon+'"></i>';
proceedBtn.className='is-'+variant; proceedIcon.className='fas '+icon; proceedLbl.textContent=label;
if(opts.warning){badgeTxt.textContent=opts.warning;badge.classList.add('visible');}else{badge.classList.remove('visible');}
overlay.classList.add('active');
document.getElementById('kinasConfirmCancel').focus();
function close(){overlay.classList.remove('active');overlay.removeEventListener('click',outsideClick);document.removeEventListener('keydown',escKey);}
function outsideClick(e){if(e.target===overlay)close();}
function escKey(e){if(e.key==='Escape')close();}
overlay.addEventListener('click',outsideClick); document.addEventListener('keydown',escKey);
document.getElementById('kinasConfirmCancel').onclick=close;
proceedBtn.onclick=function(){close(); if(onConfirm)onConfirm();};
};
window.kinasToast = function(message, type, duration) {
type=type||'error'; duration=duration||5000;
var container=document.getElementById('kinasToastContainer');
var iconMap={error:'fa-exclamation-circle',success:'fa-check-circle',info:'fa-info-circle',warning:'fa-exclamation-triangle'};
var toast=document.createElement('div'); toast.className='kinas-toast is-'+type;
toast.innerHTML='<i class="fas '+(iconMap[type]||iconMap.error)+'"></i><span>'+message+'</span>';
container.appendChild(toast);
var timer=setTimeout(function(){toast.style.animation='kinasToastOut .35s ease forwards';setTimeout(function(){toast.remove();},340);},duration);
toast.addEventListener('click',function(){clearTimeout(timer);toast.style.animation='kinasToastOut .35s ease forwards';setTimeout(function(){toast.remove();},340);});
};
</script>
<script>
let currentCsrfToken = document.getElementById('csrfTokenInput')?.value || '<?php echo $csrf_token; ?>';
function refreshCsrfToken(){ const t=document.getElementById('csrfTokenInput'); if(t)t.value=currentCsrfToken; document.querySelectorAll('input[name="csrf_token"]').forEach(i=>i.value=currentCsrfToken); }
// SOLAR HARDWARE PARTITIONING: show the correct capacity inputs per type.
function syncSolarHardware(){
const t=document.getElementById('hardwareType')?.value||'solar_panel';
const show=(id,on)=>{const elx=document.getElementById(id); if(elx)elx.style.display=on?'':'none';};
show('panelWattsGroup', t==='solar_panel');
show('inverterKvaGroup', t==='inverter'||t==='power_station');
show('batteryKwhGroup', t==='battery'||t==='power_station');
}
document.addEventListener('DOMContentLoaded', function() {
syncSolarHardware();
document.getElementById('hardwareType')?.addEventListener('change', syncSolarHardware);
const form=document.getElementById('editForm'); const submitBtn=document.getElementById('updateBtn');
if(form&&submitBtn){
submitBtn.addEventListener('click',function(e){
e.preventDefault();
const title=form.querySelector('input[name="title"]')?.value?.trim()||'';
const price=form.querySelector('input[name="price"]')?.value?.trim()||'';
const description=form.querySelector('textarea[name="description"]')?.value?.trim()||'';
if(!title){showSuccessBanner('Please enter a listing title.',true);return;}
if(!price||parseFloat(price)<=0){showSuccessBanner('Please enter a valid price greater than zero.',true);return;}
if(!description){showSuccessBanner('Please enter a description.',true);return;}
submitBtn.disabled=true; submitBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Updating...';
form.submit();
});
}
});
const existingImages = <?php echo json_encode($existingImages); ?>;
const imagePreviewGrid=document.getElementById('imagePreviewGrid');
const imageUpload=document.getElementById('imageUpload');
let selectedFiles=[]; const filePreviewUrls=new Map();
function getPreviewUrl(f){let u=filePreviewUrls.get(f); if(!u){u=URL.createObjectURL(f);filePreviewUrls.set(f,u);} return u;}
window.__kinasPreviewImgError=function(imgEl,index){const f=selectedFiles[index]; if(!f)return; const old=filePreviewUrls.get(f); if(old){URL.revokeObjectURL(old);filePreviewUrls.delete(f);} const fresh=getPreviewUrl(f); if(imgEl.dataset.kinasRetried==='1')return; imgEl.dataset.kinasRetried='1'; imgEl.onerror=function(){window.__kinasPreviewImgError(imgEl,index);}; imgEl.src=fresh;};
function renderImages(){ if(!imagePreviewGrid)return; imagePreviewGrid.innerHTML='';
existingImages.forEach((img,index)=>{const div=document.createElement('div');div.className='preview-item existing';div.innerHTML=`<img src="${img.url}" alt="Existing image ${index+1}"><span class="preview-badge">Saved</span><button type="button" class="preview-remove" onclick="removeExistingImage(${img.id}, this)" title="Remove this image">&times;</button>`;imagePreviewGrid.appendChild(div);});
const cur=new Set(selectedFiles); for(const [f,u] of filePreviewUrls){ if(!cur.has(f)){URL.revokeObjectURL(u);filePreviewUrls.delete(f);} }
selectedFiles.forEach((f,index)=>{const div=document.createElement('div');div.className='preview-item';div.innerHTML=`<img src="${getPreviewUrl(f)}" alt="New image ${index+1}" onerror="window.__kinasPreviewImgError && window.__kinasPreviewImgError(this, ${index})"><button type="button" class="preview-remove" onclick="removeNewImage(${index})" title="Remove this image">&times;</button>`;imagePreviewGrid.appendChild(div);});
}
function removeExistingImage(imageId,button){ kinasConfirm('Remove this image from the listing?',function(){ button.classList.add('loading'); button.innerHTML='⏳'; const csrf=document.getElementById('csrfTokenInput')?.value||currentCsrfToken;
fetch('/api/listings/delete-image.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({image_id:imageId,csrf_token:csrf})})
.then(r=>{if(!r.ok)return r.json().then(d=>{throw new Error(d.error||'Server error: '+r.status);});return r.json();})
.then(d=>{ if(d.success){ const i=existingImages.findIndex(x=>x.id===imageId); if(i!==-1){existingImages.splice(i,1);renderImages();} fetch('/api/auth/csrf-token.php').then(r=>r.json()).then(t=>{if(t.csrf_token){currentCsrfToken=t.csrf_token;document.getElementById('csrfTokenInput').value=currentCsrfToken;document.querySelectorAll('input[name="csrf_token"]').forEach(i=>i.value=currentCsrfToken);}}).catch(()=>{}); } else { kinasToast(d.error||'Failed to remove image','error'); button.classList.remove('loading'); button.innerHTML='&times;'; if(d.error&&d.error.toLowerCase().includes('security token'))setTimeout(()=>window.location.reload(),1000);} })
.catch(e=>{kinasToast('Error: '+e.message,'error');button.classList.remove('loading');button.innerHTML='&times;';});
},{title:'Remove Image',confirm:'Remove',variant:'warning',icon:'fa-image'}); }
function removeNewImage(index){ selectedFiles.splice(index,1); syncInputFiles(); renderImages(); }
function syncInputFiles(){ if(!imageUpload)return; const dt=new DataTransfer(); selectedFiles.forEach(f=>dt.items.add(f)); imageUpload.files=dt.files; }
if(imageUpload){ imageUpload.addEventListener('kinas:images-ready',function(e){ const nf=(e.detail&&Array.isArray(e.detail.files))?e.detail.files:Array.from(imageUpload.files||[]); selectedFiles=[...selectedFiles,...nf]; syncInputFiles(); renderImages(); }); }
document.addEventListener('DOMContentLoaded',function(){renderImages();refreshCsrfToken();});
document.addEventListener('DOMContentLoaded',function(){setTimeout(function(){if(imagePreviewGrid&&imagePreviewGrid.children.length===0)renderImages();},100);});
function showToast(m,t){var map={success:'success',error:'error',info:'info'};kinasToast(m,map[t]||'info');}
</script>
</main>
</div>
<?php $__imgUploadJsV = @filemtime(__DIR__ . '/../assets/js/image-upload.js') ?: time(); ?>
<script src="/assets/js/image-upload.js?v=<?= $__imgUploadJsV ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
const vtTypeLink=document.getElementById('vtTypeLink'); const vtTypeVideo=document.getElementById('vtTypeVideo');
const vtLinkField=document.getElementById('vtLinkField'); const vtVideoField=document.getElementById('vtVideoField');
if(!vtTypeLink||!vtTypeVideo)return;
function sync(){const isLink=vtTypeLink.checked; vtLinkField.style.display=isLink?'':'none'; vtVideoField.style.display=isLink?'none':'';}
vtTypeLink.addEventListener('change',sync); vtTypeVideo.addEventListener('change',sync);
});
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
