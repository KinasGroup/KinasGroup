<?php
/**
 * KINAS GROUP — Email Service
 * Handles sending branded emails using Resend API
 */

class EmailService
{
    private $apiKey;
    private $fromEmail;
    private $fromName;
    private $siteUrl;
    private $useResend = false;
    private $mailer;
    private $usePHPMailer = false;
    
    public function __construct()
    {
        $this->fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@kinas-group.com';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'KINAS GROUP OF COMPANIES LIMITED';
        $this->siteUrl = getenv('SITE_URL') ?: 'https://kinas-group.com';
        
        // Check for Resend API key first
        $this->apiKey = getenv('RESEND_API_KEY') ?: '';
        if (!empty($this->apiKey)) {
            $this->useResend = true;
        }
        
        // Check if PHPMailer is available (fallback)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->usePHPMailer = true;
            $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $this->setupPHPMailer();
        }
    }
    
    private function setupPHPMailer()
    {
        $smtpHost = getenv('SMTP_HOST') ?: '';
        $smtpPort = getenv('SMTP_PORT') ?: 587;
        $smtpUsername = getenv('SMTP_USERNAME') ?: '';
        $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
        $smtpEncryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        
        if (!empty($smtpHost) && !empty($smtpUsername) && !empty($smtpPassword)) {
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtpHost;
            $this->mailer->Port = $smtpPort;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $smtpUsername;
            $this->mailer->Password = $smtpPassword;
            $this->mailer->SMTPSecure = $smtpEncryption;
        } else {
            $this->mailer->isMail();
        }
        
        $this->mailer->setFrom($this->fromEmail, $this->fromName);
        $this->mailer->isHTML(true);
    }
    
    /**
     * Send email using Resend API or fallback
     */
    public function send($to, $name, $subject, $htmlBody, $plainText = '')
    {
        // Try Resend first
        if ($this->useResend) {
            $result = $this->sendViaResend($to, $name, $subject, $htmlBody, $plainText);
            if ($result) {
                return true;
            }
        }
        
        // Fallback to PHPMailer
        return $this->sendViaPHPMailer($to, $name, $subject, $htmlBody, $plainText);
    }
    
    /**
     * Send email via Resend API
     */
    private function sendViaResend($to, $name, $subject, $htmlBody, $plainText = '')
    {
        $payload = [
            'from' => $this->fromName . ' <' . $this->fromEmail . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $plainText ?: strip_tags($htmlBody)
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('Resend cURL error: ' . $error);
            return false;
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('Resend API error: ' . $response . ' (HTTP ' . $httpCode . ')');
            return false;
        }
        
        $decoded = json_decode($response, true);
        if (isset($decoded['id'])) {
            error_log('Resend email sent: ' . $decoded['id'] . ' to ' . $to);
            return true;
        }
        
        error_log('Resend email failed: ' . $response);
        return false;
    }
    
    /**
     * Send email via PHPMailer (fallback)
     */
    private function sendViaPHPMailer($to, $name, $subject, $htmlBody, $plainText = '')
    {
        if (!$this->usePHPMailer) {
            error_log('PHPMailer not available');
            return false;
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = $plainText ?: strip_tags($htmlBody);
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send branded verification email
     */
    public function sendVerificationEmail($email, $name, $verificationCode)
    {
        $verificationLink = $this->siteUrl . '/auth/verify-email.php?code=' . $verificationCode;
        $year = date('Y');
        
        $subject = 'Verify Your Email - KINAS GROUP';
        
        $htmlBody = $this->buildVerificationEmailHTML($name, $verificationLink, $year);
        $plainText = $this->buildVerificationEmailPlain($name, $verificationLink);
        
        return $this->send($email, $name, $subject, $htmlBody, $plainText);
    }
    
    /**
     * PUBLIC method to preview email - returns the HTML
     */
    public function getVerificationEmailHTML($name = 'John Doe', $verificationLink = null)
    {
        if ($verificationLink === null) {
            $verificationLink = $this->siteUrl . '/auth/verify-email.php?code=preview-123456';
        }
        $year = date('Y');
        return $this->buildVerificationEmailHTML($name, $verificationLink, $year);
    }
    
    /**
     * PUBLIC method to get plain text version for preview
     */
    public function getVerificationEmailPlain($name = 'John Doe', $verificationLink = null)
    {
        if ($verificationLink === null) {
            $verificationLink = $this->siteUrl . '/auth/verify-email.php?code=preview-123456';
        }
        return $this->buildVerificationEmailPlain($name, $verificationLink);
    }
    
    /**
     * Build branded HTML email
     */
    private function buildVerificationEmailHTML($name, $verificationLink, $year)
    {
        return <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Verify Your Email - KINAS GROUP</title>
    <style type="text/css">
        body, table, td, a { 
            -webkit-text-size-adjust: 100%; 
            -ms-text-size-adjust: 100%; 
            margin: 0; 
            padding: 0; 
        }
        table, td { 
            mso-table-lspace: 0pt; 
            mso-table-rspace: 0pt; 
        }
        img { 
            -ms-interpolation-mode: bicubic; 
            border: 0; 
            height: auto; 
            line-height: 100%; 
            outline: none; 
            text-decoration: none; 
        }
        * { 
            font-family: 'Inter', Arial, Helvetica, sans-serif; 
        }
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; padding: 0 !important; }
            .inner-padding { padding: 30px 20px !important; }
            .btn { display: block !important; width: 100% !important; text-align: center !important; }
            .logo-text { font-size: 20px !important; }
            .hero-title { font-size: 22px !important; }
            .hero-sub { font-size: 14px !important; }
        }
        @media only screen and (max-width: 400px) {
            .inner-padding { padding: 20px 15px !important; }
            .hero-title { font-size: 18px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#F5F3F0; font-family:'Inter', Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F5F3F0" style="background-color:#F5F3F0; padding:20px 0;">
    <tr>
        <td align="center" valign="top">
            <table width="600" cellpadding="0" cellspacing="0" border="0" class="container" style="max-width:600px; width:100%; background-color:#FFFFFF; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.06);">
                
                <!-- HEADER -->
                <tr>
                    <td bgcolor="#0A0A0A" style="background-color:#0A0A0A; padding:28px 40px 20px; text-align:center;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="color:#FFFFFF;">
                                    <div style="font-family:'Prata', Georgia, serif; font-size:28px; color:#C6A43F; letter-spacing:1px; font-weight:400; margin-bottom:4px;">
                                        KINAS GROUP
                                    </div>
                                    <div style="font-size:10px; letter-spacing:4px; text-transform:uppercase; color:rgba(255,255,255,0.4); font-weight:300;">
                                        Of Companies Limited
                                    </div>
                                    <div style="width:60px; height:2px; background:#C6A43F; margin:10px auto 0;"></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- HERO -->
                <tr>
                    <td bgcolor="#0A0A0A" style="background-color:#0A0A0A; padding:0 40px 30px; text-align:center;">
                        <h1 style="font-family:'Prata', Georgia, serif; font-size:26px; font-weight:400; color:#FFFFFF; margin:0 0 8px 0; line-height:1.3;">
                            Welcome to KINAS GROUP
                        </h1>
                        <p style="font-size:14px; color:rgba(255,255,255,0.6); margin:0; line-height:1.6;">
                            The World's Luxury Marketplace
                        </p>
                    </td>
                </tr>

                <!-- BODY -->
                <tr>
                    <td class="inner-padding" style="padding:40px 40px 30px;">
                        
                        <p style="font-size:16px; color:#0A0A0A; margin:0 0 6px 0; font-weight:600;">
                            Hello, {$name}!
                        </p>
                        <p style="font-size:14px; color:#555555; margin:0 0 20px 0; line-height:1.7;">
                            Thank you for joining <strong style="color:#0A0A0A;">KINAS GROUP OF COMPANIES LIMITED</strong>. 
                            We're excited to have you as part of our luxury marketplace ecosystem.
                        </p>
                        
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr><td style="padding:10px 0 18px 0;"><hr style="border:0; border-top:1px solid #E8E5E0;"></td></tr>
                        </table>

                        <p style="font-size:14px; color:#0A0A0A; margin:0 0 16px 0; font-weight:600;">
                            Please verify your email address to complete your registration.
                        </p>
                        <p style="font-size:14px; color:#555555; margin:0 0 24px 0; line-height:1.6;">
                            Click the button below to verify your email and start exploring our luxury portfolio.
                        </p>

                        <!-- Button -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding:4px 0 20px 0;">
                                    <a href="{$verificationLink}" style="display:inline-block; background-color:#C6A43F; color:#0A0A0A; font-family:'Inter', Arial, sans-serif; font-size:15px; font-weight:600; text-decoration:none; padding:14px 48px; border-radius:4px; letter-spacing:0.3px;">
                                        Verify Email Address
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:12px; color:#999999; margin:0 0 6px 0; text-align:center;">
                            Or copy and paste this link into your browser:
                        </p>
                        <p style="font-size:12px; color:#C6A43F; margin:0 0 18px 0; text-align:center; word-break:break-all; background:#F8F6F1; padding:10px 14px; border-radius:4px;">
                            <a href="{$verificationLink}" style="color:#C6A43F; text-decoration:none;">{$verificationLink}</a>
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr><td style="padding:6px 0 18px 0;"><hr style="border:0; border-top:1px solid #E8E5E0;"></td></tr>
                        </table>

                        <!-- Trust Signals -->
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding:4px 0;">
                                    <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                        <tr>
                                            <td style="padding:0 12px; text-align:center;">
                                                <span style="font-size:20px;">🏛️</span>
                                                <p style="font-size:10px; color:#999; margin:2px 0 0 0;">Registered Company</p>
                                            </td>
                                            <td style="padding:0 12px; text-align:center;">
                                                <span style="font-size:20px;">🔒</span>
                                                <p style="font-size:10px; color:#999; margin:2px 0 0 0;">Secure Connection</p>
                                            </td>
                                            <td style="padding:0 12px; text-align:center;">
                                                <span style="font-size:20px;">✅</span>
                                                <p style="font-size:10px; color:#999; margin:2px 0 0 0;">Verified Platform</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:16px 0 0 0;">
                                    <p style="font-size:12px; color:#999999; margin:0; text-align:center; line-height:1.6;">
                                        This verification link expires in <strong style="color:#666;">24 hours</strong>.<br>
                                        If you did not create an account with KINAS GROUP, please ignore this email.
                                    </p>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- DIVISIONS BAR -->
                <tr>
                    <td bgcolor="#F8F6F1" style="background-color:#F8F6F1; padding:16px 20px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="font-size:9px; color:#888; letter-spacing:2px; text-transform:uppercase; font-weight:500;">
                                    Our Divisions
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="font-size:10px; color:#555; letter-spacing:0.5px; padding-top:6px;">
                                    KINAS Automobile &nbsp;·&nbsp; Williams Connect Home &nbsp;·&nbsp; KINAS Volt &nbsp;·&nbsp; KINAS Marketplace
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td bgcolor="#0A0A0A" style="background-color:#0A0A0A; padding:24px 40px 20px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="color:rgba(255,255,255,0.4); font-size:11px; line-height:1.8;">
                                    &copy; {$year} KINAS GROUP OF COMPANIES LIMITED
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="color:rgba(255,255,255,0.3); font-size:10px; line-height:1.6; padding-top:4px;">
                                    RC Number: 7997266
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="color:rgba(255,255,255,0.3); font-size:10px; line-height:1.6;">
                                    Gwarinpa, 900108, Federal Capital Territory, Nigeria
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top:10px;">
                                    <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                        <tr>
                                            <td style="padding:0 8px;">
                                                <a href="https://kinas-group.com" style="color:rgba(255,255,255,0.4); text-decoration:none; font-size:10px;">Website</a>
                                            </td>
                                            <td style="padding:0 8px; color:rgba(255,255,255,0.2);">|</td>
                                            <td style="padding:0 8px;">
                                                <a href="mailto:support@kinas-group.com" style="color:rgba(255,255,255,0.4); text-decoration:none; font-size:10px;">Support</a>
                                            </td>
                                            <td style="padding:0 8px; color:rgba(255,255,255,0.2);">|</td>
                                            <td style="padding:0 8px;">
                                                <a href="tel:+2348107576042" style="color:rgba(255,255,255,0.4); text-decoration:none; font-size:10px;">+234 810 757 6042</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top:12px;">
                                    <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                        <tr>
                                            <td style="padding:0 6px;">
                                                <a href="#" style="display:inline-block; width:28px; height:28px; background:rgba(255,255,255,0.05); border-radius:50%; text-align:center; line-height:28px; color:rgba(255,255,255,0.4); font-size:12px; text-decoration:none;">📱</a>
                                            </td>
                                            <td style="padding:0 6px;">
                                                <a href="#" style="display:inline-block; width:28px; height:28px; background:rgba(255,255,255,0.05); border-radius:50%; text-align:center; line-height:28px; color:rgba(255,255,255,0.4); font-size:12px; text-decoration:none;">📷</a>
                                            </td>
                                            <td style="padding:0 6px;">
                                                <a href="#" style="display:inline-block; width:28px; height:28px; background:rgba(255,255,255,0.05); border-radius:50%; text-align:center; line-height:28px; color:rgba(255,255,255,0.4); font-size:12px; text-decoration:none;">👍</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top:14px;">
                                    <p style="font-size:9px; color:rgba(255,255,255,0.2); margin:0; line-height:1.4;">
                                        This email was sent to you because you registered on kinas-group.com.<br>
                                        If you didn't register, please ignore this email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
HTML;
    }
    
    /**
     * Build plain text version
     */
    private function buildVerificationEmailPlain($name, $verificationLink)
    {
        return <<<TEXT
KINAS GROUP OF COMPANIES LIMITED
========================================

Hello, {$name}!

Thank you for joining KINAS GROUP OF COMPANIES LIMITED. 
We're excited to have you as part of our luxury marketplace ecosystem.

Please verify your email address to complete your registration:

VERIFICATION LINK:
{$verificationLink}

This verification link expires in 24 hours.

If you did not create an account with KINAS GROUP, please ignore this email.

---
Our Divisions:
KINAS Automobile | Williams Connect Home | KINAS Volt | KINAS Marketplace

© 2025 KINAS GROUP OF COMPANIES LIMITED
RC Number: 7997266
Gwarinpa, 900108, Federal Capital Territory, Nigeria
Website: https://kinas-group.com
Email: support@kinas-group.com
Phone: +234 810 757 6042
TEXT;
    }
}
