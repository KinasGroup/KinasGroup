<?php
// calculator.php - Solar Savings Calculator
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';

$page_title = 'Solar Savings Calculator - Kinas Volt';
$headerDepth = '../../';

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

        /* FIX: DROPDOWN SELECT VISIBILITY */
        select,
        .form-group select,
        #solarCalculatorForm select {
            background: rgba(255,255,255,0.08) !important;
            color: #FFFFFF !important;
            border: 1px solid rgba(255,255,255,0.15) !important;
            padding: 14px 16px !important;
            border-radius: 8px !important;
            font-family: 'Inter', sans-serif !important;
            font-size: 16px !important;
            width: 100% !important;
            appearance: auto !important;
            -webkit-appearance: auto !important;
            cursor: pointer !important;
        }

        select option,
        .form-group select option,
        #solarCalculatorForm select option {
            background: #1a1a2e !important;
            color: #FFFFFF !important;
            padding: 12px 16px !important;
            font-size: 15px !important;
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        }

        select,
        .form-group select,
        #solarCalculatorForm select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23C6A43F' d='M6 8L1 3h10z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 16px center !important;
            padding-right: 40px !important;
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

        /* ============================================================
           RESULTS DISPLAY FIX - FORCE VISIBILITY AND CENTER
           ============================================================ */

        /* Force results section to display and center */
        #results {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            min-height: 100px !important;
            max-width: 1200px !important;
            margin: 0 auto !important;
            width: 100% !important;
        }

        #results .results-section {
            display: none !important;
            margin-top: 40px !important;
            width: 100% !important;
        }
        #results .results-section.active {
            display: block !important;
        }

        #results .results-card {
            background: linear-gradient(135deg, #C6A43F 0%, #A8882E 100%) !important;
            border-radius: 12px !important;
            padding: 40px !important;
            color: #0A0A0A !important;
            display: block !important;
            width: 100% !important;
            margin: 0 auto !important;
            box-sizing: border-box !important;
        }

        #results .results-header {
            text-align: center !important;
            margin-bottom: 32px !important;
        }

        #results .results-header .check-icon {
            width: 64px !important;
            height: 64px !important;
            background: rgba(0,0,0,0.1) !important;
            border-radius: 50% !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 32px !important;
            margin-bottom: 16px !important;
        }

        #results .results-header h2 {
            font-family: 'Prata', serif !important;
            font-size: 32px !important;
            color: #0A0A0A !important;
            margin-bottom: 8px !important;
        }

        #results .results-header p {
            color: rgba(0,0,0,0.7) !important;
        }

        #results .results-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
            gap: 16px !important;
            margin-bottom: 32px !important;
            width: 100% !important;
        }

        #results .result-item {
            background: rgba(0,0,0,0.08) !important;
            border-radius: 12px !important;
            padding: 20px !important;
            text-align: center !important;
            color: #0A0A0A !important;
            display: block !important;
        }

        #results .result-item i {
            font-size: 28px !important;
            color: #0A0A0A !important;
            display: inline-block !important;
            margin-bottom: 8px !important;
        }

        #results .result-value {
            font-size: 24px !important;
            font-weight: 800 !important;
            color: #0A0A0A !important;
            display: block !important;
            margin: 4px 0 !important;
        }

        #results .result-label {
            font-size: 12px !important;
            opacity: 0.7 !important;
            color: #0A0A0A !important;
            display: block !important;
        }

        #results .proposal-buttons {
            display: flex !important;
            gap: 16px !important;
            justify-content: center !important;
            flex-wrap: wrap !important;
            width: 100% !important;
        }

        #results .proposal-buttons .btn-dark {
            background: #0A0A0A !important;
            color: #FFFFFF !important;
            padding: 14px 32px !important;
            border-radius: 40px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            border: none !important;
            cursor: pointer !important;
        }

        #results .proposal-buttons .btn-outline-dark {
            background: transparent !important;
            border: 2px solid #0A0A0A !important;
            color: #0A0A0A !important;
            padding: 12px 32px !important;
            border-radius: 40px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
        }

        /* Force visibility for all result elements */
        #results .results-card * {
            visibility: visible !important;
            opacity: 1 !important;
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

        /* Footer Social Icons Fix */
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

        /* Override dark mode for selects */
        @media (prefers-color-scheme: dark) {
            select,
            .form-group select,
            #solarCalculatorForm select {
                background: rgba(255,255,255,0.08) !important;
                color: #FFFFFF !important;
                border-color: rgba(255,255,255,0.15) !important;
            }
            
            select option,
            .form-group select option,
            #solarCalculatorForm select option {
                background: #1a1a2e !important;
                color: #FFFFFF !important;
            }
        }

        @media (max-width: 768px) {
            .calculator-hero h1 { font-size: 32px; }
            .progress-steps { gap: 30px; }
            .form-card { padding: 24px; }
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .appliances-header { display: none; }
            .appliance-row { grid-template-columns:
