<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// STATIC PREVIEW FILE - Error State
// Access at: https://kinasgroup.com/auth/verify-email-error-preview.php

$pageTitle = 'Email Verification Error - KINAS GROUP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <title>Email Verification Error - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; padding: 20px; }
        .verification-container { max-width: 500px; width: 100%; background: #FFFFFF; border-radius: 20px; padding: 50px 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); text-align: center; }
        .logo-placeholder { width: 100%; max-width: 280px; height: 80px; margin: 0 auto 30px; background: linear-gradient(135deg, #C6A43F 0%, #A8882E 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .logo-placeholder svg { width: 60%; height: auto; }
        .email-icon { font-size: 64px; color: #DC2626; margin-bottom: 25px; display: inline-block; }
        .verification-title { font-family: 'Prata', serif; font-size: 32px; color: #0A0A0A; margin-bottom: 16px; }
        .message-text { color: #DC2626; font-size: 16px; line-height: 1.6; margin-bottom: 24px; padding: 12px 16px; background: #FEF2F2; border-radius: 10px; display: inline-block; width: 100%; }
        .info-text { color: #666666; font-size: 14px; line-height: 1.6; margin-bottom: 30px; }
        .btn { display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 14px 32px; border-radius: 40px; text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s; margin-top: 10px; }
        .btn:hover { background: #A8882E; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #C6A43F; color: #C6A43F; }
        .btn-outline:hover { background: #C6A43F; color: #0A0A0A; }
        .divider { display: flex; align-items: center; gap: 15px; margin: 30px 0 20px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #E0E0E0; }
        .divider span { color: #999; font-size: 12px; }
        .preview-notice { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.8); color: white; padding: 8px 16px; border-radius: 40px; font-size: 12px; z-index: 1000; }
        .preview-notice a { color: #C6A43F; text-decoration: none; }
        @media (max-width: 520px) { .verification-container { padding: 35px 25px; } .logo-placeholder { max-width: 240px; height: 70px; } .verification-title { font-size: 28px; } .email-icon { font-size: 48px; } }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="logo-placeholder">
            <svg viewBox="0 0 200 50" xmlns="http://www.w3.org/2000/svg">
                <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Prata, serif" font-size="22" font-weight="600" fill="#0A0A0A">KINAS GROUP</text>
            </svg>
        </div>

        <div class="email-icon">
            <i class="fas fa-envelope-open-text"></i>
        </div>

        <h1 class="verification-title">Verification Failed</h1>
        <div class="message-text">
            <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
            Invalid or expired verification code.
        </div>
        <p class="info-text">
            The verification link may be invalid or has expired. 
            Please try registering again or contact support for assistance.
        </p>
        <a href="#" class="btn">
            <i class="fas fa-user-plus"></i> Register Again
        </a>
        
        <div class="divider">
            <span>or</span>
        </div>
        
        <a href="#" class="btn-outline btn">
            <i class="fas fa-sign-in-alt"></i> Try Logging In
        </a>
    </div>

    <div class="preview-notice">
        ⚡ ERROR STATE PREVIEW | 
        <a href="verify-email-preview.php">View Success State</a>
    </div>
</body>
</html>
