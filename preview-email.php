<?php
/**
 * Email Preview Script - DELETE AFTER TESTING
 * Visit: https://your-site.com/preview-email.php
 */

// Load the EmailService
require_once __DIR__ . '/includes/email.php';

// Sample data
$name = 'John Doe';
$verificationLink = 'https://kinas-group.com/auth/verify-email.php?code=abc123xyz789';
$year = date('Y');

// Create EmailService instance
$emailService = new EmailService();

// Use reflection to access the private method
$reflection = new ReflectionClass($emailService);
$method = $reflection->getMethod('buildVerificationEmail');
$method->setAccessible(true);

// Generate the email HTML
$emailHtml = $method->invoke($emailService, $name, $verificationLink, $year);

// Display it
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Preview - KINAS GROUP</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
            font-family: 'Inter', Arial, sans-serif;
        }
        .preview-header {
            max-width: 600px;
            margin: 0 auto 20px;
            padding: 12px 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-header .badge {
            background: #C6A43F;
            color: #0A0A0A;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .preview-header .note {
            font-size: 12px;
            color: #888;
        }
        .preview-frame {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }
        .device-toolbar {
            max-width: 600px;
            margin: 0 auto 10px;
            padding: 8px 16px;
            background: #1a1a1a;
            border-radius: 8px 8px 0 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .device-toolbar .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .device-toolbar .dot.red { background: #ff5f57; }
        .device-toolbar .dot.yellow { background: #ffbd2e; }
        .device-toolbar .dot.green { background: #28c840; }
        .device-toolbar .url {
            flex: 1;
            background: rgba(255,255,255,0.1);
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            font-family: monospace;
            text-align: center;
        }
        .btn-group {
            max-width: 600px;
            margin: 16px auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-group button {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn-group .btn-view { background: #0A0A0A; color: #fff; }
        .btn-group .btn-html { background: #C6A43F; color: #0A0A0A; }
        .btn-group .btn-close { background: #e0e0e0; color: #333; }
        .plain-text {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #f8f6f1;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            border: 1px solid #e8e5e0;
            display: none;
        }
        .plain-text.visible {
            display: block;
        }
        @media (max-width: 640px) {
            body { padding: 10px; }
            .preview-header { flex-direction: column; gap: 6px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="preview-header">
    <div>
        <span style="font-weight:700; color:#0A0A0A;">📧 Email Preview</span>
        <span class="badge">Verification Email</span>
    </div>
    <div class="note">Test data - Real emails will use actual user data</div>
</div>

<div class="device-toolbar">
    <span class="dot red"></span>
    <span class="dot yellow"></span>
    <span class="dot green"></span>
    <span class="url">kinas-group.com/email</span>
</div>

<div class="preview-frame">
    <?= $emailHtml ?>
</div>

<div class="btn-group">
    <button class="btn-view" onclick="openInNewTab()">📱 Open in New Tab</button>
    <button class="btn-html" onclick="togglePlainText()">📄 Show Plain Text</button>
    <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>

<div id="plainTextContainer" class="plain-text">
<?php
// Generate plain text version
$plainMethod = $reflection->getMethod('buildPlainTextVerification');
$plainMethod->setAccessible(true);
$plainText = $plainMethod->invoke($emailService, $name, $verificationLink);
echo htmlspecialchars($plainText);
?>
</div>

<script>
function openInNewTab() {
    const html = `<?= addslashes($emailHtml) ?>`;
    const win = window.open('', '_blank');
    if (win) {
        win.document.write(html);
        win.document.close();
    }
}

function togglePlainText() {
    const el = document.getElementById('plainTextContainer');
    el.classList.toggle('visible');
}
</script>

</body>
</html>
