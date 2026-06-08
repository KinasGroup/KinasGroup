<?php
require_once '../config/database.php';
require_once '../../includes/session.php';

$calcId = $_GET['id'] ?? 0;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM solar_calculations WHERE id = ?");
    $stmt->execute([$calcId]);
    $calc = $stmt->fetch();
    
    if (!$calc) {
        die('Calculation not found');
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="kinas-volt-estimate.pdf"');
    
    // Simple text-based PDF-like output
    echo "KINAS VOLT - Solar Estimate\n";
    echo "==========================\n\n";
    echo "Date: " . date('F j, Y') . "\n\n";
    echo "System Size: {$calc['system_size']} kW\n";
    echo "Estimated Cost: $" . number_format($calc['system_cost']) . "\n";
    echo "Monthly Savings: $" . number_format($calc['monthly_savings']) . "\n";
    echo "Payback Period: " . round($calc['system_cost'] / ($calc['monthly_savings'] * 12), 1) . " years\n";
    echo "20-Year Savings: $" . number_format($calc['monthly_savings'] * 12 * 20 - $calc['system_cost']) . "\n";
} catch (Exception $e) {
    die('Failed to generate receipt');
}