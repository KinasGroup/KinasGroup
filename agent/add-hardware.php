<?php
/**
 * Agent Dashboard - Add Hardware
 * Lets a (super) agent add a single solar hardware item
 * (panel, inverter, battery, charge controller, mounting structure)
 * to their inventory in the solar_listings table.
 *
 * Access via: https://kinas-group.com/agent/add-hardware.php
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    header('Location: /auth/login.php');
    exit;
}

$db      = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];

$hardwareTypes = [
    'solar_panel'        => 'Solar Panel',
    'inverter'           => 'Inverter',
    'battery'            => 'Battery',
    'charge_controller'  => 'Charge Controller',
    'mounting_structure' => 'Mounting Structure',
];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $title         = trim($_POST['title'] ?? '');
        $serviceType   = $_POST['service_type'] ?? '';
        $brand         = trim($_POST['brand'] ?? '');
        $capacityKw    = trim($_POST['capacity_kw'] ?? '');
        $warrantyYears = trim($_POST['warranty_years'] ?? '');
        $price         = trim($_POST['price'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $city          = trim($_POST['city'] ?? '');
        $state         = trim($_POST['state'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if (!array_key_exists($serviceType, $hardwareTypes)) $errors[] = 'Please choose a valid hardware type.';
        if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Please enter a valid price.';
        if ($capacityKw !== '' && !is_numeric($capacityKw)) $errors[] = 'Capacity must be a number.';
        if ($warrantyYears !== '' && !is_numeric($warrantyYears)) $errors[] = 'Warranty years must be a number.';

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO solar_listings (
                        agent_id, title, service_type, brand, capacity_kw,
                        warranty_years, price, description, city, state, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([
                    $agentId,
                    $title,
                    $serviceType,
                    $brand !== '' ? $brand : null,
                    $capacityKw !== '' ? $capacityKw : null,
                    $warrantyYears !== '' ? $warrantyYears : null,
                    $price,
                    $description !== '' ? $description : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                ]);
                $_SESSION['flash_success'] = 'Hardware item "' . $title . '" added to your inventory.';
                header('Location: hardware.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Could not save hardware item. Please try again.';
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
$pageTitle  = 'Add Hardware - Agent Dashboard';
include __DIR__ . '/../templates/header.php';
?>

<div class="je-dash-shell">
    <?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="je-dash-main">
        <div class="je-dash-header">
            <div>
                <h1><i class="fas fa-plus" style="color: #C6A43F;"></i> Add Hardware</h1>
                <p>Add a new solar hardware item to your inventory</p>
            </div>
            <div>
                <a href="hardware.php" class="je-btn je-btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Inventory
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="je-form-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="je-panel">
            <div class="je-panel-body">
                <form method="POST" action="add-hardware.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="je-form-group">
                        <label for="title">Item Title</label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="e.g. 550W Monocrystalline Solar Panel">
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label for="service_type">Hardware Type</label>
                            <select id="service_type" name="service_type" required>
                                <option value="">Select type...</option>
                                <?php foreach ($hardwareTypes as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>"
                                        <?= (($_POST['service_type'] ?? '') === $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="je-form-group">
                            <label for="brand">Brand</label>
                            <input type="text" id="brand" name="brand"
                                   value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"
                                   placeholder="e.g. Jinko Solar">
                        </div>
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label for="capacity_kw">Capacity (kW)</label>
                            <input type="number" step="0.01" id="capacity_kw" name="capacity_kw"
                                   value="<?= htmlspecialchars($_POST['capacity_kw'] ?? '') ?>"
                                   placeholder="e.g. 0.55">
                        </div>
                        <div class="je-form-group">
                            <label for="warranty_years">Warranty (years)</label>
                            <input type="number" id="warranty_years" name="warranty_years"
                                   value="<?= htmlspecialchars($_POST['warranty_years'] ?? '') ?>"
                                   placeholder="e.g. 25">
                        </div>
                    </div>

                    <div class="je-form-row">
                        <div class="je-form-group">
                            <label for="price">Price (₦)</label>
                            <input type="number" step="0.01" id="price" name="price" required
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                   placeholder="e.g. 450000">
                        </div>
                        <div class="je-form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city"
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                   placeholder="e.g. Lagos">
                        </div>
                    </div>

                    <div class="je-form-group">
                        <label for="state">State</label>
                        <input type="text" id="state" name="state"
                               value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
                               placeholder="e.g. Lagos">
                    </div>

                    <div class="je-form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="Describe this hardware item..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="je-btn je-btn-gold">
                        <i class="fas fa-check"></i> Add Hardware
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
