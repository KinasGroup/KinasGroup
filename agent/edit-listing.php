<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Check if user is an agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = (int)$_SESSION['user_id'];

// Get listing ID and division
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$divisionParam = isset($_GET['division']) ? $_GET['division'] : '';

if (!$listingId || !$divisionParam) {
    header('Location: listings.php?error=Invalid listing');
    exit;
}

// Map division param to table
$tableMap = [
    'solar' => 'solar_listings',
    'car' => 'car_listings',
    'property' => 'property_listings',
    'marketplace' => 'marketplace_listings'
];

if (!isset($tableMap[$divisionParam])) {
    header('Location: listings.php?error=Invalid division');
    exit;
}

$table = $tableMap[$divisionParam];

// Get the listing
$stmt = $db->prepare("SELECT * FROM $table WHERE id = ? AND agent_id = ?");
$stmt->execute([$listingId, $agentId]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: listings.php?error=Listing not found');
    exit;
}

// Get existing images for this listing
$imageStmt = $db->prepare("SELECT id, url FROM listing_images WHERE listing_id = ? AND listing_type = ? ORDER BY sort_order");
$imageStmt->execute([$listingId, $divisionParam]);
$existingImages = $imageStmt->fetchAll();

// KYC soft-guard
$kycStatus = 'pending';
try {
    $st = $db->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");
    $st->execute([$agentId]);
    $kycStatus = $st->fetchColumn() ?: 'pending';
} catch (Exception $e) {
    // Table not migrated yet — allow
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Division map
$divisionMap = [
    'kinas-automobile'    => ['type' => 'car',         'label' => 'Kinas Automobile',      'opt' => 'automobile'],
    'williams-connect-home'=> ['type' => 'property',   'label' => 'Williams Connect Home', 'opt' => 'realestate'],
    'kinas-volt'          => ['type' => 'solar',       'label' => 'Kinas Volt',            'opt' => 'solar'],
    'kinas-marketplace'   => ['type' => 'marketplace', 'label' => 'Kinas Marketplace',     'opt' => 'marketplace'],
];

// Generate CSRF token BEFORE including header
$csrf_token = Security::generateCSRFToken();

// Set page title before including header
$pageTitle = 'Edit Listing - Agent Dashboard';

require_once __DIR__ . '/../templates/header.php';
?>

<!-- ============================================================
     RESPONSIVE FIX - COMPLETE OVERHAUL FOR MOBILE
     ============================================================ -->
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

/* ============================================================
   FIX: REORDER GRID ON MOBILE - IMAGES BELOW FORM
   ============================================================ */
.form-grid { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 0; 
}
.form-section { padding: 32px; }
.form-section:first-child { border-right: 1px solid #E0E0E0; }
.form-section h3 { font-size: 18px; font-weight: 600; color: #C6A43F; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #C6A43F; display: inline-block; }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #333; }
.form-group label i { color: #C6A43F; margin-right: 6px; }
.form-group input, .form-group select, .form-group textarea { 
    width: 100%; 
    padding: 12px 16px; 
    border: 1px solid #E0E0E0; 
    border-radius: 12px; 
    font-family: 'Inter', sans-serif; 
    font-size: 14px; 
    transition: all 0.3s; 
    background: #fff; 
    box-sizing: border-box;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
    outline: none; 
    border-color: #C6A43F; 
    box-shadow: 0 0 0 3px rgba(198,164,63,0.1); 
}
.form-group select { 
    appearance: none; 
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); 
    background-repeat: no-repeat; 
    background-position: right 16px center; 
    padding-right: 40px; 
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.input-prefix { position: relative; display: flex; align-items: center; }
.prefix { position: absolute; left: 16px; color: #C6A43F; font-weight: 600; }
.input-prefix input { padding-left: 32px; }
.image-upload-area { 
    border: 2px dashed #E0E0E0; 
    border-radius: 16px; 
    padding: 20px; 
    text-align: center; 
    transition: all 0.3s; 
    width: 100%;
}
.image-upload-area:hover { border-color: #C6A43F; background: rgba(198,164,63,0.02); }
.upload-placeholder { cursor: pointer; }
.upload-placeholder i { font-size: 48px; color: #C6A43F; margin-bottom: 12px; }
.upload-placeholder p { margin-bottom: 8px; color: #666; }
.upload-placeholder span { font-size: 12px; color: #999; }
.image-preview-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); 
    gap: 12px; 
    margin-top: 20px; 
}
.preview-item { 
    position: relative; 
    border-radius: 12px; 
    overflow: hidden; 
    aspect-ratio: 1; 
    background: #F5F5F5; 
    border: 2px solid #E0E0E0; 
}
.preview-item img { width: 100%; height: 100%; object-fit: cover; }
.preview-item.existing { border-color: #2E7D32; }
.preview-item.existing .preview-badge { 
    position: absolute; 
    top: 4px; 
    left: 4px; 
    background: #2E7D32; 
    color: white; 
    font-size: 9px; 
    padding: 2px 8px; 
    border-radius: 4px; 
    font-weight: 600; 
}
.preview-remove { 
    position: absolute; 
    top: 4px; 
    right: 4px; 
    width: 24px; 
    height: 24px; 
    background: rgba(0,0,0,0.7); 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: white; 
    cursor: pointer; 
    font-size: 14px; 
    border: none; 
    transition: all 0.3s; 
}
.preview-remove:hover { background: #C62828; }
.preview-remove.loading { opacity: 0.5; pointer-events: none; }
.checkbox-group { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input { width: auto; accent-color: #C6A43F; }
.form-actions { 
    display: flex; 
    justify-content: flex-end; 
    gap: 16px; 
    margin-top: 32px; 
    padding-top: 24px; 
    border-top: 1px solid #E0E0E0; 
    flex-wrap: wrap;
}
.btn-cancel { 
    padding: 12px 28px; 
    background: #F5F5F5; 
    border: none; 
    border-radius: 40px; 
    color: #666; 
    cursor: pointer; 
    font-weight: 600; 
}
.btn-submit { 
    padding: 12px 32px; 
    background: #C6A43F; 
    border: none; 
    border-radius: 40px; 
    font-weight: 600; 
    color: #0A0A0A; 
    cursor: pointer; 
    transition: all 0.3s; 
}
.btn-submit:hover { background: #A8882E; transform: translateY(-2px); }
.automobile-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.automobile-fields-grid .form-group { margin-bottom: 0; }
.full-width { grid-column: 1 / -1; }

/* ============================================================
   RESPONSIVE BREAKPOINTS - FIXED
   ============================================================ */

/* Tablet and below */
@media (max-width: 968px) { 
    .form-grid { 
        grid-template-columns: 1fr; 
    } 
    .form-section:first-child { 
        border-right: none; 
        border-bottom: 1px solid #E0E0E0; 
    } 
    .form-row { 
        grid-template-columns: 1fr; 
        gap: 0; 
    } 
    .automobile-fields-grid { 
        grid-template-columns: 1fr; 
    } 
}

/* Mobile */
@media (max-width: 768px) { 
    .agent-container { 
        padding: 15px !important; 
    } 
    .form-section { 
        padding: 16px !important; 
    }
    .agent-header h1 { 
        font-size: 22px !important; 
    }
    .form-section h3 { 
        font-size: 16px !important; 
    }
    .form-group input, 
    .form-group select, 
    .form-group textarea { 
        font-size: 14px !important; 
        padding: 10px 14px !important; 
    }
    .btn-submit, 
    .btn-cancel { 
        width: 100% !important; 
        justify-content: center !important; 
        text-align: center !important;
    }
    .form-actions { 
        flex-direction: column !important; 
        gap: 10px !important; 
    }
    .image-preview-grid { 
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)) !important; 
        gap: 8px !important; 
    }
    .preview-item { 
        aspect-ratio: 1 !important; 
    }
}

/* Small phones */
@media (max-width: 480px) { 
    .agent-container { 
        padding: 10px !important; 
    }
    .form-section { 
        padding: 12px !important; 
    }
    .agent-header { 
        flex-direction: column !important; 
        align-items: flex-start !important; 
        gap: 10px !important; 
    }
    .btn-secondary { 
        width: 100% !important; 
        justify-content: center !important; 
    }
    .image-preview-grid { 
        grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)) !important; 
        gap: 6px !important; 
    }
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

    <form class="listing-form" method="POST" action="/api/listings/update.php" enctype="multipart/form-data" id="editForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
        <input type="hidden" name="division" value="<?php echo $divisionParam; ?>">
        <input type="hidden" name="listing_type" id="listing_type" value="<?php echo $divisionParam; ?>">
        <input type="hidden" name="redirect" value="/agent/listings.php">
        
        <div class="form-grid">
            <!-- LEFT COLUMN - Form Fields (Now first on mobile) -->
            <div class="form-section">
                <h3>Basic Information</h3>
                <div class="form-group"><label><i class="fas fa-layer-group"></i> Division</label>
                    <?php 
                    $divisionLabel = '';
                    foreach ($divisionMap as $dbDiv => $info) {
                        if ($info['type'] === $divisionParam) {
                            $divisionLabel = $info['label'];
                            break;
                        }
                    }
                    ?>
                    <input type="text" value="<?php echo htmlspecialchars($divisionLabel ?: $divisionParam); ?>" disabled style="background:#f5f5f5;">
                    <small style="color:#666; font-size:12px;">Division cannot be changed after creation.</small>
                </div>
                <div class="form-group"><label><i class="fas fa-heading"></i> Listing Title *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>" placeholder="e.g., 2024 Mercedes-Benz S-Class" required>
                </div>
                <div class="form-group"><label><i class="fas fa-align-left"></i> Description *</label>
                    <textarea name="description" rows="6" placeholder="Provide a detailed description of your listing..." required><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Price *</label>
                    <div class="input-prefix"><span class="prefix">₦</span><input type="number" name="price" value="<?php echo htmlspecialchars($listing['price'] ?? 0); ?>" placeholder="0" step="0.01" required></div>
                </div>
                <div class="form-group"><label>Price Type</label>
                    <select name="price_type">
                        <option value="fixed" <?php echo ($listing['price_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                        <option value="negotiable" <?php echo ($listing['price_type'] ?? '') === 'negotiable' ? 'selected' : ''; ?>>Negotiable</option>
                        <option value="contact" <?php echo ($listing['price_type'] ?? '') === 'contact' ? 'selected' : ''; ?>>Contact for Price</option>
                    </select>
                </div></div>
                <h3>Location</h3>
                <div class="form-row"><div class="form-group"><label><i class="fas fa-map-marker-alt"></i> City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($listing['city'] ?? ''); ?>" placeholder="e.g., Bellevue">
                </div>
                <div class="form-group"><label>State/Province</label>
                    <input type="text" name="state" value="<?php echo htmlspecialchars($listing['state'] ?? ''); ?>" placeholder="e.g., WA">
                </div></div>
                <div class="form-group"><label><i class="fas fa-location-dot"></i> Address</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($listing['address'] ?? ''); ?>" placeholder="Full address, e.g., 1880 136th Place NE">
                </div>
                <div class="form-group"><label><i class="fas fa-globe"></i> Country</label>
                    <input type="text" name="country" value="<?php echo htmlspecialchars($listing['country'] ?? 'Nigeria'); ?>" placeholder="e.g., United States">
                </div>

                <!-- AUTOMOBILE DETAILS SECTION -->
                <div id="automobileFields" style="display:<?php echo $divisionParam === 'car' ? 'block' : 'none'; ?>; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-car"></i> Automobile Details</h3>
                    <div class="automobile-fields-grid">
                        <div class="form-group"><label><i class="fas fa-tag"></i> Make *</label>
                            <select name="brand" required>
                                <option value="">Select Make</option>
                                <option value="Acura" <?php echo ($listing['brand'] ?? '') === 'Acura' ? 'selected' : ''; ?>>Acura</option>
                                <option value="Alfa Romeo" <?php echo ($listing['brand'] ?? '') === 'Alfa Romeo' ? 'selected' : ''; ?>>Alfa Romeo</option>
                                <option value="Aston Martin" <?php echo ($listing['brand'] ?? '') === 'Aston Martin' ? 'selected' : ''; ?>>Aston Martin</option>
                                <option value="Audi" <?php echo ($listing['brand'] ?? '') === 'Audi' ? 'selected' : ''; ?>>Audi</option>
                                <option value="Bentley" <?php echo ($listing['brand'] ?? '') === 'Bentley' ? 'selected' : ''; ?>>Bentley</option>
                                <option value="BMW" <?php echo ($listing['brand'] ?? '') === 'BMW' ? 'selected' : ''; ?>>BMW</option>
                                <option value="Bugatti" <?php echo ($listing['brand'] ?? '') === 'Bugatti' ? 'selected' : ''; ?>>Bugatti</option>
                                <option value="Buick" <?php echo ($listing['brand'] ?? '') === 'Buick' ? 'selected' : ''; ?>>Buick</option>
                                <option value="Cadillac" <?php echo ($listing['brand'] ?? '') === 'Cadillac' ? 'selected' : ''; ?>>Cadillac</option>
                                <option value="Chevrolet" <?php echo ($listing['brand'] ?? '') === 'Chevrolet' ? 'selected' : ''; ?>>Chevrolet</option>
                                <option value="Chrysler" <?php echo ($listing['brand'] ?? '') === 'Chrysler' ? 'selected' : ''; ?>>Chrysler</option>
                                <option value="Citroen" <?php echo ($listing['brand'] ?? '') === 'Citroen' ? 'selected' : ''; ?>>Citroen</option>
                                <option value="Dodge" <?php echo ($listing['brand'] ?? '') === 'Dodge' ? 'selected' : ''; ?>>Dodge</option>
                                <option value="Ferrari" <?php echo ($listing['brand'] ?? '') === 'Ferrari' ? 'selected' : ''; ?>>Ferrari</option>
                                <option value="Fiat" <?php echo ($listing['brand'] ?? '') === 'Fiat' ? 'selected' : ''; ?>>Fiat</option>
                                <option value="Ford" <?php echo ($listing['brand'] ?? '') === 'Ford' ? 'selected' : ''; ?>>Ford</option>
                                <option value="Genesis" <?php echo ($listing['brand'] ?? '') === 'Genesis' ? 'selected' : ''; ?>>Genesis</option>
                                <option value="GMC" <?php echo ($listing['brand'] ?? '') === 'GMC' ? 'selected' : ''; ?>>GMC</option>
                                <option value="Honda" <?php echo ($listing['brand'] ?? '') === 'Honda' ? 'selected' : ''; ?>>Honda</option>
                                <option value="Hyundai" <?php echo ($listing['brand'] ?? '') === 'Hyundai' ? 'selected' : ''; ?>>Hyundai</option>
                                <option value="Infiniti" <?php echo ($listing['brand'] ?? '') === 'Infiniti' ? 'selected' : ''; ?>>Infiniti</option>
                                <option value="Jaguar" <?php echo ($listing['brand'] ?? '') === 'Jaguar' ? 'selected' : ''; ?>>Jaguar</option>
                                <option value="Jeep" <?php echo ($listing['brand'] ?? '') === 'Jeep' ? 'selected' : ''; ?>>Jeep</option>
                                <option value="Kia" <?php echo ($listing['brand'] ?? '') === 'Kia' ? 'selected' : ''; ?>>Kia</option>
                                <option value="Lamborghini" <?php echo ($listing['brand'] ?? '') === 'Lamborghini' ? 'selected' : ''; ?>>Lamborghini</option>
                                <option value="Land Rover" <?php echo ($listing['brand'] ?? '') === 'Land Rover' ? 'selected' : ''; ?>>Land Rover</option>
                                <option value="Lexus" <?php echo ($listing['brand'] ?? '') === 'Lexus' ? 'selected' : ''; ?>>Lexus</option>
                                <option value="Maserati" <?php echo ($listing['brand'] ?? '') === 'Maserati' ? 'selected' : ''; ?>>Maserati</option>
                                <option value="Mazda" <?php echo ($listing['brand'] ?? '') === 'Mazda' ? 'selected' : ''; ?>>Mazda</option>
                                <option value="McLaren" <?php echo ($listing['brand'] ?? '') === 'McLaren' ? 'selected' : ''; ?>>McLaren</option>
                                <option value="Mercedes-Benz" <?php echo ($listing['brand'] ?? '') === 'Mercedes-Benz' ? 'selected' : ''; ?>>Mercedes-Benz</option>
                                <option value="Mini" <?php echo ($listing['brand'] ?? '') === 'Mini' ? 'selected' : ''; ?>>Mini</option>
                                <option value="Mitsubishi" <?php echo ($listing['brand'] ?? '') === 'Mitsubishi' ? 'selected' : ''; ?>>Mitsubishi</option>
                                <option value="Nissan" <?php echo ($listing['brand'] ?? '') === 'Nissan' ? 'selected' : ''; ?>>Nissan</option>
                                <option value="Porsche" <?php echo ($listing['brand'] ?? '') === 'Porsche' ? 'selected' : ''; ?>>Porsche</option>
                                <option value="Ram" <?php echo ($listing['brand'] ?? '') === 'Ram' ? 'selected' : ''; ?>>Ram</option>
                                <option value="Rolls-Royce" <?php echo ($listing['brand'] ?? '') === 'Rolls-Royce' ? 'selected' : ''; ?>>Rolls-Royce</option>
                                <option value="Subaru" <?php echo ($listing['brand'] ?? '') === 'Subaru' ? 'selected' : ''; ?>>Subaru</option>
                                <option value="Tesla" <?php echo ($listing['brand'] ?? '') === 'Tesla' ? 'selected' : ''; ?>>Tesla</option>
                                <option value="Toyota" <?php echo ($listing['brand'] ?? '') === 'Toyota' ? 'selected' : ''; ?>>Toyota</option>
                                <option value="Volkswagen" <?php echo ($listing['brand'] ?? '') === 'Volkswagen' ? 'selected' : ''; ?>>Volkswagen</option>
                                <option value="Volvo" <?php echo ($listing['brand'] ?? '') === 'Volvo' ? 'selected' : ''; ?>>Volvo</option>
                                <option value="Other" <?php echo ($listing['brand'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-car"></i> Model *</label>
                            <input type="text" name="model" value="<?php echo htmlspecialchars($listing['model'] ?? ''); ?>" placeholder="e.g., S-Class" required>
                        </div>
                        <div class="form-group"><label><i class="fas fa-calendar"></i> Year *</label>
                            <input type="number" name="year" value="<?php echo htmlspecialchars($listing['year'] ?? ''); ?>" placeholder="e.g., 2018" min="1900" max="2099" required>
                        </div>
                        <div class="form-group"><label><i class="fas fa-tachometer-alt"></i> Mileage *</label>
                            <input type="text" name="mileage" value="<?php echo htmlspecialchars($listing['mileage'] ?? ''); ?>" placeholder="e.g., 19592 mi (31530 km)" required>
                        </div>
                        <div class="form-group"><label><i class="fas fa-cog"></i> Engine</label>
                            <input type="text" name="engine" value="<?php echo htmlspecialchars($listing['engine'] ?? ''); ?>" placeholder="e.g., 6 Cylinder">
                        </div>
                        <div class="form-group"><label><i class="fas fa-cogs"></i> Gearbox / Transmission</label>
                            <select name="gearbox">
                                <option value="">Select Gearbox</option>
                                <option value="Automatic" <?php echo ($listing['gearbox'] ?? '') === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                <option value="Manual" <?php echo ($listing['gearbox'] ?? '') === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                <option value="Semi-Automatic" <?php echo ($listing['gearbox'] ?? '') === 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                <option value="CVT" <?php echo ($listing['gearbox'] ?? '') === 'CVT' ? 'selected' : ''; ?>>CVT</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-car-side"></i> Car Type</label>
                            <select name="car_type">
                                <option value="">Select Car Type</option>
                                <option value="Coupe" <?php echo ($listing['car_type'] ?? '') === 'Coupe' ? 'selected' : ''; ?>>Coupe</option>
                                <option value="Sedan" <?php echo ($listing['car_type'] ?? '') === 'Sedan' ? 'selected' : ''; ?>>Sedan</option>
                                <option value="SUV" <?php echo ($listing['car_type'] ?? '') === 'SUV' ? 'selected' : ''; ?>>SUV</option>
                                <option value="Convertible" <?php echo ($listing['car_type'] ?? '') === 'Convertible' ? 'selected' : ''; ?>>Convertible</option>
                                <option value="Hatchback" <?php echo ($listing['car_type'] ?? '') === 'Hatchback' ? 'selected' : ''; ?>>Hatchback</option>
                                <option value="Wagon" <?php echo ($listing['car_type'] ?? '') === 'Wagon' ? 'selected' : ''; ?>>Wagon</option>
                                <option value="Truck" <?php echo ($listing['car_type'] ?? '') === 'Truck' ? 'selected' : ''; ?>>Truck</option>
                                <option value="Van" <?php echo ($listing['car_type'] ?? '') === 'Van' ? 'selected' : ''; ?>>Van</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-steering-wheel"></i> Drive</label>
                            <select name="drive">
                                <option value="">Select Drive</option>
                                <option value="LHD" <?php echo ($listing['drive'] ?? '') === 'LHD' ? 'selected' : ''; ?>>LHD (Left-Hand Drive)</option>
                                <option value="RHD" <?php echo ($listing['drive'] ?? '') === 'RHD' ? 'selected' : ''; ?>>RHD (Right-Hand Drive)</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-road"></i> Drive Train</label>
                            <select name="drive_train">
                                <option value="">Select Drive Train</option>
                                <option value="AWD" <?php echo ($listing['drive_train'] ?? '') === 'AWD' ? 'selected' : ''; ?>>AWD (All-Wheel Drive)</option>
                                <option value="FWD" <?php echo ($listing['drive_train'] ?? '') === 'FWD' ? 'selected' : ''; ?>>FWD (Front-Wheel Drive)</option>
                                <option value="RWD" <?php echo ($listing['drive_train'] ?? '') === 'RWD' ? 'selected' : ''; ?>>RWD (Rear-Wheel Drive)</option>
                                <option value="4WD" <?php echo ($listing['drive_train'] ?? '') === '4WD' ? 'selected' : ''; ?>>4WD (Four-Wheel Drive)</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-gas-pump"></i> Fuel Type</label>
                            <select name="fuel_type">
                                <option value="">Select Fuel Type</option>
                                <option value="Petrol" <?php echo ($listing['fuel_type'] ?? '') === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                                <option value="Diesel" <?php echo ($listing['fuel_type'] ?? '') === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                                <option value="Electric" <?php echo ($listing['fuel_type'] ?? '') === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                <option value="Hybrid" <?php echo ($listing['fuel_type'] ?? '') === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                <option value="Plugin-Hybrid" <?php echo ($listing['fuel_type'] ?? '') === 'Plugin-Hybrid' ? 'selected' : ''; ?>>Plugin Hybrid</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-clipboard-check"></i> Condition</label>
                            <select name="condition">
                                <option value="">Select Condition</option>
                                <option value="Brand New" <?php echo ($listing['condition_status'] ?? '') === 'Brand New' ? 'selected' : ''; ?>>Brand New</option>
                                <option value="Like New" <?php echo ($listing['condition_status'] ?? '') === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                                <option value="Excellent" <?php echo ($listing['condition_status'] ?? '') === 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                                <option value="Very Good" <?php echo ($listing['condition_status'] ?? '') === 'Very Good' ? 'selected' : ''; ?>>Very Good</option>
                                <option value="Good" <?php echo ($listing['condition_status'] ?? '') === 'Good' ? 'selected' : ''; ?>>Good</option>
                                <option value="Fair" <?php echo ($listing['condition_status'] ?? '') === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-barcode"></i> VIN</label>
                            <input type="text" name="vin" value="<?php echo htmlspecialchars($listing['vin'] ?? ''); ?>" placeholder="e.g., 19UNC1B01JY000027">
                        </div>
                        <div class="form-group"><label><i class="fas fa-palette"></i> Color</label>
                            <input type="text" name="color" value="<?php echo htmlspecialchars($listing['color'] ?? ''); ?>" placeholder="e.g., Silver">
                        </div>
                        <div class="form-group"><label><i class="fas fa-palette"></i> Interior Color</label>
                            <input type="text" name="interior_color" value="<?php echo htmlspecialchars($listing['interior_color'] ?? ''); ?>" placeholder="e.g., Grey">
                        </div>
                        <div class="form-group"><label><i class="fas fa-door-open"></i> Doors</label>
                            <select name="doors">
                                <option value="">Select Doors</option>
                                <option value="2" <?php echo ($listing['doors'] ?? '') == 2 ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo ($listing['doors'] ?? '') == 3 ? 'selected' : ''; ?>>3</option>
                                <option value="4" <?php echo ($listing['doors'] ?? '') == 4 ? 'selected' : ''; ?>>4</option>
                                <option value="5" <?php echo ($listing['doors'] ?? '') == 5 ? 'selected' : ''; ?>>5</option>
                            </select>
                        </div>
                        <div class="form-group"><label><i class="fas fa-users"></i> Seats</label>
                            <select name="seats">
                                <option value="">Select Seats</option>
                                <option value="2" <?php echo ($listing['seats'] ?? '') == 2 ? 'selected' : ''; ?>>2</option>
                                <option value="4" <?php echo ($listing['seats'] ?? '') == 4 ? 'selected' : ''; ?>>4</option>
                                <option value="5" <?php echo ($listing['seats'] ?? '') == 5 ? 'selected' : ''; ?>>5</option>
                                <option value="7" <?php echo ($listing['seats'] ?? '') == 7 ? 'selected' : ''; ?>>7</option>
                                <option value="8" <?php echo ($listing['seats'] ?? '') == 8 ? 'selected' : ''; ?>>8</option>
                            </select>
                        </div>
                        <div class="form-group full-width"><label><i class="fas fa-check-circle"></i> Features (comma separated)</label>
                            <?php
                            $featuresDisplay = '';
                            if (!empty($listing['features'])) {
                                if (is_array($listing['features'])) {
                                    $featuresDisplay = implode(', ', $listing['features']);
                                } else {
                                    $decoded = json_decode($listing['features'], true);
                                    if (is_array($decoded)) {
                                        $featuresDisplay = implode(', ', $decoded);
                                    } else {
                                        $featuresDisplay = $listing['features'];
                                    }
                                }
                            }
                            ?>
                            <input type="text" name="features" value="<?php echo htmlspecialchars($featuresDisplay); ?>" placeholder="e.g., Leather seats, Sunroof, Navigation, Backup camera">
                        </div>
                    </div>
                </div>

                <!-- PROPERTY DETAILS SECTION -->
                <div id="realestateFields" style="display:<?php echo $divisionParam === 'property' ? 'block' : 'none'; ?>; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-home"></i> Property Details</h3>
                    <div class="automobile-fields-grid">
                        <div class="form-group"><label>Bedrooms</label>
                            <input type="number" name="beds" value="<?php echo htmlspecialchars($listing['beds'] ?? ''); ?>" placeholder="e.g., 3">
                        </div>
                        <div class="form-group"><label>Bathrooms</label>
                            <input type="number" name="baths" value="<?php echo htmlspecialchars($listing['baths'] ?? ''); ?>" placeholder="e.g., 2">
                        </div>
                        <div class="form-group"><label>Area (sq ft)</label>
                            <input type="text" name="sqft" value="<?php echo htmlspecialchars($listing['sqft'] ?? ''); ?>" placeholder="e.g., 2500">
                        </div>
                        <div class="form-group"><label>Property Type</label>
                            <select name="property_type">
                                <option value="">Select Type</option>
                                <option value="Villa" <?php echo ($listing['property_type'] ?? '') === 'Villa' ? 'selected' : ''; ?>>Villa</option>
                                <option value="Apartment" <?php echo ($listing['property_type'] ?? '') === 'Apartment' ? 'selected' : ''; ?>>Apartment</option>
                                <option value="Land" <?php echo ($listing['property_type'] ?? '') === 'Land' ? 'selected' : ''; ?>>Land</option>
                                <option value="House" <?php echo ($listing['property_type'] ?? '') === 'House' ? 'selected' : ''; ?>>House</option>
                                <option value="Condo" <?php echo ($listing['property_type'] ?? '') === 'Condo' ? 'selected' : ''; ?>>Condo</option>
                                <option value="Townhouse" <?php echo ($listing['property_type'] ?? '') === 'Townhouse' ? 'selected' : ''; ?>>Townhouse</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SOLAR DETAILS SECTION -->
                <div id="solarFields" style="display:<?php echo $divisionParam === 'solar' ? 'block' : 'none'; ?>; margin-top:24px;">
                    <h3 style="margin-bottom:16px;"><i class="fas fa-sun"></i> Solar Details</h3>
                    <div class="automobile-fields-grid">
                        <div class="form-group"><label>Capacity (kW)</label>
                            <input type="text" name="capacity" value="<?php echo htmlspecialchars($listing['capacity_kw'] ?? ''); ?>" placeholder="e.g., 10">
                        </div>
                        <div class="form-group"><label>System Type</label>
                            <select name="solar_type">
                                <option value="">Select Type</option>
                                <option value="Residential" <?php echo ($listing['service_type'] ?? '') === 'Residential' ? 'selected' : ''; ?>>Residential</option>
                                <option value="Commercial" <?php echo ($listing['service_type'] ?? '') === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="Industrial" <?php echo ($listing['service_type'] ?? '') === 'Industrial' ? 'selected' : ''; ?>>Industrial</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="checkbox-group"><label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?php echo (!empty($listing['featured'])) ? 'checked' : ''; ?>><span>Feature this listing for premium visibility</span></label></div>
                <div class="form-actions"><button type="button" class="btn-cancel" onclick="window.location.href='/agent/listings.php'">Cancel</button><button type="submit" class="btn-submit" id="updateBtn">Update Listing</button></div>
            </div>

            <!-- RIGHT COLUMN - Images (Now second on mobile) -->
            <div class="form-section">
                <h3>Images</h3>
                <div class="image-upload-area">
                    <input type="file" name="images[]" id="imageUpload" multiple accept="image/*" style="display: none;">
                    <div class="upload-placeholder" onclick="document.getElementById('imageUpload').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click or drag images here</p>
                        <span>Upload up to 10 images (Max 5MB each)</span>
                    </div>
                    <div class="image-preview-grid" id="imagePreviewGrid">
                        <!-- Existing images will be loaded here via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ============================================
// FIX: DIRECT FORM SUBMISSION HANDLER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editForm');
    const submitBtn = document.getElementById('updateBtn');
    
    if (form && submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Update button clicked');
            
            // Validate required fields
            const title = form.querySelector('input[name="title"]')?.value?.trim() || '';
            const price = form.querySelector('input[name="price"]')?.value?.trim() || '';
            const description = form.querySelector('textarea[name="description"]')?.value?.trim() || '';
            
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
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            // Submit the form
            form.submit();
        });
        
        // Also handle form submit event as backup
        form.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
        });
    }
});

// ============================================
// IMAGE MANAGEMENT
// ============================================

// Existing images from the database
const existingImages = <?php echo json_encode($existingImages); ?>;
const imagePreviewGrid = document.getElementById('imagePreviewGrid');
const imageUpload = document.getElementById('imageUpload');
let selectedFiles = [];

// Function to render all images (existing + new)
function renderImages() {
    if (!imagePreviewGrid) return;
    imagePreviewGrid.innerHTML = '';
    
    // Add existing images first
    existingImages.forEach((img, index) => {
        const div = document.createElement('div');
        div.className = 'preview-item existing';
        div.innerHTML = `
            <img src="${img.url}" alt="Existing image ${index + 1}">
            <span class="preview-badge">Saved</span>
            <button class="preview-remove" onclick="removeExistingImage(${img.id}, this)" title="Remove this image">&times;</button>
        `;
        imagePreviewGrid.appendChild(div);
    });
    
    // Add new images that were just uploaded
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `
                <img src="${e.target.result}" alt="New image ${index + 1}">
                <button class="preview-remove" onclick="removeNewImage(${index})" title="Remove this image">&times;</button>
            `;
            imagePreviewGrid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

// Remove an existing image from the database
function removeExistingImage(imageId, button) {
    if (!confirm('Remove this image from the listing?')) return;
    
    button.classList.add('loading');
    button.innerHTML = '⏳';
    
    fetch('/api/listings/delete-image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            image_id: imageId,
            csrf_token: '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const index = existingImages.findIndex(img => img.id === imageId);
            if (index !== -1) {
                existingImages.splice(index, 1);
                renderImages();
            }
        } else {
            alert(data.error || 'Failed to remove image');
            button.classList.remove('loading');
            button.innerHTML = '&times;';
        }
    })
    .catch(error => {
        alert('Network error. Please try again.');
        button.classList.remove('loading');
        button.innerHTML = '&times;';
    });
}

// Remove a newly uploaded image (not yet saved)
function removeNewImage(index) {
    selectedFiles.splice(index, 1);
    syncInputFiles();
    renderImages();
}

// Handle file upload
function syncInputFiles() {
    if (!imageUpload) return;
    const dt = new DataTransfer();
    selectedFiles.forEach(f => dt.items.add(f));
    imageUpload.files = dt.files;
}

if (imageUpload) {
    imageUpload.addEventListener('change', function(e) {
        const newFiles = Array.from(e.target.files);
        selectedFiles = [...selectedFiles, ...newFiles];
        syncInputFiles();
        renderImages();
    });
}

// Initial render
document.addEventListener('DOMContentLoaded', function() {
    renderImages();
});

// Also render images after any DOM changes
document.addEventListener('DOMContentLoaded', function() {
    // Re-render if the image grid is empty after a delay
    setTimeout(function() {
        if (imagePreviewGrid && imagePreviewGrid.children.length === 0) {
            renderImages();
        }
    }, 100);
});
</script>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
