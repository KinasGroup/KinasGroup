<?php
// includes/solar-pdf.php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 62,
        'margin_bottom' => 40,
        'margin_left'   => 18,
        'margin_right'  => 18,
        'format'        => 'A4'
    ]);

    // Professional Letterhead Header with correct logo path
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:18px; border-bottom:4px solid #C6A43F;">
        <img src="'. __DIR__ .'/../assets/images/logos/kinas-volt-logo.jpg" 
             style="max-height:125px; width:auto;" alt="KINAS VOLT">
        <h1 style="margin:12px 0 4px 0; color:#0A0A0A; font-family:Prata,serif; letter-spacing:1px;">
            KINAS VOLT
        </h1>
        <p style="margin:0; font-size:14px; color:#555; font-weight:500;">
            Premium Solar Energy Solutions
        </p>
        <p style="margin:8px 0 0 0; font-size:13px; color:#666;">
            Gwarimpa, 900108, Federal Capital Territory, Abuja • +234 913 717 5523
        </p>
    </div>');

    $mpdf->SetHTMLFooter('
    <table width="100%" style="font-size:10px; color:#777; border-top:1px solid #ddd; padding-top:10px;">
        <tr>
            <td>Reference: <strong>'.$reference.'</strong></td>
            <td align="center">KINAS GROUP • volt.kinasgroup.com</td>
            <td align="right">Page {PAGENO} of {nb}</td>
        </tr>
    </table>');

    $html = '
    <h1 style="text-align:center; color:#0A0A0A; font-family:Prata,serif; margin:45px 0 10px 0;">Professional Solar Power System Proposal</h1>
    <p style="text-align:center; color:#C6A43F; font-size:18px; margin-bottom:35px;">
        Reference: <strong>'.$reference.'</strong> &nbsp;&nbsp;|&nbsp;&nbsp; '.date('F j, Y').'
    </p>

    <!-- Customer Information -->
    <h2 style="color:#C6A43F;">Customer Information</h2>
    <table border="1" cellpadding="12" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:30px;">
        <tr><td width="38%"><strong>Full Name</strong></td><td>'.htmlspecialchars($data['full_name'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Email Address</strong></td><td>'.htmlspecialchars($data['email'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Phone Number</strong></td><td>'.htmlspecialchars($data['phone'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Location</strong></td><td>'.htmlspecialchars($data['city_state'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Property Type</strong></td><td>'.htmlspecialchars($data['property_type'] ?? 'N/A').'</td></tr>
    </table>

    <!-- Load Analysis -->
    <h2 style="color:#C6A43F;">Load Analysis Summary</h2>
    <table border="1" cellpadding="12" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:30px;">
        <tr><td><strong>Total Connected Load</strong></td><td><strong>'.number_format($data['total_load_watts'] ?? 0).' Watts</strong></td></tr>
        <tr><td><strong>Daily Energy Consumption</strong></td><td><strong>'.number_format($data['daily_kwh'] ?? 0, 2).' kWh</strong></td></tr>
        <tr><td><strong>Desired Backup Duration</strong></td><td><strong>'.($data['backup_hours'] ?? 24).' Hours</strong></td></tr>
    </table>

    <!-- Recommended System -->
    <h2 style="color:#C6A43F;">Recommended System Configuration</h2>
    <table border="1" cellpadding="12" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:35px;">
        <tr style="background:#F8F8F8; font-weight:600;">
            <th>Component</th><th>Specification</th><th>Quantity</th>
        </tr>
        <tr><td>Solar Panels</td><td>550W Monocrystalline Tier-1</td><td><strong>'.($data['recommended_panels'] ?? 18).' Units</strong></td></tr>
        <tr><td>Inverter</td><td>'.htmlspecialchars($data['recommended_inverter'] ?? '10kVA Hybrid Inverter').'</td><td>1 Unit</td></tr>
        <tr><td>Battery Bank</td><td>'.htmlspecialchars($data['recommended_battery'] ?? '48V 200Ah Lithium LiFePO4').'</td><td>2–3 Units</td></tr>
    </table>

    <!-- Cost Summary -->
    <h2 style="color:#C6A43F;">Investment Summary</h2>
    <table border="1" cellpadding="15" cellspacing="0" width="100%" style="border-collapse:collapse; background:#FEFBF5; font-size:15px;">
        <tr>
            <td><strong>Estimated Total Project Cost</strong></td>
            <td style="font-size:26px; color:#C6A43F; font-weight:bold;">₦'.number_format($data['estimated_cost'] ?? 14850000).'</td>
        </tr>
    </table>

    <p style="margin-top:40px; line-height:1.7; font-size:13.5px;">
        <strong>Warranty:</strong> 25 Years (Panels), 5–10 Years (Batteries & Inverter), 2 Years Workmanship.<br><br>
        <strong>Disclaimer:</strong> This proposal is generated based on the data provided. A comprehensive site assessment by our engineers is required for final design, exact quantities, and pricing.
    </p>

    <p style="text-align:center; margin-top:60px; color:#444; font-size:14px;">
        Thank you for choosing <strong>KINAS VOLT</strong>.<br>
        Our technical team will contact you within 24 hours to schedule your <strong>free site assessment</strong>.
    </p>';

    $mpdf->WriteHTML($html);

    // Save PDF
    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filepath = $uploadDir . $reference . '.pdf';
    $mpdf->Output($filepath, 'F');

    return $filepath;
}
