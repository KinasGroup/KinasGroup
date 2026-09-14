<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
* KINAS GROUP — Add Hardware (Solar Division)
*
* FIXED:
* - Product image is now stored in the listing_images table
*   (listing_type = 'solar'), which is the ONLY place the rest of the
*   site (edit-listing.php, public pages, update.php) reads photos from.
*   Previously the URL was written to a column on solar_listings that
*   nothing reads (or dropped entirely when the column didn't exist),
*   which is why uploaded pictures never showed up.
* - Upload still goes through the global FileUpload class (R2 first,
*   local disk fallback), same as create.php / update.php.
* - Insert + image row are wrapped in a transaction.
*/
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/file-upload.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['agent', 'admin'], true)) {
    header('Location: /auth/login.php');
    exit;
}

$db      = Database::getInstance()->getConnection();
$agentId = (int)$_SESSION['user_id'];

$hardwareTypes = [
    'solar_panel'         => 'Solar Panel',
    'inverter'            => 'Inverter',
    'battery'             => 'Battery',
    'power_station'       => 'Power Station',
    'charge_controller'   => 'Charge Controller',
    'mounting_structure'  => 'Mounting Structure',
];

// Read table columns ONCE for dynamic inserts
$cols = [];
try {
    $colStmt = $db->query("SHOW COLUMNS FROM solar_listings");
    while ($c = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $c['Field'];
    }
} catch (Exception $e) {
    $cols = [];
}

$hasMaxPvCol = in_array('max_pv_input_w', $cols, true);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Please refresh and try again.';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $hwType      = $_POST['hardware_type'] ?? '';
        $brand       = trim($_POST['brand'] ?? '');
        $panelWatts  = trim($_POST['panel_watts'] ?? '');
        $inverterKva = trim($_POST['inverter_kva'] ?? '');
        $batteryKwh  = trim($_POST['battery_kwh'] ?? '');
        $maxPvInput  = trim($_POST['max_pv_input_w'] ?? '');
        $warranty    = trim($_POST['warranty_years'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $state       = trim($_POST['state'] ?? '');

        // Validation
        if ($title === '') $errors[] = 'Title is required.';
        if (!array_key_exists($hwType, $hardwareTypes)) $errors[] = 'Please choose a valid hardware type.';
        if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Please enter a valid price.';

        if ($hwType === 'solar_panel' && ($panelWatts === '' || !is_numeric($panelWatts) || $panelWatts <= 0)) {
            $errors[] = 'Solar Panel requires Panel Capacity in Watts (W).';
        }
        if ($hwType === 'inverter' && ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0)) {
            $errors[] = 'Inverter requires Capacity in kW/kVA.';
        }
        if ($hwType === 'battery' && ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0)) {
            $errors[] = 'Battery requires Capacity in kWh.';
        }
        if ($hwType === 'power_station') {
            if ($inverterKva === '' || !is_numeric($inverterKva) || $inverterKva <= 0) {
                $errors[] = 'Power Station requires Inverter Capacity in kW/kVA.';
            }
            if ($batteryKwh === '' || !is_numeric($batteryKwh) || $batteryKwh <= 0) {
                $errors[] = 'Power Station requires Battery Capacity in kWh.';
            }
            if ($hasMaxPvCol && ($maxPvInput === '' || !is_numeric($maxPvInput) || $maxPvInput <= 0)) {
                $errors[] = 'Power Station requires Max Panel Input in Watts (W).';
            }
        }

        if ($maxPvInput !== '' && (!is_numeric($maxPvInput) || $maxPvInput <= 0)) {
            $errors[] = 'Max Panel Input must be a positive number of Watts.';
        }

        // ------------------------------------------------------------
        // Handle image upload (FileUpload = R2 first, local fallback)
        // ------------------------------------------------------------
        $imageUrl = null;
        if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploader = new FileUpload('products');
                $fileArr = [
                    'name'     => $_FILES['product_image']['name'],
                    'type'     => $_FILES['product_image']['type'],
                    'tmp_name' => $_FILES['product_image']['tmp_name'],
                    'error'    => $_FILES['product_image']['error'],
                    'size'     => $_FILES['product_image']['size'],
                ];
                $result = $uploader->upload($fileArr, [
                    'prefix'    => "hardware_{$agentId}_",
                    'maxWidth'  => 1920,
                    'maxHeight' => 1080,
                    'quality'   => 85,
                ]);

                if ($result['success']) {
                    $imageUrl = isset($result['key'])
                        ? $result['filepath']
                        : '/uploads/products/' . $result['filename'];
                } else {
                    $errors[] = 'Image upload failed: ' . ($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                error_log('Hardware image upload error: ' . $e->getMessage());
                $errors[] = 'Could not process image upload.';
            }
        }

        // ------------------------------------------------------------
        // Insert listing + image row (transaction)
        // ------------------------------------------------------------
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $fields = ['agent_id', 'title', 'service_type', 'brand', 'price', 'warranty_years', 'description', 'city', 'state', 'status'];
                $values = [
                    $agentId,
                    $title,
                    $hwType,
                    $brand !== '' ? $brand : null,
                    $price,
                    $warranty !== '' ? $warranty : null,
                    $description !== '' ? $description : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                    'active',
                ];

                if (in_array('created_at', $cols, true)) {
                    $fields[] = 'created_at';
                    $values[] = date('Y-m-d H:i:s');
                }
                if (in_array('updated_at', $cols, true)) {
                    $fields[] = 'updated_at';
                    $values[] = date('Y-m-d H:i:s');
                }
                if (in_array('hardware_type', $cols, true)) {
                    $fields[] = 'hardware_type';
                    $values[] = $hwType;
                }
                if (in_array('panel_watts', $cols, true)) {
                    $fields[] = 'panel_watts';
                    $values[] = $panelWatts !== '' ? (float)$panelWatts : null;
                }
                if (in_array('inverter_kva', $cols, true)) {
                    $fields[] = 'inverter_kva';
                    $values[] = $inverterKva !== '' ? (float)$inverterKva : null;
                }
                if (in_array('battery_kwh', $cols, true)) {
                    $fields[] = 'battery_kwh';
                    $values[] = $batteryKwh !== '' ? (float)$batteryKwh : null;
                }
                if (in_array('max_pv_input_w', $cols, true)) {
                    $fields[] = 'max_pv_input_w';
                    $values[] = $maxPvInput !== '' ? (int)$maxPvInput : null;
                }

                // Legacy capacity_kw sync
                if (in_array('capacity_kw', $cols, true)) {
                    $capacityKw = null;
                    if ($hwType === 'solar_panel' && $panelWatts !== '' && is_numeric($panelWatts)) {
                        $capacityKw = round((float)$panelWatts / 1000, 3);
                    } elseif (($hwType === 'inverter' || $hwType === 'power_station') && $inverterKva !== '' && is_numeric($inverterKva)) {
                        $capacityKw = round((float)$inverterKva, 3);
                    } elseif ($hwType === 'battery' && $batteryKwh !== '' && is_numeric($batteryKwh)) {
                        $capacityKw = round((float)$batteryKwh, 3);
                    }
                    if ($capacityKw !== null) {
                        $fields[] = 'capacity_kw';
                        $values[] = $capacityKw;
                    }
                }

                $ph = implode(',', array_fill(0, count($fields), '?'));
                $db->prepare("INSERT INTO solar_listings (" . implode(',', $fields) . ") VALUES ($ph)")
                   ->execute($values);

                $listingId = (int)$db->lastInsertId();

                // THE FIX: store the photo where the whole site reads it.
                if ($imageUrl !== null && $listingId > 0) {
                    $db->prepare("
                        INSERT INTO listing_images (listing_id, listing_type, url, sort_order)
                        VALUES (?, 'solar', ?, 1)
                    ")->execute([$listingId, $imageUrl]);
                }

                $db->commit();

                $_SESSION['flash_success'] = 'Hardware item "' . $title . '" added to your inventory.';
                header('Location: hardware.php');
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('add-hardware error: ' . $e->getMessage());
                $errors[] = 'Could not save hardware item: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
$pageTitle = 'Add Hardware - Agent Dashboard';
include __DIR__ . '/../templates/header.php';
?>
<style>
.hw-cap {
    border: 1px dashed #C6A43F;
    border-radius: 8px;
    padding: 12px;
    margin-top: 4px;
    background: #fffdf5;
}
.hw-image-preview {
    max-width: 200px;
    max-height: 200px;
    border-radius: 10px;
    margin-top: 10px;
    display: none;
    border: 1px solid #E0E0E0;
}
.hw-image-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: #F5F5F5;
    border: 1px solid #E0E0E0;
    border-radius: 10px;
    cursor: pointer;
    font-size: 13px;
    color: #333;
    transition: all 0.2s;
}
.hw-image-label:hover {
    background: #E8E8E8;
}
.hw-image-note {
    font-size: 11px;
    color: #999;
    margin-top: 6px;
}
</style>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-plus" style="color:#C6A43F;"></i> Add Hardware</h1>
                <p>Add a solar hardware item to your inventory</p>
            </div>
            <a href="hardware.php" class="je-btn je-btn-outline"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="je-form-error">
                <?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
            </div>
        <?php endif; ?>

        <div class="je-panel">
            <div class="je-panel-body">
                <form method="POST" action="add-hardware.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="je-form-group">
                        <label>Item Title *</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. 550W Monocrystalline Solar Panel">
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label>Hardware Type *</label>
                            <select name="hardware_type" id="hardwareType" required>
                                <option value="">Select type...</option>
                                <?php foreach ($hardwareTypes as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= (($_POST['hardware_type'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="je-form-group">
                            <label>Brand</label>
                            <input type="text" name="brand" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" placeholder="e.g. Jinko Solar">
                        </div>
                    </div>

                    <!-- Capacity fields -->
                    <div class="hw-cap">
                        <div class="je-form-row">
                            <div class="je-form-group" id="grp_panel_watts" style="display:none;">
                                <label>Panel Capacity (W)</label>
                                <input type="number" step="0.01" min="0" name="panel_watts" value="<?= htmlspecialchars($_POST['panel_watts'] ?? '') ?>" placeholder="e.g. 550">
                            </div>
                            <div class="je-form-group" id="grp_inverter_kva" style="display:none;">
                                <label>Inverter Capacity (kW/kVA)</label>
                                <input type="number" step="0.01" min="0" name="inverter_kva" value="<?= htmlspecialchars($_POST['inverter_kva'] ?? '') ?>" placeholder="e.g. 5">
                            </div>
                            <div class="je-form-group" id="grp_battery_kwh" style="display:none;">
                                <label>Battery Capacity (kWh)</label>
                                <input type="number" step="0.01" min="0" name="battery_kwh" value="<?= htmlspecialchars($_POST['battery_kwh'] ?? '') ?>" placeholder="e.g. 10">
                            </div>
                            <?php if ($hasMaxPvCol): ?>
                            <div class="je-form-group" id="grp_max_pv" style="display:none;">
                                <label>Max Panel Input (W)</label>
                                <input type="number" step="1" min="0" name="max_pv_input_w" value="<?= htmlspecialchars($_POST['max_pv_input_w'] ?? '') ?>" placeholder="e.g. 300">
                            </div>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:12px;color:#888;margin-top:6px;" id="hw_hint">Select a hardware type to enter its capacity in the correct unit.</p>
                    </div>

                    <!-- Product Image -->
                    <div class="je-form-group" style="margin-top:16px;">
                        <label>Product Image</label>
                        <div>
                            <label class="hw-image-label" for="productImage">
                                <i class="fas fa-camera"></i> Choose Image
                            </label>
                            <input type="file" name="product_image" id="productImage" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;">
                        </div>
                        <img id="imagePreview" class="hw-image-preview" alt="Preview">
                        <p class="hw-image-note">Recommended: Square or landscape image. Allowed: JPG, PNG, WEBP, GIF. Max: 5MB.</p>
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label>Warranty (years)</label>
                            <input type="number" name="warranty_years" value="<?= htmlspecialchars($_POST['warranty_years'] ?? '') ?>" placeholder="e.g. 25">
                        </div>
                        <div class="je-form-group">
                            <label>Price (₦) *</label>
                            <input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="e.g. 450000">
                        </div>
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label>City</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                        </div>
                        <div class="je-form-group">
                            <label>State</label>
                            <input type="text" name="state" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="je-form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="je-btn je-btn-gold">
                        <i class="fas fa-check"></i> Add Hardware
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
(function() {
    var sel = document.getElementById('hardwareType');

    function sync() {
        var t = sel ? sel.value : '';
        var show = function(id, on) {
            var el = document.getElementById(id);
            if (el) el.style.display = on ? '' : 'none';
        };

        show('grp_panel_watts', t === 'solar_panel');
        show('grp_inverter_kva', t === 'inverter' || t === 'power_station');
        show('grp_battery_kwh', t === 'battery' || t === 'power_station');
        show('grp_max_pv', t === 'power_station');

        var hints = {
            solar_panel: 'Enter Panel Capacity in Watts (W).',
            inverter: 'Enter Inverter Capacity in kW/kVA.',
            battery: 'Enter Battery Capacity in kWh.',
            power_station: 'Enter Inverter (kW/kVA), Battery (kWh) and Max Panel Input (W).',
            charge_controller: 'No capacity needed for Charge Controller.',
            mounting_structure: 'No capacity needed for Mounting Structure.'
        };

        var h = document.getElementById('hw_hint');
        if (h) h.textContent = hints[t] || 'Select a hardware type to enter its capacity in the correct unit.';
    }

    if (sel) {
        sel.addEventListener('change', sync);
        sync();
    }

    var imageInput = document.getElementById('productImage');
    var imagePreview = document.getElementById('imagePreview');

    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function() {
            var file = this.files && this.files[0] ? this.files[0] : null;

            if (!file) {
                imagePreview.style.display = 'none';
                return;
            }

            var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!allowed.includes(file.type)) {
                if (typeof window.kinasToast === 'function') {
                    window.kinasToast('Invalid image type. Allowed: JPG, PNG, WEBP, GIF.', 'error', 5000);
                } else {
                    alert('Invalid image type. Allowed: JPG, PNG, WEBP, GIF.');
                }
                this.value = '';
                imagePreview.style.display = 'none';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                if (typeof window.kinasToast === 'function') {
                    window.kinasToast('Image is too large. Maximum allowed size is 5MB.', 'error', 5000);
                } else {
                    alert('Image is too large. Maximum allowed size is 5MB.');
                }
                this.value = '';
                imagePreview.style.display = 'none';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
