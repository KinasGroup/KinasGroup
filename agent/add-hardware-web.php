<?php
/**
 * Add Hardware - Web Runner (Agent Version)
 * Access via: https://kinas-group.com/agent/add-hardware-web.php
 */

// Fix paths for Railway deployment
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    die("❌ Please login as an agent first.");
}

$db = Database::getInstance()->getConnection();
$agentId = $_SESSION['user_id'];

// Check if hardware already exists
$check = $db->query("SELECT COUNT(*) FROM solar_listings WHERE service_type IN ('solar_panel', 'inverter', 'battery')")->fetchColumn();

echo "<h1>🔧 Add Hardware to Inventory</h1>\n";

if ($check > 0) {
    echo "<p style='color: green;'>✅ Hardware already exists in inventory. Found $check items.</p>\n";
    
    // Show existing hardware
    $existing = $db->query("SELECT id, title, service_type, brand, price FROM solar_listings WHERE service_type IN ('solar_panel', 'inverter', 'battery') LIMIT 10")->fetchAll();
    echo "<h2>📦 Existing Hardware:</h2>\n";
    echo "<ul>\n";
    foreach ($existing as $item) {
        echo "<li>" . htmlspecialchars($item['title']) . " (" . $item['service_type'] . ") - ₦" . number_format($item['price']) . "</li>\n";
    }
    echo "</ul>\n";
    echo "<p><a href='add-hardware-web.php?force=1'>Force add more</a></p>\n";
    exit;
}

// Force add option
if (isset($_GET['force']) && $_GET['force'] == 1) {
    $db->exec("DELETE FROM solar_listings WHERE service_type IN ('solar_panel', 'inverter', 'battery')");
    echo "<p>🗑️ Existing hardware removed. Adding fresh...</p>\n";
}

$hardware = [
    [
        'title' => '550W Monocrystalline Solar Panel',
        'service_type' => 'solar_panel',
        'brand' => 'Jinko Solar',
        'capacity_kw' => 0.55,
        'warranty_years' => 25,
        'price' => 450000,
        'description' => 'High-efficiency monocrystalline PERC solar panel with 21.5% efficiency.',
        'city' => 'Lagos',
        'state' => 'Lagos'
    ],
    [
        'title' => '12kVA Hybrid Inverter',
        'service_type' => 'inverter',
        'brand' => 'Growatt',
        'capacity_kw' => 12,
        'warranty_years' => 5,
        'price' => 3500000,
        'description' => 'Pure sine wave hybrid inverter with WiFi monitoring.',
        'city' => 'Lagos',
        'state' => 'Lagos'
    ],
    [
        'title' => '48V 400Ah Lithium Battery Bank',
        'service_type' => 'battery',
        'brand' => 'Pylontech',
        'capacity_kw' => 19.2,
        'warranty_years' => 10,
        'price' => 2800000,
        'description' => 'Lithium LiFePO4 battery with 6000+ cycles.',
        'city' => 'Lagos',
        'state' => 'Lagos'
    ],
    [
        'title' => '8kVA Hybrid Inverter',
        'service_type' => 'inverter',
        'brand' => 'Victron Energy',
        'capacity_kw' => 8,
        'warranty_years' => 5,
        'price' => 2200000,
        'description' => 'Compact hybrid inverter with Bluetooth monitoring.',
        'city' => 'Abuja',
        'state' => 'FCT'
    ],
    [
        'title' => '24V 200Ah Lithium Battery',
        'service_type' => 'battery',
        'brand' => 'Renogy',
        'capacity_kw' => 4.8,
        'warranty_years' => 5,
        'price' => 1200000,
        'description' => 'Lithium LiFePO4 battery with 200Ah capacity.',
        'city' => 'Lagos',
        'state' => 'Lagos'
    ],
    [
        'title' => '100A MPPT Charge Controller',
        'service_type' => 'charge_controller',
        'brand' => 'OutBack Power',
        'capacity_kw' => 4.8,
        'warranty_years' => 3,
        'price' => 350000,
        'description' => 'MPPT charge controller with 100A capacity.',
        'city' => 'Lagos',
        'state' => 'Lagos'
    ]
];

$count = 0;
echo "<h2>Adding hardware...</h2>\n<ul>\n";
foreach ($hardware as $item) {
    try {
        $stmt = $db->prepare("
            INSERT INTO solar_listings (
                agent_id, title, service_type, brand, capacity_kw, 
                warranty_years, price, description, city, state, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $agentId,
            $item['title'],
            $item['service_type'],
            $item['brand'],
            $item['capacity_kw'],
            $item['warranty_years'],
            $item['price'],
            $item['description'],
            $item['city'],
            $item['state']
        ]);
        $count++;
        echo "<li style='color:green;'>✅ Added: " . htmlspecialchars($item['title']) . "</li>\n";
    } catch (Exception $e) {
        echo "<li style='color:red;'>❌ Error: " . $e->getMessage() . "</li>\n";
    }
}
echo "</ul>\n";

echo "<h2>✅ Successfully added $count hardware items!</h2>\n";
echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>\n";
?>
