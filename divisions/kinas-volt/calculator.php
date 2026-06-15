<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';

$page_title = 'Solar Calculator - Kinas Volt';
$headerDepth = '../../';

require_once __DIR__ . '/../../templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Prata&display=swap" rel="stylesheet">
    <style>
        .calc-container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        .step { background: white; border: 1px solid #E0E0E0; border-radius: 16px; padding: 40px; margin-bottom: 30px; display: none; }
        .step.active { display: block; }
        .appliance-row { display: grid; grid-template-columns: 2fr 1fr 1fr 80px; gap: 12px; align-items: end; padding: 12px; background: #f8f8f8; border-radius: 8px; margin-bottom: 10px; }
        .results-section { background: #0A0A0A; color: white; padding: 40px; border-radius: 16px; }
        button { background: #C6A43F; color: #0A0A0A; font-weight: 600; padding: 14px 32px; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
<div class="calc-container">
    <h1 style="text-align:center;font-family:'Prata',serif;">KINAS VOLT SOLAR CALCULATOR</h1>

    <form id="solarCalculatorForm" method="POST" action="/api/solar/calculate.php">
        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">

        <!-- Step 1: Customer Info -->
        <div class="step active" id="step1">
            <h2>1. Customer Information</h2>
            <input type="text" name="full_name" placeholder="Full Name" required><br><br>
            <input type="tel" name="phone" placeholder="Phone Number" required><br><br>
            <input type="email" name="email" placeholder="Email Address" required><br><br>
            <input type="text" name="city_state" placeholder="City and State" required><br><br>
            <select name="property_type" required>
                <option value="">Property Type</option>
                <option value="Apartment">Apartment</option>
                <option value="Duplex">Duplex</option>
                <option value="Bungalow">Bungalow</option>
                <option value="Office">Office</option>
                <option value="Commercial">Commercial Building</option>
                <option value="Hotel">Hotel</option>
                <option value="Other">Other</option>
            </select>
            <button type="button" onclick="nextStep(2)">Next: Appliances →</button>
        </div>

        <!-- Step 2: Appliances (Dynamic) -->
        <div class="step" id="step2">
            <h2>2. Select Appliances</h2>
            <div id="appliance-list"></div>
            <button type="button" onclick="addCustomAppliance()">+ Add Custom Appliance</button><br><br>
            <button type="button" onclick="nextStep(3)">Next: Backup Requirements →</button>
        </div>

        <!-- Step 3: Backup -->
        <div class="step" id="step3">
            <h2>3. Backup Requirements</h2>
            <select name="backup_hours">
                <option value="8">8 Hours</option>
                <option value="12">12 Hours</option>
                <option value="24" selected>24 Hours</option>
            </select>
            <button type="submit">Calculate & Generate Proposal</button>
        </div>
    </form>

    <div id="results" class="results-section" style="display:none;"></div>
</div>

<script src="../../assets/js/solar-calculator.js"></script>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
