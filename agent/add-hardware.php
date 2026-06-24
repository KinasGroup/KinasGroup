<?php
/**
 * Agent Dashboard - Add Hardware
 * Access via: https://kinas-group.com/agent/add-hardware.php
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
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $capacityKw = floatval($_POST['capacity_kw'] ?? 0);
    $warrantyYears = intval($_POST['warranty_years'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    
    if (empty($title) || empty($serviceType) || empty($brand) || $capacityKw <= 0 || $price <= 0) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        $stmt = $db->prepare("
            INSERT INTO solar_listings (
                agent_id, title, service_type, brand, capacity_kw, 
                warranty_years, price, description, city, state, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        try {
            $stmt->execute([
                $agentId,
                $title,
                $serviceType,
                $brand,
                $capacityKw,
                $warrantyYears,
                $price,
                $description,
                $city,
                $state
            ]);
            $message = 'Hardware added successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$pageTitle = 'Add Hardware - Agent Dashboard';
include '../templates/header.php';
?>

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
.table-responsive {
    overflow-x: auto;
}
</style>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-microchip" style="color: #C6A43F;"></i> Add Hardware</h1>
                <p>Add solar panels, inverters, batteries, and other hardware to your inventory</p>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="je-banner is-success">
                <i class="je-banner-icon fas fa-check-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="je-banner is-danger">
                <i class="je-banner-icon fas fa-exclamation-circle"></i>
                <div class="je-banner-body">
                    <div class="je-banner-text"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="je-banner is-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                <i class="je-banner-icon fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <div class="je-banner-body">
                    <div class="je-banner-title"><?php echo $message; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="je-panel">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-plus-circle" style="color: #C6A43F;"></i> New Hardware Listing
                </div>
            </div>
            <div class="je-panel-body">
                <form method="POST" action="" class="je-form">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="je-form-group">
                            <label for="title"><i class="fas fa-tag"></i> Hardware Title *</label>
                            <input type="text" id="title" name="title" placeholder="e.g., 550W Monocrystalline Solar Panel" required>
                        </div>
                        <div class="je-form-group">
                            <label for="service_type"><i class="fas fa-cogs"></i> Hardware Type *</label>
                            <select id="service_type" name="service_type" required>
                                <option value="">Select type...</option>
                                <option value="solar_panel">🌞 Solar Panel</option>
                                <option value="inverter">⚡ Inverter</option>
                                <option value="battery">🔋 Battery</option>
                                <option value="charge_controller">🔌 Charge Controller</option>
                                <option value="mounting_structure">🏗️ Mounting Structure</option>
                            </select>
                        </div>
                        <div class="je-form-group">
                            <label for="brand"><i class="fas fa-building"></i> Brand *</label>
                            <input type="text" id="brand" name="brand" placeholder="e.g., Jinko Solar, Growatt" required>
                        </div>
                        <div class="je-form-group">
                            <label for="capacity_kw"><i class="fas fa-bolt"></i> Capacity (kW) *</label>
                            <input type="number" id="capacity_kw" name="capacity_kw" placeholder="e.g., 0.55 for panel, 12 for inverter" step="0.01" min="0.1" required>
                        </div>
                        <div class="je-form-group">
                            <label for="warranty_years"><i class="fas fa-shield-alt"></i> Warranty (Years)</label>
                            <input type="number" id="warranty_years" name="warranty_years" placeholder="e.g., 25" value="5">
                        </div>
                        <div class="je-form-group">
                            <label for="price"><i class="fas fa-money-bill-wave"></i> Price (₦) *</label>
                            <input type="number" id="price" name="price" placeholder="e.g., 450000" step="0.01" min="0" required>
                        </div>
                        <div class="je-form-group">
                            <label for="city"><i class="fas fa-city"></i> City</label>
                            <input type="text" id="city" name="city" placeholder="e.g., Lagos">
                        </div>
                        <div class="je-form-group">
                            <label for="state"><i class="fas fa-map-marker-alt"></i> State</label>
                            <input type="text" id="state" name="state" placeholder="e.g., Lagos">
                        </div>
                        <div class="je-form-group" style="grid-column: span 2;">
                            <label for="description"><i class="fas fa-align-left"></i> Description</label>
                            <textarea id="description" name="description" rows="4" placeholder="Describe the hardware specifications and features..."></textarea>
                        </div>
                    </div>

                    <div style="display: flex; gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #E0E0E0;">
                        <button type="submit" class="je-btn je-btn-gold" style="background: #C6A43F; color: #0A0A0A;">
                            <i class="fas fa-save"></i> Add Hardware
                        </button>
                        <a href="dashboard.php" class="je-btn je-btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Reference Guide -->
        <div class="je-panel" style="margin-top: 24px;">
            <div class="je-panel-header">
                <div class="je-panel-title">
                    <i class="fas fa-info-circle" style="color: #C6A43F;"></i> Hardware Reference Guide
                </div>
            </div>
            <div class="je-panel-body">
                <div class="table-responsive">
                <table class="je-table">
                    <thead>
                        <tr>
                            <th>Hardware Type</th>
                            <th>Typical Capacity</th>
                            <th>Typical Price Range (₦)</th>
                            <th>Warranty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>🌞 Solar Panel</strong></td>
                            <td>0.4 - 0.6 kW (400W - 600W)</td>
                            <td>300,000 - 600,000</td>
                            <td>25 years</td>
                        </tr>
                        <tr>
                            <td><strong>⚡ Inverter</strong></td>
                            <td>5 - 20 kVA</td>
                            <td>1,500,000 - 6,000,000</td>
                            <td>5 years</td>
                        </tr>
                        <tr>
                            <td><strong>🔋 Battery</strong></td>
                            <td>5 - 20 kWh</td>
                            <td>2,000,000 - 8,000,000</td>
                            <td>10 years</td>
                        </tr>
                        <tr>
                            <td><strong>🔌 Charge Controller</strong></td>
                            <td>50 - 200A</td>
                            <td>200,000 - 800,000</td>
                            <td>3 years</td>
                        </tr>
                        <tr>
                            <td><strong>🏗️ Mounting Structure</strong></td>
                            <td>Per panel</td>
                            <td>50,000 - 150,000</td>
                            <td>5 years</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../templates/footer.php'; ?>
