<?php
// includes/solar-pdf.php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 55,
        'margin_bottom' => 35,
        'margin_left'   => 15,
        'margin_right'  => 15,
        'format'        => 'A4'
    ]);

    // Letterhead Header using existing company logo
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:15px; border-bottom:3px solid #C6A43F;">
        <img src="'. __DIR__ .'/../assets/images/logo.png" style="max-height:110px;" alt="KINAS GROUP">
        <h2 style="margin:10px 0 5px 0; color:#0A0A0A; font-family:Prata,serif;">KINAS VOLT</h2>
        <p style="margin:0; font-size:14px; color:#555;">Solar Energy Division • KINAS GROUP</p>
        <p style="margin:5px 0 0 0; font-size:13px; color:#666;">
            Gwarimpa, 900108, Federal Capital Territory, Abuja<br>
            <strong>+234 913 717 5523</strong>
        </p>
    </div>');

    // Footer
    $mpdf->SetHTMLFooter('
    <table width="100%" style="font-size:10px; color:#666; border-top:1px solid #ddd; padding-top:8px;">
        <tr>
            <td>Ref: <strong>'.$reference.'</strong></td>
            <td align="center">KINAS GROUP • volt.kinasgroup.com</td>
            <td align="right">Page {PAGENO} of {nb}</td>
        </tr>
    </table>');

    $html = '
    <h1 style="text-align:center; color:#0A0A0A; font-family:Prata,serif; margin:40px 0 10px;">Solar Power System Proposal</h1>
    <p style="text-align:center; color:#C6A43F; font-size:18px; margin-bottom:30px;">Reference: <strong>'.$reference.'</strong> | '.date('F j, Y').'</p>

    <!-- Customer Details -->
    <h2>Customer Information</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:25px;">
        <tr><td width="35%"><strong>Full Name</strong></td><td>'.htmlspecialchars($data['full_name'] ?? '').'</td></tr>
        <tr><td><strong>Email</strong></td><td>'.htmlspecialchars($data['email'] ?? '').'</td></tr>
        <tr><td><strong>Phone</strong></td><td>'.htmlspecialchars($data['phone'] ?? '').'</td></tr>
        <tr><td><strong>Location</strong></td><td>'.htmlspecialchars($data['city_state'] ?? '').'</td></tr>
        <tr><td><strong>Property Type</strong></td><td>'.htmlspecialchars($data['property_type'] ?? '').'</td></tr>
    </table>

    <!-- Load Analysis -->
    <h2>Load Analysis</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:25px;">
        <tr><td><strong>Total Connected Load</strong></td><td><strong>'.number_format($data['total_load_watts'] ?? 0).' Watts</strong></td></tr>
        <tr><td><strong>Daily Energy Consumption</strong></td><td><strong>'.number_format($data['daily_kwh'] ?? 0, 2).' kWh</strong></td></tr>
        <tr><td><strong>Desired Backup Time</strong></td><td><strong>'.($data['backup_hours'] ?? 24).' hours</strong></td></tr>
    </table>

    <!-- Recommendations -->
    <h2>Recommended System Configuration</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:30px;">
        <tr style="background:#F8F8F8;"><th>Component</th><th>Specification</th><th>Quantity</th></tr>
        <tr><td>Solar Panels</td><td>550W Monocrystalline Tier-1</td><td><strong>'.($data['recommended_panels'] ?? 12).' units</strong></td></tr>
        <tr><td>Inverter</td><td>'.htmlspecialchars($data['recommended_inverter'] ?? '8kVA Hybrid Inverter').'</td><td>1</td></tr>
        <tr><td>Battery Bank</td><td>'.htmlspecialchars($data['recommended_battery'] ?? '48V Lithium').'</td><td>2</td></tr>
    </table>

    <p style="margin-top:30px; font-size:14px; line-height:1.6;">
        <strong>Estimated Total Investment:</strong> ₦'.number_format($data['estimated_cost'] ?? 8450000).'<br><br>
        <strong>Disclaimer:</strong> This is an automated proposal based on provided information. Final design, pricing, and performance will be confirmed after a professional site survey.
    </p>

    <p style="text-align:center; margin-top:50px; color:#666;">
        Thank you for choosing KINAS VOLT.<br>
        Our team will contact you shortly for your free site assessment.
    </p>';

    $mpdf->WriteHTML($html);

    // Save file
    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = $reference . '.pdf';
    $filepath = $uploadDir . $filename;

    $mpdf->Output($filepath, 'F');

    return $filepath;
}
