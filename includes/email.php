<?php
// KINAS GROUP - Email System
// Supports both PHP mail() and Resend API

class EmailService {
    private $fromEmail = 'noreply@kinas-group.com';
    private $fromName = 'KINAS GROUP';
    private $useResend = false;
    private $resendApiKey = '';
    private $baseUrl = '';

    public function __construct() {
        // First try getenv() (works if dotenv was loaded)
        $this->resendApiKey = getenv('RESEND_API_KEY') ?: '';

        // If getenv returns empty, try $_ENV directly
        if (empty($this->resendApiKey) && isset($_ENV['RESEND_API_KEY'])) {
            $this->resendApiKey = $_ENV['RESEND_API_KEY'];
        }

        // If still empty, try loading .env directly as backup
        if (empty($this->resendApiKey)) {
            $envFile = __DIR__ . '/../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        if ($key === 'RESEND_API_KEY') {
                            $this->resendApiKey = trim($value);
                            break;
                        }
                    }
                }
            }
        }

        $this->useResend = !empty($this->resendApiKey)
            && $this->resendApiKey !== 'YOUR_RESEND_API_KEY'
            && strpos($this->resendApiKey, 're_') === 0;
            
        // Get base URL from environment or use fallback
        $this->baseUrl = getenv('APP_URL') ?: '';
        if (empty($this->baseUrl) && isset($_ENV['APP_URL'])) {
            $this->baseUrl = $_ENV['APP_URL'];
        }
        if (empty($this->baseUrl)) {
            // Fallback to current request
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'kinasgroup.com';
            $this->baseUrl = $protocol . '://' . $host;
        }
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function sendVerificationEmail($userEmail, $userName, $verificationCode) {
        $subject = 'Verify Your Email - KINAS GROUP';
        $verifyLink = $this->baseUrl . "/auth/verify-email.php?code=" . urlencode($verificationCode);

        $body = $this->getEmailTemplate('Email Verification', "
            <h2>Welcome to KINAS GROUP, " . htmlspecialchars($userName) . "!</h2>
            <p>Please verify your email address to complete your registration.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verifyLink}' style='background: #151515; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Verify Email Address
                </a>
            </div>
            <p>Or copy this link: <br><small>{$verifyLink}</small></p>
            <p>This link will expire in 24 hours.</p>
        ");

        return $this->send($userEmail, $subject, $body);
    }

    public function sendPasswordReset($userEmail, $userName, $resetToken) {
        $subject = 'Password Reset - KINAS GROUP';
        $resetLink = $this->baseUrl . "/auth/reset-password.php?token=" . urlencode($resetToken);

        $body = $this->getEmailTemplate('Password Reset', "
            <h2>Password Reset Request</h2>
            <p>Hello " . htmlspecialchars($userName) . ",</p>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' style='background: #151515; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Reset Password
                </a>
            </div>
            <p>If you didn't request this, please ignore this email.</p>
            <p>This link will expire in 1 hour.</p>
        ");

        return $this->send($userEmail, $subject, $body);
    }

    public function sendAgentApproved($userEmail, $userName) {
        $subject = 'Agent Account Approved - KINAS GROUP';

        $body = $this->getEmailTemplate('Account Approved', "
            <h2>Congratulations, " . htmlspecialchars($userName) . "!</h2>
            <p>Your agent account has been approved. You can now start listing on KINAS GROUP.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$this->baseUrl}/agent/dashboard.php' style='background: #006c75; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    Go to Dashboard
                </a>
            </div>
        ");

        return $this->send($userEmail, $subject, $body);
    }

    public function sendAgentRejected($userEmail, $userName, $reason) {
        $subject = 'Agent Application Status - KINAS GROUP';

        $body = $this->getEmailTemplate('Application Status', "
            <h2>Hello " . htmlspecialchars($userName) . ",</h2>
            <p>Unfortunately, your agent application could not be approved at this time.</p>
            <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
            <p>You may reapply after addressing the issues mentioned above.</p>
        ");

        return $this->send($userEmail, $subject, $body);
    }

    public function sendNewInquiry($agentEmail, $agentName, $listingTitle, $buyerName, $buyerEmail, $message) {
        $subject = "New Inquiry - {$listingTitle}";

        $body = $this->getEmailTemplate('New Inquiry', "
            <h2>New Listing Inquiry</h2>
            <p><strong>Listing:</strong> " . htmlspecialchars($listingTitle) . "</p>
            <p><strong>From:</strong> " . htmlspecialchars($buyerName) . " (" . htmlspecialchars($buyerEmail) . ")</p>
            <p><strong>Message:</strong></p>
            <blockquote style='border-left: 3px solid #006c75; padding-left: 15px;'>" . nl2br(htmlspecialchars($message)) . "</blockquote>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$this->baseUrl}/agent/messages.php' style='background: #006c75; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;'>
                    View Messages
                </a>
            </div>
        ");

        return $this->send($agentEmail, $subject, $body);
    }

    private function getEmailTemplate($title, $content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #151515; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px; border-bottom: 3px solid #151515; }
                .header img { max-height: 40px; }
                .content { padding: 30px 20px; }
                .footer { text-align: center; padding: 20px; color: #717171; font-size: 12px; border-top: 1px solid #e0e0e0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0; font-family: \"Prata\", serif;'>KINAS GROUP</h1>
                    <p style='color: #717171;'>{$title}</p>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " KINAS GROUP OF COMPANY LIMITED. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply directly.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function send($to, $subject, $body) {
        // Use Resend API if configured
        if ($this->useResend) {
            return $this->sendViaResend($to, $subject, $body);
        }

        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: support@kinasgroup.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return mail($to, $subject, $body, $headers);
    }

    private function sendViaResend($to, $subject, $body) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'from' => $this->fromName . ' <' . $this->fromEmail . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $body
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->resendApiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log for debugging
        error_log("Resend API call - To: $to, HTTP Code: $httpCode, Response: $response");

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log('Resend API error: ' . $response . ' (HTTP ' . $httpCode . ')');
        if (!empty($curlError)) {
            error_log('Resend cURL error: ' . $curlError);
        }
        return false;
    }
}
?>
