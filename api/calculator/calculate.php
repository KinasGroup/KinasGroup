<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$monthlyBill = floatval($data['monthly_bill'] ?? 200);
$roofArea = floatval($data['roof_area'] ?? 1500);
$sunHours = floatval($data['sun_hours'] ?? 5);
$electricityRate = floatval($data['electricity_rate'] ?? 0.12);

$monthlyConsumption = $monthlyBill / $electricityRate;
$systemSize = round(($monthlyConsumption / ($sunHours * 30)) * 100) / 100;
$systemCost = round($systemSize * 2500);
$monthlySavings = round($monthlyBill * 0.85);
$coverage = min(round(($systemSize * $sunHours * 30 / $monthlyConsumption) * 100), 100);
$payback = round(($systemCost / ($monthlySavings * 12)) * 10) / 10;
$savings20 = round($monthlySavings * 12 * 20 - $systemCost);

// Save calculation if user is logged in
if (SessionManager::isLoggedIn()) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO solar_calculations (user_id, monthly_bill, roof_area, sun_hours, electricity_rate, system_size, system_cost, monthly_savings, calculation_data, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $monthlyBill, $roofArea, $sunHours, $electricityRate, $systemSize, $systemCost, $monthlySavings, json_encode($data)]);
}

echo json_encode([
    'success' => true,
    'systemSize' => $systemSize,
    'systemCost' => $systemCost,
    'monthlySavings' => $monthlySavings,
    'coverage' => $coverage,
    'payback' => $payback,
    'savings20' => $savings20
]);