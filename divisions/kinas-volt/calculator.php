<?php
// calculator.php - Footer social icons fixed
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';

$page_title = 'Solar Savings Calculator - Kinas Volt';
$headerDepth = '../../';

// Add footer social icon CSS fix
$footerSocialCSS = '
<style>
/* Force social icons to display properly */
.je-footer-social {
    display: flex !important;
    gap: 12px !important;
    margin-top: 16px !important;
    flex-wrap: wrap !important;
}
.je-footer-social a {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
    color: #fff !important;
    text-decoration: none !important;
    transition: all 0.3s ease !important;
    font-size: 18px !important;
    background: rgba(255,255,255,0.05) !important;
}
.je-footer-social a:hover {
    background: #C6A43F !important;
    border-color: #C6A43F !important;
    color: #0A0A0A !important;
    transform: translateY(-3px) !important;
}
.je-footer-social a i {
    font-size: 18px !important;
    line-height: 1 !important;
    display: inline-block !important;
    color: inherit !important;
}
</style>
';

// Prepend the CSS fix to the page
$footerSocialCSS = '';

require_once __DIR__ . '/../../templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gold: #C6A43F;
            --primary-gold-dark: #A8882E;
            --dark-bg: #0A0A0A;
            --dark-card: #141414;
            --dark-surface: #1A1A1A;
            --text-light: #FFFFFF;
            --text-muted: rgba(255,255,255,0.7);
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --success: #2c7a47;
            --error: #dc3545;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #0A0A0A 0%, #1a1a2e 100%);
            font-family: 'Inter', sans-serif;
            color: var(--text-light);
            overflow-x: hidden;
        }

        .calculator-hero {
            background: linear-gradient(135deg, rgba(10,10,10,0.95), rgba(26,26,46,0.95));
            padding: 100px 0 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .calculator-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://images.unsplash.com/photo-1509391366360-2e959784a276?w=1920&q=80') center/cover;
            opacity: 0.1;
            pointer-events: none;
        }
        .calculator-hero h1 {
            font-family: 'Prata', serif;
            font-size: 48px;
            font-weight: 400;
            background: linear-gradient(135deg, #FFFFFF 0%, var(--primary-gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }
        .calculator-hero p {
            color: var(--text-muted);
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        .calc-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 24px 80px;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 80px;
            margin-bottom: 60px;
            position: relative;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: rgba(255,255,255,0.1);
            z-index: 0;
        }
        .step-indicator {
            text-align: center;
            position: relative;
            z-index: 1;
            cursor: pointer;
            transition: var(--transition);
        }
        .step-number {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-weight: 700;
            font-size: 18px;
            transition: var(--transition);
        }
        .step-indicator.active .step-number {
            background: var(--primary-gold);
            border-color: var(--primary-gold);
            color: var(--dark-bg);
            box-shadow: 0 0 20px rgba(198,164,63,0.3);
        }
        .step-indicator.completed .step-number {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .step-indicator.completed .step-number::after {
            content: '✓';
            font-size: 20px;
        }
        .step-indicator.completed .step-number span {
            display: none;
        }
        .step-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
        }
        .step-indicator.active .step-label {
            color: var(--primary-gold);
        }

        .step {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .step.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-card {
            background: var(--dark-card);
            border-radius: var(--border-radius);
            padding: 40px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .form-card h2 {
            font-family: 'Prata', serif;
            font-size: 28px;
            margin-bottom: 32px;
            color: var(--primary-gold);
        }
        .form-card h2 i { margin-right: 12px; }
        .form-card h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: var(--text-light);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group label i {
            margin-right: 8px;
            color: var(--primary-gold);
        }
        .form-group input,
        .form-group select {
            padding: 14px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text-light);
            font-size: 16px;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            width: 100%;
            appearance: auto;
            -webkit-appearance: auto;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-gold);
            background: rgba(255,255,255,0.08);
        }
        .form-group input::placeholder {
            color: rgba(255,255,255,0.3);
        }
        .form-group select option {
            background: #1a1a1a;
            color: #fff;
        }

        .appliances-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 60px;
            gap: 16px;
            padding: 12px 16px;
            background: rgba(198,164,63,0.1);
            border-radius: 8px;
            margin-bottom: 12px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            color: var(--primary-gold);
        }
        .appliance-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 60px;
            gap: 16px;
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            margin-bottom: 12px;
            transition: var(--transition);
        }
        .appliance-row:hover {
            background: rgba(255,255,255,0.06);
        }
        .appliance-row input,
        .appliance-row select {
            padding: 10px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
            width: 100%;
        }

        .remove-appliance {
            background: rgba(220,53,69,0.25);
            border: 1px solid rgba(220,53,69,0.3);
            border-radius: 6px;
            color: #ff6b6b !important;
            cursor: pointer;
            transition: var(--transition);
            font-size: 14px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
        }
        .remove-appliance:hover {
            background: rgba(220,53,69,0.4);
            border-color: #dc3545;
            color: #ff4757 !important;
            transform: scale(1.05);
        }
        .remove-appliance i {
            font-size: 14px;
            color: #ff6b6b !important;
        }
        .remove-appliance:hover i {
            color: #ff4757 !important;
        }

        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .preset-chip {
            background: var(--dark-surface);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 40px;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            color: var(--text-muted);
        }
        .preset-chip:hover {
            border-color: var(--primary-gold);
            background: rgba(198,164,63,0.1);
            color: var(--primary-gold);
        }
        .add-appliance-btn {
            background: transparent;
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 16px;
            width: 100%;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 16px;
        }
        .add-appliance-btn:hover {
            border-color: var(--primary-gold);
            color: var(--primary-gold);
        }

        .btn {
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: none;
            font-family: 'Inter', sans-serif;
        }
        .btn-primary {
            background: var(--primary-gold);
            color: var(--dark-bg);
        }
        .btn-primary:hover {
            background: var(--primary-gold-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(198,164,63,0.3);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-light);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
        }
        .btn-group {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 32px;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(198,164,63,0.2);
            border-top-color: var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .results-section {
            display: none;
            margin-top: 40px;
            animation: fadeIn 0.5s ease;
        }
        .results-section.active {
            display: block;
        }
        .results-card {
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--primary-gold-dark) 100%);
            border-radius: var(--border-radius);
            padding: 40px;
            color: var(--dark-bg);
        }
        .results-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .results-header .check-icon {
            width: 64px;
            height: 64px;
            background: rgba(0,0,0,0.1);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
        }
        .results-header h2 {
            font-family: 'Prata', serif;
            font-size: 32px;
            margin-bottom: 8px;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .result-item {
            background: rgba(0,0,0,0.08);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .result-item i {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .result-value {
            font-size: 24px;
            font-weight: 800;
            margin: 4px 0;
        }
        .result-label {
            font-size: 12px;
            opacity: 0.7;
        }
        .proposal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-dark {
            background: var(--dark-bg);
            color: white;
        }
        .btn-dark:hover {
            background: #1A1A1A;
            transform: translateY(-2px);
        }
        .btn-outline-dark {
            background: transparent;
            border: 2px solid var(--dark-bg);
            color: var(--dark-bg);
        }
        .btn-outline-dark:hover {
            background: var(--dark-bg);
            color: white;
        }

        .error-message {
            background: rgba(220,53,69,0.2);
            border: 1px solid var(--error);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--error);
            margin-bottom: 16px;
            display: none;
        }
        .error-message.show {
            display: block;
        }

        /* Footer Social Icons Fix - Force visibility */
        .je-footer-social {
            display: flex !important;
            gap: 12px !important;
            margin-top: 16px !important;
            flex-wrap: wrap !important;
        }
        .je-footer-social a {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            color: #fff !important;
            text-decoration: none !important;
            transition: all 0.3s ease !important;
            font-size: 18px !important;
            background: rgba(255,255,255,0.05) !important;
        }
        .je-footer-social a:hover {
            background: #C6A43F !important;
            border-color: #C6A43F !important;
            color: #0A0A0A !important;
            transform: translateY(-3px) !important;
        }
        .je-footer-social a i {
            font-size: 18px !important;
            line-height: 1 !important;
            display: inline-block !important;
            color: inherit !important;
        }

        @media (max-width: 768px) {
            .calculator-hero h1 { font-size: 32px; }
            .progress-steps { gap: 30px; }
            .form-card { padding: 24px; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .appliances-header { display: none; }
            .appliance-row { grid-template-columns: 1fr; gap: 10px; }
            .btn-group { flex-direction: column; }
            .btn { justify-content: center; }
            .results-card { padding: 24px; }
            .results-header h2 { font-size: 24px; }
            .result-value { font-size: 20px; }
        }
        @media (max-width: 480px) {
            .calc-wrapper { padding: 20px 16px 60px; }
            .preset-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body class="solar-calculator-page">

<div class="calculator-hero">
    <div class="container" style="max-width:1400px; margin:0 auto; padding:0 24px;">
        <div class="hero-badge" style="display:inline-block; background:rgba(198,164,63,0.15); color:#C6A43F; font-size:12px; font-weight:600; letter-spacing:2px; text-transform:uppercase; padding:6px 16px; border-radius:40px; margin-bottom:20px;">
            <i class="fas fa-solar-panel"></i> KINAS VOLT
        </div>
        <h1 style="font-family:'Prata',serif; font-size:52px; font-weight:400; background:linear-gradient(135deg, #FFFFFF 0%, #C6A43F 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; margin-bottom:16px;">Solar Savings Calculator</h1>
        <p style="font-size:18px; color:rgba(255,255,255,0.7); max-width:600px; margin:0 auto; line-height:1.6;">Get an instant estimate of your solar needs and potential savings</p>
    </div>
</div>

<div class="calc-wrapper">
    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step-indicator active" data-step="1">
            <div class="step-number"><span>1</span></div>
            <div class="step-label">Your Info</div>
        </div>
        <div class="step-indicator" data-step="2">
            <div class="step-number"><span>2</span></div>
            <div class="step-label">Appliances</div>
        </div>
        <div class="step-indicator" data-step="3">
            <div class="step-number"><span>3</span></div>
            <div class="step-label">Backup Needs</div>
        </div>
    </div>

    <div id="errorMessage" class="error-message"></div>

    <form id="solarCalculatorForm">
        <?php
        // Generate a fresh CSRF token
        $csrfToken = Security::generateCSRFToken();
        ?>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- Step 1: Customer Info -->
        <div class="step active" id="step1">
            <div class="form-card">
                <h2><i class="fas fa-user"></i> Customer Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" name="full_name" placeholder="Enter your full name" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number *</label>
                        <input type="tel" name="phone" placeholder="0803 123 4567" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> City & State *</label>
                        <input type="text" name="city_state" placeholder="Lagos, Nigeria" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Property Type *</label>
                        <select name="property_type" required>
                            <option value="">Select property type</option>
                            <option value="Apartment">🏢 Apartment</option>
                            <option value="Duplex">🏠 Duplex</option>
                            <option value="Bungalow">🏡 Bungalow</option>
                            <option value="Office">💼 Office</option>
                            <option value="Commercial">🏭 Commercial Building</option>
                            <option value="Hotel">🏨 Hotel</option>
                            <option value="Other">📌 Other</option>
                        </select>
                    </div>
                </div>
                <div class="btn-group">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: Appliances -->
        <div class="step" id="step2">
            <div class="form-card">
                <h2><i class="fas fa-plug"></i> Your Appliances</h2>
                <p style="margin-bottom: 24px; color: var(--text-muted);">Add the appliances you want to power with solar</p>
                
                <div class="preset-grid" id="presetGrid"></div>
                
                <div class="appliances-header">
                    <div>Appliance</div>
                    <div>Quantity</div>
                    <div>Watts (W)</div>
                    <div></div>
                </div>
                <div id="appliance-list"></div>
                
                <button type="button" class="add-appliance-btn" onclick="addCustomAppliance()">
                    <i class="fas fa-plus"></i> Add Custom Appliance
                </button>
                
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Backup -->
        <div class="step" id="step3">
            <div class="form-card">
                <h2><i class="fas fa-battery-full"></i> Backup Requirements</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> Backup Hours Needed</label>
                        <select name="backup_hours">
                            <option value="8">🕐 8 Hours (Basic - Essential appliances only)</option>
                            <option value="12">🕑 12 Hours (Standard - Full daytime use)</option>
                            <option value="24" selected>🕒 24 Hours (Premium - Complete independence)</option>
                            <option value="48">🕓 48 Hours (Ultimate - Extended cloudy days)</option>
                        </select>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-calculator"></i> Calculate & Get Proposal
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p style="color: #C6A43F; font-weight: 600;">Calculating your solar solution...</p>
        <p style="color: rgba(255,255,255,0.5); font-size: 14px;">This may take a few moments</p>
    </div>

    <!-- Results -->
    <div id="results" class="results-section"></div>
</div>

<script>
// Appliance presets
const appliancePresets = [
    { name: "LED Bulb (10W)", watts: 10 },
    { name: "Ceiling Fan (70W)", watts: 70 },
    { name: "Refrigerator (150W)", watts: 150 },
    { name: "TV (100W)", watts: 100 },
    { name: "Laptop (50W)", watts: 50 },
    { name: "Air Conditioner (1HP)", watts: 900 },
    { name: "Air Conditioner (1.5HP)", watts: 1200 },
    { name: "Microwave (1000W)", watts: 1000 },
    { name: "Electric Kettle (1500W)", watts: 1500 },
    { name: "Washing Machine (500W)", watts: 500 },
    { name: "Water Pump (750W)", watts: 750 },
    { name: "Iron (1000W)", watts: 1000 }
];

let applianceCounter = 0;
let currentStep = 1;

function loadPresets() {
    const container = document.getElementById('presetGrid');
    container.innerHTML = appliancePresets.map(preset => `
        <div class="preset-chip" onclick="addPresetAppliance('${preset.name}', ${preset.watts})">
            ${preset.name}
        </div>
    `).join('');
}

function addPresetAppliance(name, watts) {
    addCustomAppliance(name, watts);
}

function addCustomAppliance(name = '', watts = '') {
    applianceCounter++;
    const container = document.getElementById('appliance-list');
    const row = document.createElement('div');
    row.className = 'appliance-row';
    row.id = `appliance-${applianceCounter}`;
    row.innerHTML = `
        <input type="text" name="appliance_name[]" placeholder="Appliance name" value="${escapeHtml(name)}">
        <input type="number" name="appliance_qty[]" placeholder="Qty" value="1" min="1">
        <input type="number" name="appliance_watts[]" placeholder="Watts" value="${watts}" min="1">
        <button type="button" class="remove-appliance" onclick="removeAppliance('appliance-${applianceCounter}')">
            <i class="fas fa-trash"></i> Delete
        </button>
    `;
    container.appendChild(row);
}

function removeAppliance(id) {
    const element = document.getElementById(id);
    if (element) element.remove();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function goToStep(step) {
    // Validate step 1
    if (step === 2 && currentStep === 1) {
        const form = document.getElementById('solarCalculatorForm');
        const inputs = form.querySelectorAll('#step1 input[required], #step1 select[required]');
        let valid = true;
        let errorMsg = '';
        inputs.forEach(input => {
            if (!input.value.trim()) {
                valid = false;
                errorMsg = 'Please fill in all required fields in Customer Information.';
                input.style.borderColor = '#dc3545';
            } else {
                input.style.borderColor = '';
            }
        });
        if (!valid) {
            showError(errorMsg);
            return;
        }
        hideError();
    }

    if (step === 3 && currentStep === 2) {
        const applianceNames = document.querySelectorAll('input[name="appliance_name[]"]');
        const applianceWatts = document.querySelectorAll('input[name="appliance_watts[]"]');
        let hasAppliance = false;
        for (let i = 0; i < applianceNames.length; i++) {
            if (applianceNames[i].value && applianceWatts[i].value) {
                hasAppliance = true;
                break;
            }
        }
        if (!hasAppliance) {
            showError('Please add at least one appliance.');
            return;
        }
        hideError();
    }

    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
    document.getElementById(`step${step}`).classList.add('active');
    
    document.querySelectorAll('.step-indicator').forEach((el, idx) => {
        const stepNum = idx + 1;
        el.classList.remove('active', 'completed');
        if (stepNum < step) el.classList.add('completed');
        else if (stepNum === step) el.classList.add('active');
    });
    
    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showError(msg) {
    const el = document.getElementById('errorMessage');
    el.textContent = msg;
    el.classList.add('show');
}

function hideError() {
    document.getElementById('errorMessage').classList.remove('show');
}

document.getElementById('submitBtn').addEventListener('click', async function() {
    const form = document.getElementById('solarCalculatorForm');
    
    // Validate all required fields
    const allRequired = form.querySelectorAll('input[required], select[required]');
    let valid = true;
    allRequired.forEach(input => {
        if (!input.value.trim()) {
            valid = false;
            input.style.borderColor = '#dc3545';
        } else {
            input.style.borderColor = '';
        }
    });
    
    if (!valid) {
        showError('Please fill in all required fields.');
        goToStep(1);
        return;
    }
    
    // Collect appliances
    const applianceNames = document.querySelectorAll('input[name="appliance_name[]"]');
    const applianceQtys = document.querySelectorAll('input[name="appliance_qty[]"]');
    const applianceWatts = document.querySelectorAll('input[name="appliance_watts[]"]');
    
    const appliances = [];
    for (let i = 0; i < applianceNames.length; i++) {
        if (applianceNames[i].value && applianceWatts[i].value) {
            appliances.push({
                name: applianceNames[i].value,
                quantity: parseInt(applianceQtys[i].value) || 1,
                watts: parseInt(applianceWatts[i].value)
            });
        }
    }
    
    if (appliances.length === 0) {
        showError('Please add at least one appliance.');
        goToStep(2);
        return;
    }
    
    // Build form data
    const formData = new FormData(form);
    formData.append('appliances', JSON.stringify(appliances));
    
    // Show loading
    const loading = document.getElementById('loadingOverlay');
    loading.classList.add('active');
    this.disabled = true;
    
    try {
        const response = await fetch('/api/solar/calculate.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        loading.classList.remove('active');
        this.disabled = false;
        
        if (result.success) {
            displayResults(result.data, result.reference, result.pdf_url);
        } else {
            showError(result.message || 'Something went wrong. Please try again.');
        }
    } catch (error) {
        loading.classList.remove('active');
        this.disabled = false;
        showError('Network error. Please check your connection and try again.');
        console.error('Error:', error);
    }
});

function displayResults(data, reference, pdfUrl) {
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = `
        <div class="results-card">
            <div class="results-header">
                <div class="check-icon"><i class="fas fa-check-circle"></i></div>
                <h2>Your Solar Solution is Ready!</h2>
                <p>Based on your inputs, here's what we recommend</p>
                <p style="font-size: 13px; margin-top: 8px; opacity: 0.7;">Reference: ${reference}</p>
            </div>
            <div class="results-grid">
                <div class="result-item">
                    <i class="fas fa-bolt"></i>
                    <div class="result-value">${data.system_size || '--'} kW</div>
                    <div class="result-label">Recommended System Size</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-solar-panel"></i>
                    <div class="result-value">${data.panels || '--'}</div>
                    <div class="result-label">Solar Panels Needed</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-battery-full"></i>
                    <div class="result-value">${data.battery_capacity || '--'} kWh</div>
                    <div class="result-label">Battery Storage</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="result-value">₦${(data.estimated_cost || 0).toLocaleString()}</div>
                    <div class="result-label">Estimated Investment</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-chart-line"></i>
                    <div class="result-value">₦${(data.monthly_savings || 0).toLocaleString()}</div>
                    <div class="result-label">Monthly Savings</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-leaf"></i>
                    <div class="result-value">${data.co2_saved || '--'}</div>
                    <div class="result-label">CO₂ Saved (tons/year)</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="result-value">${data.payback_years || '--'} years</div>
                    <div class="result-label">Payback Period</div>
                </div>
                <div class="result-item">
                    <i class="fas fa-trophy"></i>
                    <div class="result-value">${data.roi || '--'}%</div>
                    <div class="result-label">ROI (20 years)</div>
                </div>
            </div>
            <div class="proposal-buttons">
                ${pdfUrl ? `<a href="${pdfUrl}" target="_blank" class="btn btn-dark"><i class="fas fa-file-pdf"></i> View PDF Proposal</a>` : ''}
                <button class="btn btn-outline-dark" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Start Over
                </button>
            </div>
            <p style="text-align: center; margin-top: 20px; font-size: 13px; opacity: 0.7;">
                <i class="fas fa-envelope"></i> A detailed proposal has been sent to your email.
            </p>
        </div>
    `;
    resultsDiv.classList.add('active');
    resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadPresets();
    addCustomAppliance('Refrigerator', 150);
    addCustomAppliance('LED Bulbs', 10);
    addCustomAppliance('Ceiling Fan', 70);
});

// Input validation on blur
document.querySelectorAll('input[required], select[required]').forEach(el => {
    el.addEventListener('blur', function() {
        if (this.value.trim()) {
            this.style.borderColor = '';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
