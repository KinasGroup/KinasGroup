<?php
// public/webmail/index.php - Branded Landing Page
require_once __DIR__ . '/../includes/session.php'; // if you want to show logged-in state
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KINAS GROUP Mail</title>
    <link rel="stylesheet" href="/assets/css/style.css"> <!-- your existing styles -->
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            color: #fff;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mail-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(198,164,63,0.2);
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .logo {
            font-family: 'Prata', serif;
            font-size: 42px;
            color: #C6A43F;
            margin-bottom: 8px;
        }
        .tagline { color: #aaa; margin-bottom: 30px; font-size: 15px; }
        .btn-primary {
            display: inline-block;
            background: #C6A43F;
            color: #0A0A0A;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(198,164,63,0.4);
        }
    </style>
</head>
<body>
    <div class="mail-card">
        <div class="logo">KINAS GROUP</div>
        <p class="tagline">Professional Email • Secure & Reliable</p>
        
        <a href="https://mail.kinas-group.com" class="btn-primary">
            Open Webmail →
        </a>
        
        <p style="margin-top: 30px; font-size: 13px; color: #777;">
            Powered by Roundcube + Resend
        </p>
    </div>
</body>
</html>
