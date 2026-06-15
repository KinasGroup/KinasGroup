<?php
// includes/solar-pdf.php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 58,
        'margin_bottom' => 40,
        'margin_left'   => 15,
        'margin_right'  => 15,
        'format'        => 'A4'
    ]);

    // Header with Logo & Company Info
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:15px; border-bottom:3px solid #C6A43F;">
        <img src="'. __DIR__ .'/../assets/images/logo.png" style="max-height:105px;" alt="KINAS VOLT">
        <h2 style="margin:8px 0 4px 0; color:#0A0A0A; font-family:Prata,serif;">KINAS VOLT</h2>
        <p style="margin:0; font-size:13px; color:#555;">Solar Energy Division of KINAS GROUP</p>
        <p style="margin:4px 0 0 0; font-size:12px; color:#666;">
            Gwarimpa, 900108, Federal Capital Territory, Abuja<br>
            <strong>+234 913 717 5523</strong>
        </p>
    </div>');

    $mpdf->SetHTMLFooter('
    <table width="100%" style="font-size:10px;color:#666;border-top:1px solid #ddd;padding-top:8px;">
        <tr>
            <td>Ref: <strong>'.$reference.'</strong></td>
            <td align="center">volt.kinasgroup.com</td>
            <td align="right">Page {PAGENO} of {nb}</td>
        </tr>
    </table>');

    $html = '
    <h1 style="text-align:center;color:#0A0A0A;font-family:Prata,serif;margin:35px 0 8px;">Solar Power System Proposal</h1>
    <p style="text-align:center;color:#C6A43F;font-size:17px;margin-bottom:30px;">Reference: <strong>'.$reference.'</strong> | '.date('F j, Y').'</p>

    <!-- Customer Info -->
    <h2>Customer Information</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:25px;">
        <tr><td width="35%"><strong>Full Name</strong></td><td>'.htmlspecialchars($data['full_name'] ?? '').'</td></tr>
        <tr><td><strong>Email</strong></td><td>'.htmlspecialchars($data['email'] ?? '').'</td></tr>
        <tr><td><strong>Phone</strong></td><td>'.htmlspecialchars($data['phone'] ?? '').'</td></tr>
        <tr><td><strong>Location</strong></td><td>'.htmlspecialchars($data['city_state'] ?? '').'</td></tr>
        <tr><td><strong>Property Type</strong></td><td>'.htmlspecialchars($data['property_type'] ?? '').'</td></tr>
    </table>

    <!-- Load Analysis -->
    <h2>Load Analysis Summary</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:25px;">
        <tr><td><strong>Total Connected Load</strong></td><td><strong>'.number_format($data['total_load_watts'] ?? 0).' Watts</strong></td></tr>
        <tr><td><strong>Maximum Surge Load</strong></td><td><strong>'.number_format(($data['total_load_watts'] ?? 0) * 1.25).' Watts</strong></td></tr>
        <tr><td><strong>Daily Energy Consumption</strong></td><td><strong>'.number_format($data['daily_kwh'] ?? 0, 2).' kWh</strong></td></tr>
        <tr><td><strong>Desired Backup Duration</strong></td><td><strong>'.($data['backup_hours'] ?? 24).' hours</strong></td></tr>
    </table>

    <!-- Recommended System -->
    <h2>Recommended System Configuration</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:30px;">
        <tr style="background:#F8F8F8;"><th>Component</th><th>Specification</th><th>Quantity</th></tr>
        <tr><td>Solar Panels</td><td>550W Monocrystalline Tier-1</td><td><strong>'.($data['recommended_panels'] ?? 16).' units ('.round(($data['recommended_panels'] ?? 16)*0.55,1).' kWp)</strong></td></tr>
        <tr><td>Inverter</td><td>'.htmlspecialchars($data['recommended_inverter'] ?? '10kVA Hybrid').'</td><td>1 unit</td></tr>
        <tr><td>Battery Bank</td><td>'.htmlspecialchars($data['recommended_battery'] ?? '48V 200Ah Lithium').'</td><td>2–3 units</td></tr>
    </table>

    <!-- Summary -->
    <h2>Project Summary</h2>
    <table border="1" cellpadding="12" cellspacing="0" width="100%" style="border-collapse:collapse;background:#FEFBF5;">
        <tr><td><strong>Estimated Total Cost</strong></td><td style="font-size:24px;color:#C6A43F;"><strong>₦'.number_format($data['estimated_cost'] ?? 12850000).'</strong></td></tr>
        <tr><td>Estimated Payback Period</td><td>4.0 – 5.2 years</td></tr>
        <tr><td>Installation Timeline</td><td>7 – 14 working days after site survey</td></tr>
    </table>

    <p style="margin-top:30px;line-height:1.6;">
        <strong>Warranty:</strong> 25 years on panels, 5 years on inverter & batteries, 2 years workmanship.<br><br>
        <strong>Disclaimer:</strong> This proposal is based on information supplied by the customer. Final design, quantities, and pricing will be confirmed after a professional site inspection.
    </p>

    <p style="text-align:center;margin-top:50px;color:#444;">
        Thank you for trusting <strong>KINAS VOLT</strong>.<br>
        Our team will contact you within 24 hours to schedule your free site assessment.
    </p>';

    $mpdf->WriteHTML($html);

    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filepath = $uploadDir . $reference . '.pdf';
    $mpdf->Output($filepath, 'F');

    return $filepath;
}
