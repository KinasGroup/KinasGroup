<?php
// includes/solar-pdf.php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 48,
        'margin_bottom' => 35,
        'margin_left'   => 15,
        'margin_right'  => 15,
        'format'        => 'A4'
    ]);

    // Letterhead Header
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:10px; border-bottom:3px solid #C6A43F;">
        <img src="'. __DIR__ .'/../assets/images/letterhead-header.png" style="max-height:130px; width:auto;" alt="KINAS VOLT">
        <h2 style="margin:8px 0 0 0; color:#0A0A0A; font-family:Prata,serif;">KINAS VOLT — Solar Energy Division</h2>
        <p style="margin:0; font-size:13px; color:#555;">Luxury Solar Solutions • Professional Installation</p>
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
    <h1 style="text-align:center; color:#0A0A0A; font-family:Prata,serif; margin-top:30px;">Solar Power System Proposal</h1>
    <p style="text-align:center; color:#C6A43F; font-size:18px; margin-bottom:30px;">Reference: <strong>'.$reference.'</strong> | Date: '.date('F j, Y').'</p>

    <!-- Customer Information -->
    <h2>Customer Information</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:25px;">
        <tr><td width="30%"><strong>Full Name</strong></td><td>'.htmlspecialchars($data['full_name'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Email</strong></td><td>'.htmlspecialchars($data['email'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Phone</strong></td><td>'.htmlspecialchars($data['phone'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Location</strong></td><td>'.htmlspecialchars($data['city_state'] ?? 'N/A').'</td></tr>
        <tr><td><strong>Property Type</strong></td><td>'.htmlspecialchars($data['property_type'] ?? 'N/A').'</td></tr>
    </table>

    <!-- Load Analysis -->
    <h2>Load Analysis Summary</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:25px;">
        <tr style="background:#F8F8F8;">
            <th>Description</th><th>Value</th>
        </tr>
        <tr><td>Total Connected Load</td><td><strong>'.number_format($data['total_load_watts'] ?? 0).' Watts</strong></td></tr>
        <tr><td>Daily Energy Consumption</td><td><strong>'.number_format($data['daily_kwh'] ?? 0, 2).' kWh</strong></td></tr>
        <tr><td>Backup Duration Requested</td><td><strong>'.($data['backup_hours'] ?? 24).' hours</strong></td></tr>
    </table>

    <!-- Recommended System -->
    <h2>Recommended Solar System</h2>
    <table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:30px;">
        <tr style="background:#F8F8F8;">
            <th>Component</th><th>Specification</th><th>Quantity</th>
        </tr>
        <tr><td>Solar Panels</td><td>550W Monocrystalline Tier-1</td><td><strong>'.($data['recommended_panels'] ?? 12).' units</strong></td></tr>
        <tr><td>Inverter</td><td>'.($data['recommended_inverter'] ?? '8kVA Hybrid').'</td><td>1 unit</td></tr>
        <tr><td>Battery Bank</td><td>'.($data['recommended_battery'] ?? '48V 200Ah Lithium').'</td><td>2 units</td></tr>
        <tr><td>Charge Controller</td><td>100A MPPT</td><td>1 unit</td></tr>
    </table>

    <!-- Cost & Performance -->
    <h2>Project Summary</h2>
    <table border="1" cellpadding="12" cellspacing="0" width="100%" style="border-collapse:collapse; background:#FEFBF5;">
        <tr><td><strong>Estimated System Cost</strong></td><td style="font-size:22px; color:#C6A43F;"><strong>₦'.number_format($data['estimated_cost'] ?? 8450000).'</strong></td></tr>
        <tr><td>Estimated Payback Period</td><td>3.8 – 4.8 years</td></tr>
        <tr><td>Daily Solar Production (est.)</td><td>'.number_format(($data['daily_kwh'] ?? 28) * 1.1, 1).' kWh</td></tr>
    </table>

    <p style="margin-top:30px; font-size:13px; line-height:1.6;">
        <strong>Disclaimer:</strong> This proposal is based on the information provided. Final system design and pricing will be confirmed after a professional site assessment. 
        All installations come with a minimum 5-year warranty on major components.
    </p>

    <p style="margin-top:40px; text-align:center; color:#666;">
        KINAS GROUP • volt.kinasgroup.com • +234 809 555 0199<br>
        <strong>Our team will contact you within 24 hours to schedule a site visit.</strong>
    </p>';

    $mpdf->WriteHTML($html);
    
    // Save PDF
    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = $reference . '.pdf';
    $filepath = $uploadDir . $filename;
    
    $mpdf->Output($filepath, 'F');
    
    return $filepath;
}
