<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';

$page_title = 'Solar Savings Calculator - Kinas Volt';
$headerDepth = '../../';
$isHeroPage = false; // Calculator page doesn't need transparent header

require_once __DIR__ . '/../../templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================
           SOLAR CALCULATOR - KINAS VOLT LUXURY DESIGN
           ============================================ */
        
        :root {
            --gold: #C6A43F;
            --gold-dark: #A8882E;
            --gold-light: #D4B96A;
            --gold-soft: rgba(198,164,63,0.12);
            --dark: #0A0A0A;
            --dark-card: #111111;
            --dark-surface: #1A1A1A;
            --dark-border: #2A2A2A;
            --white: #FFFFFF;
            --gray: #B0B0B0;
            --gray-dark: #666666;
            --success: #2E7D32;
            --success-light: #E8F5E9;
            --error: #C62828;
            --warning: #F57C00;
            --info: #1565C0;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-md: 0 8px 24px rgba(0,0,0,0.1);
            --shadow-lg: 0 16px 48px rgba(0,0,0,0.15);
            --shadow-gold: 0 8px 24px rgba(198,164,63,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #0F0F1A 100%);
            color: var(--white);
            overflow-x: hidden;
        }

        /* Hero Section */
        .calculator-hero {
            position: relative;
            padding: 100px 0 60px;
            text-align: center;
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
            opacity: 0.08;
            pointer-events: none;
        }

        .calculator-hero .container {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(198,164,63,0.15);
            color: var(--gold);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 6px 16px;
            border-radius: 40px;
            margin-bottom: 20px;
        }

        .hero-title {
            font-family: 'Prata', serif;
            font-size: 52px;
            font-weight: 400;
            background: linear-gradient(135deg, var(--white) 0%, var(--gold) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        .hero-subtitle {
            font-size: 18px;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Main Container */
        .calc-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px 80px;
        }

        /* Progress Steps */
        .progress-wrapper {
            max-width: 700px;
            margin: 0 auto 50px;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 30px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: var(--dark-border);
            z-index: 0;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-circle {
            width: 44px;
            height: 44px;
            background: var(--dark-surface);
            border: 2px solid var(--dark-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-weight: 700;
            font-size: 18px;
            transition: var(--transition);
        }

        .step.active .step-circle {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--dark);
            box-shadow: var(--shadow-gold);
        }

        .step.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step.completed .step-circle::before {
            content: '✓';
            font-size: 20px;
        }

        .step.completed .step-circle span {
            display: none;
        }

        .step-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            letter-spacing: 0.5px;
        }

        .step.active .step-label {
            color: var(--gold);
        }

        /* Form Cards */
        .form-card {
            background: var(--dark-card);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid var(--dark-border);
            transition: var(--transition);
        }

        .form-card:hover {
            border-color: var(--gold);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--dark-border);
        }

        .card-header h2 {
            font-family: 'Prata', serif;
            font-size: 28px;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h2 i {
            color: var(--gold);
            font-size: 28px;
        }

        .card-header p {
            color: var(--gray);
            margin-top: 8px;
            font-size: 14px;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray);
        }

        .form-group label i {
            color: var(--gold);
            margin-right: 8px;
            width: 16px;
        }

        .form-group input,
        .form-group select {
            padding: 14px 18px;
            background: var(--dark-surface);
            border: 1px solid var(--dark-border);
            border-radius: 12px;
            color: var(--white);
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(198,164,63,0.1);
        }

        .form-group input::placeholder {
            color: var(--gray-dark);
        }

        /* Appliances Section */
        .appliances-section {
            margin-bottom: 32px;
        }

        .appliances-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 50px;
            gap: 16px;
            padding: 12px 16px;
            background: rgba(198,164,63,0.08);
            border-radius: 12px;
            margin-bottom: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gold);
        }

        .appliance-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 50px;
            gap: 16px;
            padding: 14px 16px;
            background: var(--dark-surface);
            border: 1px solid var(--dark-border);
            border-radius: 12px;
            margin-bottom: 10px;
            transition: var(--transition);
        }

        .appliance-row:hover {
            border-color: var(--gold);
        }

        .appliance-row input,
        .appliance-row select {
            padding: 10px 14px;
            background: var(--dark);
            border: 1px solid var(--dark-border);
            border-radius: 8px;
            color: var(--white);
            font-size: 14px;
        }

        .remove-appliance {
            background: rgba(198,40,40,0.15);
            border: none;
            border-radius: 8px;
            color: var(--error);
            cursor: pointer;
            transition: var(--transition);
            font-size: 18px;
        }

        .remove-appliance:hover {
            background: rgba(198,40,40,0.3);
        }

        .add-appliance-btn {
            background: transparent;
            border: 2px dashed var(--dark-border);
            border-radius: 12px;
            padding: 16px;
            width: 100%;
            color: var(--gray);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 16px;
        }

        .add-appliance-btn:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Preset Appliances */
        .preset-appliances {
            margin-bottom: 24px;
        }

        .preset-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .preset-chip {
            background: var(--dark-surface);
            border: 1px solid var(--dark-border);
            border-radius: 40px;
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .preset-chip:hover {
            border-color: var(--gold);
            background: rgba(198,164,63,0.1);
            color: var(--gold);
        }

        /* Button Group */
        .btn-group {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--dark-border);
        }

        .btn {
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: var(--gold);
            color: var(--dark);
        }

        .btn-primary:hover {
            background: var(--gold-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-gold);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--dark-border);
            color: var(--gray);
        }

        .btn-secondary:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Results Section */
        .results-section {
            margin-top: 40px;
            display: none;
        }

        .results-section.active {
            display: block;
            animation: fadeInUp 0.6s ease;
        }

        .results-card {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            border-radius: 32px;
            padding: 48px;
            color: var(--dark);
        }

        .results-header {
            text-align: center;
            margin-bottom: 40px;
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
            margin-bottom: 20px;
        }

        .results-header h2 {
            font-family: 'Prata', serif;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .results-header p {
            font-size: 16px;
            opacity: 0.8;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .result-item {
            background: rgba(0,0,0,0.08);
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .result-item i {
            font-size: 36px;
            margin-bottom: 12px;
        }

        .result-value {
            font-size: 32px;
            font-weight: 800;
            margin: 8px 0;
        }

        .result-label {
            font-size: 13px;
            opacity: 0.7;
        }

        .proposal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-dark {
            background: var(--dark);
            color: white;
        }

        .btn-dark:hover {
            background: #1A1A1A;
            transform: translateY(-2px);
        }

        .btn-outline-dark {
            background: transparent;
            border: 2px solid var(--dark);
            color: var(--dark);
        }

        .btn-outline-dark:hover {
            background: var(--dark);
            color: white;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
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
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .step {
            animation: fadeInUp 0.5s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 36px;
            }
            
            .hero-subtitle {
                font-size: 15px;
            }
            
            .form-card {
                padding: 24px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .appliances-header {
                display: none;
            }
            
            .appliance-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
            
            .results-card {
                padding: 24px;
            }
            
            .results-header h2 {
                font-size: 24px;
            }
            
            .result-value {
                font-size: 24px;
            }
            
            .progress-steps {
                margin-bottom: 20px;
            }
            
            .step-circle {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .step-label {
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .calc-container {
                padding: 0 16px 60px;
            }
            
            .preset-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="calculator-hero">
    <div class="container">
        <div class="hero-badge">
            <i class="fas fa-solar-panel"></i> KINAS VOLT
        </div>
        <h1 class="hero-title">Solar Savings Calculator</h1>
        <p class="hero-subtitle">Get an instant estimate of your solar needs and potential savings</p>
    </div>
</div>

<div class="calc-container">
    <!-- Progress Steps -->
    <div class="progress-wrapper">
        <div class="progress-steps">
            <div class="step active" data-step="1">
                <div class="step-circle"><span>1</span></div>
                <div class="step-label">Your Info</div>
            </div>
            <div class="step" data-step="2">
                <div class="step-circle"><span>2</span></div>
                <div class="step-label">Appliances</div>
            </div>
            <div class="step" data-step="3">
                <div class="step-circle"><span>3</span></div>
                <div class="step-label">Backup</div>
            </div>
        </div>
    </div>

    <form id="solarCalculatorForm">
        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">

        <!-- Step 1 -->
        <div class="step-content" id="step1">
            <div class="form-card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> Customer Information</h2>
                    <p>Tell us about yourself to get started</p>
                </div>
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
                    <div class="form-group full-width">
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

        <!-- Step 2 -->
        <div class="step-content" id="step2" style="display: none;">
            <div class="form-card">
                <div class="card-header">
                    <h2><i class="fas fa-plug"></i> Your Appliances</h2>
                    <p>Add the appliances you want to power with solar</p>
                </div>
                
                <div class="preset-appliances">
                    <div class="preset-title">
                        <i class="fas fa-bolt"></i> Quick Add Common Appliances
                    </div>
                    <div class="preset-grid" id="presetGrid"></div>
                </div>

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

        <!-- Step 3 -->
        <div class="step-content" id="step3" style="display: none;">
            <div class="form-card">
                <div class="card-header">
                    <h2><i class="fas fa-battery-full"></i> Backup Requirements</h2>
                    <p>How many hours of backup do you need?</p>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
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
                    <button type="button" class="btn btn-primary" onclick="submitForm()">
                        <i class="fas fa-calculator"></i> Calculate & Get Proposal
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Results -->
    <div id="results" class="results-section"></div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <p style="color: var(--gold); font-weight: 600;">Calculating your solar solution...</p>
</div>

<script>
// Appliance presets
const appliancePresets = [
    { name: "LED Bulb (10W)", watts: 10, icon: "💡" },
    { name: "Ceiling Fan (70W)", watts: 70, icon: "🌀" },
    { name: "Refrigerator (150W)", watts: 150, icon: "❄️" },
    { name: "TV (100W)", watts: 100, icon: "📺" },
    { name: "Laptop (50W)", watts: 50, icon: "💻" },
    { name: "Air Conditioner (1HP)", watts: 900, icon: "❄️" },
    { name: "Air Conditioner (1.5HP)", watts: 1200, icon: "❄️" },
    { name: "Microwave (1000W)", watts: 1000, icon: "🍿" },
    { name: "Electric Kettle (1500W)", watts: 1500, icon: "🍵" },
    { name: "Washing Machine (500W)", watts: 500, icon: "🧺" },
    { name: "Water Pump (750W)", watts: 750, icon: "💧" },
    { name: "Iron (1000W)", watts: 1000, icon: "👔" }
];

let applianceCounter = 0;
let currentStep = 1;

// Load preset buttons
function loadPresets() {
    const container = document.getElementById('presetGrid');
    container.innerHTML = appliancePresets.map(preset => `
        <div class="preset-chip" onclick="addPresetAppliance('${preset.name}', ${preset.watts})">
            ${preset.icon} ${preset.name}
        </div>
    `).join('');
}

// Add preset appliance
function addPresetAppliance(name, watts) {
    addCustomAppliance(name, watts);
}

// Add custom appliance
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
            <i class="fas fa-trash"></i>
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
    // Hide all steps
    document.querySelectorAll('.step-content').forEach(el => {
        el.style.display = 'none';
    });
    document.getElementById(`step${step}`).style.display = 'block';
    
    // Update progress steps
    document.querySelectorAll('.step').forEach((el, index) => {
        const stepNum = index + 1;
        el.classList.remove('active', 'completed');
        if (stepNum < step) {
            el.classList.add('completed');
        } else if (stepNum === step) {
            el.classList.add('active');
        }
    });
    
    currentStep = step;
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function submitForm() {
    const form = document.getElementById('solarCalculatorForm');
    const formData = new FormData(form);
    
    // Validate step 1
    const step1Inputs = document.querySelectorAll('#step1 input[required], #step1 select[required]');
    let valid = true;
    step1Inputs.forEach(input => {
        if (!input.value.trim()) valid = false;
    });
    
    if (!valid) {
        alert('Please fill in all required fields in Step 1');
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
        alert('Please add at least one appliance');
        goToStep(2);
        return;
    }
    
    formData.append('appliances', JSON.stringify(appliances));
    
    // Show loading
    const loading = document.getElementById('loadingOverlay');
    loading.classList.add('active');
    
    try {
        const response = await fetch('/api/solar/calculate.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        loading.classList.remove('active');
        
        if (result.success) {
            displayResults(result.data);
        } else {
            alert('Error: ' + (result.message || 'Something went wrong'));
        }
    } catch (error) {
        loading.classList.remove('active');
        alert('Error submitting form: ' + error.message);
    }
}

function displayResults(data) {
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = `
        <div class="results-card">
            <div class="results-header">
                <div class="check-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Your Solar Solution is Ready!</h2>
                <p>Based on your inputs, here's what we recommend</p>
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
                    <div class="result-value">${data.co2_saved || '--'} tons/year</div>
                    <div class="result-label">CO₂ Saved Annually</div>
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
                <button class="btn btn-dark" onclick="window.print()">
                    <i class="fas fa-print"></i> Print / Save PDF
                </button>
                <button class="btn btn-outline-dark" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Start Over
                </button>
            </div>
        </div>
    `;
    resultsDiv.classList.add('active');
    
    // Scroll to results
    resultsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadPresets();
    addCustomAppliance('Refrigerator', 150);
    addCustomAppliance('LED Bulbs', 10);
    addCustomAppliance('Ceiling Fan', 70);
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
