<?php
/**
 * Agent Dashboard - Edit Listing
 */

require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../api/config/database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$division = isset($_GET['division']) ? $_GET['division'] : '';

if (!$listingId || !$division) {
    header('Location: listings.php?error=Invalid listing');
    exit;
}

// Map division to table
$tableMap = [
    'solar' => 'solar_listings',
    'car' => 'car_listings',
    'property' => 'property_listings',
    'marketplace' => 'marketplace_listings'
];

if (!isset($tableMap[$division])) {
    header('Location: listings.php?error=Invalid division');
    exit;
}

$table = $tableMap[$division];

// Get the listing
$stmt = $db->prepare("SELECT * FROM $table WHERE id = ? AND agent_id = ?");
$stmt->execute([$listingId, $agentId]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: listings.php?error=Listing not found');
    exit;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Division specific fields
    $extraFields = [];
    switch ($division) {
        case 'solar':
            $extraFields['service_type'] = trim($_POST['service_type'] ?? '');
            $extraFields['capacity_kw'] = floatval($_POST['capacity_kw'] ?? 0);
            $extraFields['warranty_years'] = intval($_POST['warranty_years'] ?? 0);
            break;
        case 'car':
            $extraFields['model'] = trim($_POST['model'] ?? '');
            $extraFields['year'] = intval($_POST['year'] ?? 0);
            $extraFields['mileage'] = intval($_POST['mileage'] ?? 0);
            $extraFields['transmission'] = trim($_POST['transmission'] ?? '');
            $extraFields['fuel_type'] = trim($_POST['fuel_type'] ?? '');
            $extraFields['body_type'] = trim($_POST['body_type'] ?? '');
            $extraFields['color'] = trim($_POST['color'] ?? '');
            break;
        case 'property':
            $extraFields['beds'] = intval($_POST['beds'] ?? 0);
            $extraFields['baths'] = intval($_POST['baths'] ?? 0);
            $extraFields['sqft'] = intval($_POST['sqft'] ?? 0);
            $extraFields['property_type'] = trim($_POST['property_type'] ?? '');
            $extraFields['address'] = trim($_POST['address'] ?? '');
            break;
        case 'marketplace':
            $extraFields['category'] = trim($_POST['category'] ?? '');
            $extraFields['condition'] = trim($_POST['condition'] ?? '');
            $extraFields['weight'] = floatval($_POST['weight'] ?? 0);
            $extraFields['dimensions'] = trim($_POST['dimensions'] ?? '');
            break;
    }
    
    if (empty($title) || $price <= 0) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        // Build update query
        $updateFields = [
            'title = ?',
            'price = ?',
            'description = ?',
            'city = ?',
            'state = ?',
            'brand = ?',
            'featured = ?'
        ];
        $params = [$title, $price, $description, $city, $state, $brand, $featured];
        
        foreach ($extraFields as $field => $value) {
            $updateFields[] = "$field = ?";
            $params[] = $value;
        }
        
        $params[] = $listingId;
        $params[] = $agentId;
        
        $sql = "UPDATE $table SET " . implode(', ', $updateFields) . " WHERE id = ? AND agent_id = ?";
        $update = $db->prepare($sql);
        
        try {
            $update->execute($params);
            $message = 'Listing updated successfully!';
            $messageType = 'success';
            
            // Refresh the listing data
            $stmt = $db->prepare("SELECT * FROM $table WHERE id = ? AND agent_id = ?");
            $stmt->execute([$listingId, $agentId]);
            $listing = $stmt->fetch();
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$pageTitle = 'Edit Listing - Agent Dashboard';
include '../templates/header.php';
?>

<div class="je-dash-shell">
    <!-- Sidebar -->
    <aside class="je-dash-sidebar">
        <div class="je-dash-sidebar-brand">
            <i class="fas fa-solar-panel"></i> KINAS VOLT
        </div>
        <ul class="je-dash-nav">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="listings.php"><i class="fas fa-list-ul"></i> My Listings</a></li>
            <li><a href="add-listing.php"><i class="fas fa-plus-circle"></i> Add Listing</a></li>
            <li><a href="hardware.php"><i class="fas fa-microchip"></i> Hardware Inventory</a></li>
            <li><a href="add-hardware.php"><i class="fas fa-plus"></i> Add Hardware</a></li>
            <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <hr class="sidebar-divider">
            <li><a href="/"><i class="fas fa-home"></i> Back to Site</a></li>
            <li class="je-dash-signout"><a href="/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-edit" style="color: #C6A43F;"></i> Edit Listing</h1>
                <p>Update your listing details</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="je-banner is-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <i class="je-banner-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <div class="je-banner-body">
                    <div class="je-banner-title"><?php echo htmlspecialchars($message ?? ''); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="je-panel">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-edit" style="color: #C6A43F;"></i> Edit Listing: <?php echo htmlspecialchars($listing['title'] ?? ''); ?>
                </div>
            </div>
            <div class="je-panel-body">
                <form method="POST" action="" class="je-form" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="je-form-group" style="grid-column: span 2;">
                            <label for="title"><i class="fas fa-tag"></i> Listing Title *</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($listing['title'] ?? ''); ?>" placeholder="e.g., 550W Solar Panel Installation" required>
                        </div>
                        
                        <div class="je-form-group">
                            <label for="price"><i class="fas fa-money-bill-wave"></i> Price (₦) *</label>
                            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($listing['price'] ?? 0); ?>" step="0.01" min="0" required>
                        </div>
                        
                        <div class="je-form-group">
                            <label for="brand"><i class="fas fa-building"></i> Brand</label>
                            <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($listing['brand'] ?? ''); ?>" placeholder="e.g., Jinko Solar, Growatt">
                        </div>

                        <?php if ($division === 'solar'): ?>
                            <div class="je-form-group">
                                <label for="service_type"><i class="fas fa-cogs"></i> Service Type</label>
                                <select id="service_type" name="service_type">
                                    <option value="residential" <?php echo ($listing['service_type'] ?? '') === 'residential' ? 'selected' : ''; ?>>Residential</option>
                                    <option value="commercial" <?php echo ($listing['service_type'] ?? '') === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                    <option value="industrial" <?php echo ($listing['service_type'] ?? '') === 'industrial' ? 'selected' : ''; ?>>Industrial</option>
                                    <option value="maintenance" <?php echo ($listing['service_type'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="financing" <?php echo ($listing['service_type'] ?? '') === 'financing' ? 'selected' : ''; ?>>Financing</option>
                                </select>
                            </div>
                            <div class="je-form-group">
                                <label for="capacity_kw"><i class="fas fa-bolt"></i> Capacity (kW)</label>
                                <input type="number" id="capacity_kw" name="capacity_kw" value="<?php echo htmlspecialchars($listing['capacity_kw'] ?? 0); ?>" step="0.1" placeholder="e.g., 5.5">
                            </div>
                            <div class="je-form-group">
                                <label for="warranty_years"><i class="fas fa-shield-alt"></i> Warranty (Years)</label>
                                <input type="number" id="warranty_years" name="warranty_years" value="<?php echo htmlspecialchars($listing['warranty_years'] ?? 0); ?>" placeholder="e.g., 25">
                            </div>
                        <?php endif; ?>

                        <?php if ($division === 'car'): ?>
                            <div class="je-form-group">
                                <label for="model"><i class="fas fa-car"></i> Model</label>
                                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($listing['model'] ?? ''); ?>" placeholder="e.g., 911 Carrera">
                            </div>
                            <div class="je-form-group">
                                <label for="year"><i class="fas fa-calendar"></i> Year</label>
                                <input type="number" id="year" name="year" value="<?php echo htmlspecialchars($listing['year'] ?? 0); ?>" placeholder="e.g., 2023">
                            </div>
                            <div class="je-form-group">
                                <label for="mileage"><i class="fas fa-road"></i> Mileage (km)</label>
                                <input type="number" id="mileage" name="mileage" value="<?php echo htmlspecialchars($listing['mileage'] ?? 0); ?>" placeholder="e.g., 15000">
                            </div>
                            <div class="je-form-group">
                                <label for="transmission"><i class="fas fa-cog"></i> Transmission</label>
                                <select id="transmission" name="transmission">
                                    <option value="" <?php echo empty($listing['transmission'] ?? '') ? 'selected' : ''; ?>>Select...</option>
                                    <option value="Automatic" <?php echo ($listing['transmission'] ?? '') === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                    <option value="Manual" <?php echo ($listing['transmission'] ?? '') === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="Semi-Automatic" <?php echo ($listing['transmission'] ?? '') === 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                </select>
                            </div>
                            <div class="je-form-group">
                                <label for="fuel_type"><i class="fas fa-gas-pump"></i> Fuel Type</label>
                                <select id="fuel_type" name="fuel_type">
                                    <option value="" <?php echo empty($listing['fuel_type'] ?? '') ? 'selected' : ''; ?>>Select...</option>
                                    <option value="Petrol" <?php echo ($listing['fuel_type'] ?? '') === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                                    <option value="Diesel" <?php echo ($listing['fuel_type'] ?? '') === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="Electric" <?php echo ($listing['fuel_type'] ?? '') === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                    <option value="Hybrid" <?php echo ($listing['fuel_type'] ?? '') === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="je-form-group">
                                <label for="body_type"><i class="fas fa-car-side"></i> Body Type</label>
                                <input type="text" id="body_type" name="body_type" value="<?php echo htmlspecialchars($listing['body_type'] ?? ''); ?>" placeholder="e.g., Coupe, Sedan, SUV">
                            </div>
                            <div class="je-form-group">
                                <label for="color"><i class="fas fa-palette"></i> Color</label>
                                <input type="text" id="color" name="color" value="<?php echo htmlspecialchars($listing['color'] ?? ''); ?>" placeholder="e.g., Red, Black, Silver">
                            </div>
                        <?php endif; ?>

                        <?php if ($division === 'property'): ?>
                            <div class="je-form-group">
                                <label for="property_type"><i class="fas fa-home"></i> Property Type</label>
                                <select id="property_type" name="property_type">
                                    <option value="" <?php echo empty($listing['property_type'] ?? '') ? 'selected' : ''; ?>>Select...</option>
                                    <option value="Apartment" <?php echo ($listing['property_type'] ?? '') === 'Apartment' ? 'selected' : ''; ?>>Apartment</option>
                                    <option value="Duplex" <?php echo ($listing['property_type'] ?? '') === 'Duplex' ? 'selected' : ''; ?>>Duplex</option>
                                    <option value="Bungalow" <?php echo ($listing['property_type'] ?? '') === 'Bungalow' ? 'selected' : ''; ?>>Bungalow</option>
                                    <option value="Office" <?php echo ($listing['property_type'] ?? '') === 'Office' ? 'selected' : ''; ?>>Office</option>
                                    <option value="Commercial" <?php echo ($listing['property_type'] ?? '') === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                    <option value="Hotel" <?php echo ($listing['property_type'] ?? '') === 'Hotel' ? 'selected' : ''; ?>>Hotel</option>
                                </select>
                            </div>
                            <div class="je-form-group">
                                <label for="address"><i class="fas fa-map-pin"></i> Address</label>
                                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($listing['address'] ?? ''); ?>" placeholder="Full property address">
                            </div>
                            <div class="je-form-group">
                                <label for="beds"><i class="fas fa-bed"></i> Bedrooms</label>
                                <input type="number" id="beds" name="beds" value="<?php echo htmlspecialchars($listing['beds'] ?? 0); ?>">
                            </div>
                            <div class="je-form-group">
                                <label for="baths"><i class="fas fa-bath"></i> Bathrooms</label>
                                <input type="number" id="baths" name="baths" value="<?php echo htmlspecialchars($listing['baths'] ?? 0); ?>">
                            </div>
                            <div class="je-form-group">
                                <label for="sqft"><i class="fas fa-ruler-combined"></i> Square Feet</label>
                                <input type="number" id="sqft" name="sqft" value="<?php echo htmlspecialchars($listing['sqft'] ?? 0); ?>">
                            </div>
                        <?php endif; ?>

                        <?php if ($division === 'marketplace'): ?>
                            <div class="je-form-group">
                                <label for="category"><i class="fas fa-tags"></i> Category</label>
                                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($listing['category'] ?? ''); ?>" placeholder="e.g., Electronics, Furniture">
                            </div>
                            <div class="je-form-group">
                                <label for="condition"><i class="fas fa-clipboard-check"></i> Condition</label>
                                <select id="condition" name="condition">
                                    <option value="" <?php echo empty($listing['condition'] ?? '') ? 'selected' : ''; ?>>Select...</option>
                                    <option value="New" <?php echo ($listing['condition'] ?? '') === 'New' ? 'selected' : ''; ?>>New</option>
                                    <option value="Like New" <?php echo ($listing['condition'] ?? '') === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                                    <option value="Good" <?php echo ($listing['condition'] ?? '') === 'Good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="Fair" <?php echo ($listing['condition'] ?? '') === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                                </select>
                            </div>
                            <div class="je-form-group">
                                <label for="weight"><i class="fas fa-weight"></i> Weight (kg)</label>
                                <input type="number" id="weight" name="weight" value="<?php echo htmlspecialchars($listing['weight'] ?? 0); ?>" step="0.1">
                            </div>
                            <div class="je-form-group">
                                <label for="dimensions"><i class="fas fa-arrows-alt"></i> Dimensions</label>
                                <input type="text" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($listing['dimensions'] ?? ''); ?>" placeholder="e.g., 10x10x10 cm">
                            </div>
                        <?php endif; ?>

                        <div class="je-form-group">
                            <label for="city"><i class="fas fa-city"></i> City</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($listing['city'] ?? ''); ?>" placeholder="e.g., Lagos">
                        </div>
                        
                        <div class="je-form-group">
                            <label for="state"><i class="fas fa-map-marker-alt"></i> State</label>
                            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($listing['state'] ?? ''); ?>" placeholder="e.g., Lagos">
                        </div>

                        <div class="je-form-group" style="grid-column: span 2;">
                            <label for="description"><i class="fas fa-align-left"></i> Description</label>
                            <textarea id="description" name="description" rows="5" placeholder="Describe your listing in detail..."><?php echo htmlspecialchars($listing['description'] ?? ''); ?></textarea>
                        </div>

                        <?php if ($division !== 'hardware'): ?>
                        <div class="je-form-group" style="grid-column: span 2;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="featured" value="1" <?php echo (!empty($listing['featured'])) ? 'checked' : ''; ?>>
                                <span><i class="fas fa-star" style="color: #C6A43F;"></i> <strong>Feature this listing for premium visibility</strong></span>
                            </label>
                            <small style="color: #888;">Featured listings appear on the homepage and get more views.</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #E0E0E0;">
                        <button type="submit" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A;">
                            <i class="fas fa-save"></i> Update Listing
                        </button>
                        <a href="listings.php" class="je-btn je-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<style>
.je-form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--je-line);
    border-radius: 3px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
}
.je-form-group textarea:focus {
    outline: none;
    border-color: var(--je-gold);
}
</style>

<?php include '../templates/footer.php'; ?>
