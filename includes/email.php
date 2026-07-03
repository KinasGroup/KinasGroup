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
        $this->siteUrl = getenv('SITE_URL') ?: '/';
        
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
     */
    private function getEmailHeader()
    {
        return '
        <div style="text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F; margin-bottom:24px;">
            <img src="https://kinas-group.com/assets/images/logos/kinas-email-header.jpg" 
                 style="max-height:60px; width:auto;" alt="KINAS GROUP" onerror="this.style.display=\'none\'">
            <div style="font-size:10px; color:#666; letter-spacing:2px; margin-top:4px; font-family: Arial, sans-serif;">BUILDING EXCELLENCE ACROSS INDUSTRIES</div>
            <div style="font-size:8px; color:#999; margin-top:2px; font-family: Arial, sans-serif;">Gwarinpa, Abuja • +234 810 757 6042</div>
        </div>';
    }
    
    /**
     * Get the email footer - KINAS GROUP BRANDING
     */
    private function getEmailFooter()
    {
        return '
        <div style="text-align:center; padding-top:12px; border-top:1px solid #E0E0E0; margin-top:20px; font-size:8px; color:#999; font-family: Arial, sans-serif;">
            KINAS GROUP OF COMPANIES LIMITED<br>
            www.kinas-group.com
        </div>';
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
        <div style="padding: 20px 0; font-family: Arial, sans-serif;">
            <h2 style="color: #0A0A0A;">Welcome to KINAS GROUP!</h2>
            <p>Hello <strong>{$name}</strong>,</p>
            <p>Thank you for joining KINAS GROUP OF COMPANIES LIMITED. We're excited to have you as part of our luxury marketplace ecosystem.</p>
            
            <p style="margin: 20px 0;">Please verify your email address to complete your registration:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{$verificationLink}" style="display: inline-block; padding: 14px 40px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 15px;">Verify Email Address</a>
            </div>
            
            <p style="font-size: 12px; color: #999;">Or copy and paste this link into your browser:</p>
            <p style="font-size: 12px; color: #C6A43F; word-break: break-all;">{$verificationLink}</p>
            
            <p style="font-size: 12px; color: #999; margin-top: 20px;">This verification link expires in <strong>24 hours</strong>.</p>
            <p style="font-size: 12px; color: #999;">If you did not create an account with KINAS GROUP, please ignore this email.</p>
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
Phone: +234 810 757 6042
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
        return $header . $this->buildVerificationEmailBody($name, $verificationLink, $year) . $footer;
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
