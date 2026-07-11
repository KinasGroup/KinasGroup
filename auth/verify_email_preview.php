<?php
// STATIC PREVIEW FILE - Delete after design approval
// This file shows what the verification page looks like
// Access at: https://kinasgroup.com/auth/verify-email-preview.php

$pageTitle = 'Email Verification Preview - KINAS GROUP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="only light">
    <title>Email Verification Preview - KINAS GROUP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }

        .verification-container {
            max-width: 500px;
            width: 100%;
            background: #FFFFFF;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        /* Gold Rounded Rectangle Logo - Same as auth/login.php */
        .logo-placeholder {
            width: 100%;
            max-width: 280px;
            height: 80px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #C6A43F 0%, #A8882E 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-placeholder svg {
            width: 60%;
            height: auto;
        }

        /* Email Icon */
        .email-icon {
            font-size: 64px;
            color: #C6A43F;
            margin-bottom: 25px;
            display: inline-block;
        }

        .verification-title {
            font-family: 'Prata', serif;
            font-size: 32px;
            color: #0A0A0A;
            margin-bottom: 16px;
        }

        .message-text {
            color: #10B981;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            padding: 12px 16px;
            background: #ECFDF5;
            border-radius: 10px;
            display: inline-block;
            width: 100%;
        }

        .error-text {
            color: #DC2626;
            background: #FEF2F2;
        }

        .info-text {
            color: #666666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            background: #C6A43F;
            color: #0A0A0A;
            padding: 14px 32px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn:hover {
            background: #A8882E;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #C6A43F;
            color: #C6A43F;
        }

        .btn-outline:hover {
            background: #C6A43F;
            color: #0A0A0A;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 30px 0 20px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E0E0E0;
        }
        .divider span {
            color: #999;
            font-size: 12px;
        }

        /* Preview Notice */
        .preview-notice {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 12px;
            z-index: 1000;
        }

        .preview-notice a {
            color: #C6A43F;
            text-decoration: none;
        }

        @media (max-width: 520px) {
            .verification-container {
                padding: 35px 25px;
            }
            .logo-placeholder {
                max-width: 240px;
                height: 70px;
            }
            .verification-title {
                font-size: 28px;
            }
            .email-icon {
                font-size: 48px;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <!-- Gold Rounded Rectangle Logo -->
        <div class="logo-placeholder">
            <svg viewBox="0 0 200 50" xmlns="http://www.w3.org/2000/svg">
                <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Prata, serif" font-size="22" font-weight="600" fill="#0A0A0A">KINAS GROUP</text>
            </svg>
        </div>

        <!-- Email Icon -->
        <div class="email-icon">
            <i class="fas fa-envelope-open-text"></i>
        </div>

        <!-- SUCCESS STATE (what users see when verification succeeds) -->
        <h1 class="verification-title">Email Verified!</h1>
        <div class="message-text">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
            Email verified successfully! You can now login to your account.
        </div>
        <p class="info-text">
            Hello <strong>John Doe</strong>, your account is now active. 
            You can start exploring luxury cars, homes, and exclusive items on KINAS GROUP.
        </p>
        <a href="#" class="btn">
            <i class="fas fa-sign-in-alt"></i> Login Now
        </a>
        
        <div class="divider">
            <span>or</span>
        </div>
        
        <a href="#" class="btn-outline btn">
            <i class="fas fa-home"></i> Return to Homepage
        </a>
    </div>

    <!-- Preview Notice -->
    <div class="preview-notice">
        ⚡ PREVIEW MODE | 
        <a href="verify-email.php?code=test">View Real Dynamic Page</a> | 
        This is a static preview
    </div>
</body>
</html>
