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
        $this->fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'info@kinas-group.com';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'KINAS GROUP OF COMPANIES LIMITED';
        $this->siteUrl = rtrim(getenv('APP_URL') ?: 'https://kinas-group.com', '/');

        // Prefer the domain the visitor is actually on. division-router.js
        // sets X-Original-Host on every request using the hostname it
        // resolved server-side (it overwrites any client-supplied value of
        // the same name, so this isn't attacker-controlled) — the same
        // header includes/security.php already trusts for reCAPTCHA. Without
        // this, every email (verification, password reset, agent approval,
        // etc.) always linked back to kinas-group.com even when someone
        // registered through kinasauto.com/kinasvolt.com/etc., bouncing them
        // to a different-looking site mid-flow.
        $originalHost = strtolower((string)($_SERVER['HTTP_X_ORIGINAL_HOST'] ?? ''));
        $originalHost = preg_replace('/^www\./', '', $originalHost);
        $allowedHosts = ['kinas-group.com', 'kinasauto.com', 'williamsconnecthome.com', 'kinasvolt.com', 'kinasstore.com'];
        if ($originalHost !== '' && in_array($originalHost, $allowedHosts, true)) {
            $this->siteUrl = 'https://' . $originalHost;
        }
        
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
     * Get the email header - KINAS GROUP BRANDING
     * Uses the main KINAS GROUP logo for all communications
     * NOTE: colors are set with BOTH the bgcolor attribute and an inline
     * style, and text colors are always explicit. This is what keeps the
     * email looking identical in dark-mode inboxes (Gmail/Apple Mail/Outlook
     * auto-invert anything that doesn't have an explicit color declared).
     */
    private function getEmailHeader()
    {
        return '
        <div class="email-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF !important; text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F; margin-bottom:24px;">
            <img src="https://kinas-group.com/assets/images/logos/kinas-email-header.jpg" 
                 style="max-height:60px; width:auto;" alt="KINAS GROUP" onerror="this.style.display=\'none\'">
            <div style="font-size:10px; color:#666666 !important; letter-spacing:2px; margin-top:4px; font-family: Arial, sans-serif;">BUILDING EXCELLENCE ACROSS INDUSTRIES</div>
            <div style="font-size:8px; color:#999999 !important; margin-top:2px; font-family: Arial, sans-serif;">Gwarinpa, Abuja &bull; +234 913 717 5523</div>
        </div>';
    }
    
    /**
     * Get the email footer - KINAS GROUP BRANDING
     */
    private function getEmailFooter()
    {
        return '
        <div class="email-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF !important; text-align:center; padding-top:12px; border-top:1px solid #E0E0E0; margin-top:20px; font-size:8px; color:#999999 !important; font-family: Arial, sans-serif;">
            KINAS GROUP OF COMPANIES LIMITED<br>
            www.kinas-group.com
        </div>';
    }

    /**
     * Wrap any inner email HTML fragment in a full, dark-mode-safe HTML
     * document: a light-only color-scheme declaration, a table-based
     * layout (best client compatibility), and every background/text
     * color set explicitly via both bgcolor attributes AND inline
     * !important styles so mail clients cannot auto-invert it in dark mode.
     */
    private function wrapEmailDocument($innerHtml, $subject = '')
    {
        $title = htmlspecialchars($subject !== '' ? $subject : 'KINAS GROUP', ENT_QUOTES);
        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="only light">
<meta name="supported-color-schemes" content="only light">
<title>{$title}</title>
<!--[if mso]>
<style type="text/css">
    body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
</style>
<![endif]-->
<style>
    :root { color-scheme: only light; supported-color-schemes: only light; }
    body { margin:0; padding:0; width:100% !important; background-color:#F2F2F2 !important; }
    table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
    img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }
    a { color:#C6A43F !important; text-decoration:none; }

    /* Force the same light appearance regardless of the client/device
       dark-mode setting (Gmail, Apple Mail, Outlook.com / OWA, etc.) */
    .email-bg  { background-color:#F2F2F2 !important; }
    .email-card, .email-card * { background-color:#FFFFFF !important; }
    .email-card, .email-card p, .email-card li, .email-card span,
    .email-card div, .email-card h1, .email-card h2, .email-card h3 { color:#0A0A0A !important; }

    @media (prefers-color-scheme: dark) {
        body, .email-bg { background-color:#F2F2F2 !important; }
        .email-card, .email-card * { background-color:#FFFFFF !important; color:#0A0A0A !important; }
        a { color:#C6A43F !important; }
    }
    /* Outlook.com / Office 365 webmail dark mode hooks */
    [data-ogsc] .email-card, [data-ogsc] .email-card *,
    [data-ogsb] .email-card, [data-ogsb] .email-card * {
        background-color:#FFFFFF !important; color:#0A0A0A !important;
    }
</style>
</head>
<body class="email-bg" bgcolor="#F2F2F2" style="margin:0; padding:0; background-color:#F2F2F2 !important;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="email-bg" bgcolor="#F2F2F2" style="background-color:#F2F2F2 !important;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="email-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF !important; max-width:600px; width:100%; border-radius:6px;">
<tr>
<td class="email-card" bgcolor="#FFFFFF" style="background-color:#FFFFFF !important; color:#0A0A0A !important; padding:32px 32px 24px;">
{$innerHtml}
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
     * Send email using Resend API or fallback
     */
    /**
     * The domain-aware site URL (see constructor) — for callers building
     * their own links (e.g. a password reset URL) rather than using one
     * of EmailService's own template methods.
     */
    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    public function send($to, $name, $subject, $htmlBody, $plainText = '', $fromEmail = null, $fromName = null)
    {
        $htmlBody = $this->wrapEmailDocument($htmlBody, $subject);
        $fromEmail = $fromEmail ?: $this->fromEmail;
        $fromName = $fromName ?: $this->fromName;

        // Try Resend first
        if ($this->useResend) {
            $result = $this->sendViaResend($to, $name, $subject, $htmlBody, $plainText, $fromEmail, $fromName);
            if ($result) {
                return true;
            }
        }
        
        // Fallback to PHPMailer
        return $this->sendViaPHPMailer($to, $name, $subject, $htmlBody, $plainText, $fromEmail, $fromName);
    }
    
    /**
     * Send email via Resend API
     */
    private function sendViaResend($to, $name, $subject, $htmlBody, $plainText = '', $fromEmail = null, $fromName = null)
    {
        $payload = [
            'from' => ($fromName ?: $this->fromName) . ' <' . ($fromEmail ?: $this->fromEmail) . '>',
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
     *
     * $fromEmail/$fromName let a caller present a different verified
     * address (e.g. sales@kinas-group.com for an order confirmation)
     * while still authenticating over SMTP as the one mailbox configured
     * in setupPHPMailer() (SMTP_USERNAME/SMTP_PASSWORD) — this is exactly
     * what Zoho's "Send Mail As" delegation expects: the authenticated
     * account differs from the visible From address, and Zoho accepts it
     * because that address was added as a verified alias on their side.
     */
    private function sendViaPHPMailer($to, $name, $subject, $htmlBody, $plainText = '', $fromEmail = null, $fromName = null)
    {
        if (!$this->usePHPMailer) {
            error_log('PHPMailer not available');
            return false;
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->setFrom($fromEmail ?: $this->fromEmail, $fromName ?: $this->fromName);
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
        $header = $this->getEmailHeader();
        $footer = $this->getEmailFooter();
        
        $htmlBody = $header . $this->buildVerificationEmailBody($name, $verificationLink, $year) . $footer;
        $plainText = $this->buildVerificationEmailPlain($name, $verificationLink);
        
        return $this->send($email, $name, $subject, $htmlBody, $plainText);
    }
    
    /**
     * Build verification email body
     */
    private function buildVerificationEmailBody($name, $verificationLink, $year)
    {
        return <<<HTML
        <div class="email-card" style="padding: 20px 0; font-family: Arial, sans-serif; background-color:#FFFFFF !important; color:#0A0A0A !important;">
            <h2 style="color: #0A0A0A !important; margin:0 0 16px;">Welcome to KINAS GROUP!</h2>
            <p style="color:#0A0A0A !important;">Hello <strong style="color:#0A0A0A !important;">{$name}</strong>,</p>
            <p style="color:#0A0A0A !important;">Thank you for joining KINAS GROUP OF COMPANIES LIMITED. We're excited to have you as part of our luxury marketplace ecosystem.</p>
            
            <p style="margin: 20px 0; color:#0A0A0A !important;">Please verify your email address to complete your registration:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$verificationLink}" style="display: inline-block; padding: 14px 40px; background-color: #C6A43F !important; color: #0A0A0A !important; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 15px;">Verify Email Address</a>
            </div>
            
            <p style="font-size: 12px; color: #666666 !important;">Or copy and paste this link into your browser:</p>
            <p style="font-size: 12px; color: #C6A43F !important; word-break: break-all;">{$verificationLink}</p>
            
            <p style="font-size: 12px; color: #666666 !important; margin-top: 20px;">This verification link expires in <strong style="color:#666666 !important;">24 hours</strong>.</p>
            <p style="font-size: 12px; color: #666666 !important;">If you did not create an account with KINAS GROUP, please ignore this email.</p>
        </div>
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
Phone: +234 913 717 5523
TEXT;
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
        $header = $this->getEmailHeader();
        $footer = $this->getEmailFooter();
        $inner = $header . $this->buildVerificationEmailBody($name, $verificationLink, $year) . $footer;
        return $this->wrapEmailDocument($inner, 'Verify your KINAS GROUP account');
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
}
