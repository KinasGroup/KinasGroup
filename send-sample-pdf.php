<?php
// send-sample-pdf.php
// Sends the sample PDF via email using PHP mail()

$pdfFile = __DIR__ . '/generated-pdfs/sample-solar-proposal-2026-06-16.pdf';

if (!file_exists($pdfFile)) {
    die("❌ PDF not found: $pdfFile\n");
}

$to = 'admin@kinas-group.com';
$subject = '📄 Sample Solar Proposal PDF - ' . date('Y-m-d');
$pdfUrl = 'https://kinas-group.com/generated-pdfs/' . basename($pdfFile);

$body = '
<!DOCTYPE html>
<html>
<head>
<style>
body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background: #0A0A0A; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
.header h1 { color: #C6A43F; font-family: "Prata", serif; margin: 0; }
.content { background: #FFFFFF; padding: 30px; border: 1px solid #E0E0E0; border-top: none; border-radius: 0 0 8px 8px; }
.btn { display: inline-block; padding: 12px 30px; background: #C6A43F; color: #0A0A0A; text-decoration: none; border-radius: 4px; font-weight: bold; }
.footer { text-align: center; padding-top: 20px; font-size: 11px; color: #999; border-top: 1px solid #E0E0E0; margin-top: 20px; }
.info-box { background: #F8F6F1; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #C6A43F; }
</style>
</head>
<body>
<div class="header"><h1>☀️ KINAS VOLT</h1><p style="color:#999;">Premium Solar Energy Solutions</p></div>
<div class="content">
<h2>Sample Solar Proposal PDF Ready</h2>
<p>Dear Admin,</p>
<p>A sample solar proposal PDF has been generated successfully.</p>
<div class="info-box">
<strong>📄 Document Details:</strong><br>
<strong>File:</strong> ' . basename($pdfFile) . '<br>
<strong>Size:</strong> ' . number_format(filesize($pdfFile)) . ' bytes<br>
<strong>Generated:</strong> ' . date('F j, Y - H:i:s') . '
</div>
<p style="text-align:center;margin:30px 0;">
<a href="' . $pdfUrl . '" class="btn">📄 View/Download PDF</a>
</p>
<div class="footer">KINAS GROUP • Gwarimpa, Abuja • +234 913 717 5523<br>🌐 <a href="https://kinas-group.com">kinas-group.com</a></div>
</div>
</body>
</html>';

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: KINAS VOLT Solar Division <listings@kinas-group.com>\r\n";
$headers .= "Reply-To: listings@kinas-group.com\r\n";

echo "\n📧 Sending email to admin@kinas-group.com...\n";
echo "📎 PDF: " . basename($pdfFile) . " (" . number_format(filesize($pdfFile)) . " bytes)\n";
echo "📬 From: listings@kinas-group.com\n\n";

$result = mail($to, $subject, $body, $headers);

if ($result) {
    echo "✅ Email sent successfully!\n";
    echo "📎 PDF URL: $pdfUrl\n";
} else {
    echo "❌ Email failed. Check your mail settings.\n";
    echo "📄 PDF available at: $pdfUrl\n";
}
