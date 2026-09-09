<?php
// calculator.php — DUAL-OPTION rebuild (Option B: show both options equally)
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../api/config/database.php';
$page_title = 'Solar Savings Calculator - Kinas Volt';
require_once __DIR__ . '/../../templates/header.php';
?>
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
position: absolute; inset: 0;
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
.calc-wrapper { max-width: 1400px; margin: 0 auto; padding: 40px 24px 80px; }
/* Progress Steps */
.progress-steps { display: flex; justify-content: center; gap: 80px; margin-bottom: 60px; position: relative; }
.progress-steps::before {
content: '';
position: absolute; top: 24px; left: 15%; right: 15%; height: 2px;
background: rgba(255,255,255,0.1); z-index: 0;
}
.step-indicator { text-align: center; position: relative; z-index: 1; cursor: pointer; transition: var(--transition); }
.step-number {
width: 48px; height: 48px;
background: rgba(255,255,255,0.1);
border: 2px solid rgba(255,255,255,0.2);
