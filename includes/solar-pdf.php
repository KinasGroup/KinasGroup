<?php
// includes/solar-pdf.php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf([
        'margin_top'    => 45,
        'margin_bottom' => 35,
        'margin_left'   => 20,
        'margin_right'  => 20,
        'format'        => 'A4',
        'default_font'  => 'dejavusans'
    ]);

    // Professional Header with Gold Accent
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F;">
        <img src="'. __DIR__ .'/../assets/images/logos/kinas-volt-logo.jpg" 
             style="max-height:70px; width:auto;" alt="KINAS VOLT">
        <div style="margin-top:6px;">
            <span style="font-size:9px; color:#666; letter-spacing:1px;">POWERING A SUSTAINABLE FUTURE</span>
        </div>
    </div>');

    // Professional Footer
    $mpdf->SetHTMLFooter('
    <table width="100%" style="border-top:1px solid #E0E0E0; padding-top:8px; font-size:9px; color:#888;">
        <tr>
            <td width="33%">KINAS VOLT • Solar Division</td>
            <td width="34%" align="center">Gwarimpa, Abuja | +234 913 717 5523</td>
            <td width="33%" align="right">Page {PAGENO} of {nb}</td>
        </tr>
    </table>');

    // Build the HTML content
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            @page {
                margin: 0;
            }
            body {
                font-family: "dejavusans", "Helvetica", "Arial", sans-serif;
                line-height: 1.5;
                color: #2C2C2C;
            }
            .proposal-title {
                text-align: center;
                margin: 30px 0 15px 0;
            }
            .proposal-title h1 {
                font-size: 28px;
                font-weight: bold;
                color: #C6A43F;
                margin: 0;
                letter-spacing: 1px;
            }
            .proposal-title p {
                font-size: 12px;
                color: #666;
                margin: 8px 0 0 0;
            }
            .ref-box {
                text-align: center;
                background: #F8F6F1;
                padding: 12px;
                margin-bottom: 30px;
                border-radius: 4px;
            }
            .ref-box strong {
                color: #C6A43F;
                font-size: 13px;
            }
            .section-title {
                font-size: 18px;
                font-weight: bold;
                color: #C6A43F;
                margin: 25px 0 15px 0;
                padding-bottom: 6px;
                border-bottom: 2px solid #C6A43F;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 25px;
            }
            .info-table td {
                padding: 10px 12px;
                border: 1px solid #E0E0E0;
                vertical-align: top;
            }
            .info-table td:first-child {
                width: 35%;
                background: #FAFAFA;
                font-weight: 600;
            }
            .load-summary {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 25px;
                text-align: center;
            }
            .load-summary td {
                padding: 15px;
                border: 1px solid #E0E0E0;
            }
            .load-value {
                font-size: 22px;
                font-weight: bold;
                color: #C6A43F;
            }
            .load-label {
                font-size: 11px;
                color: #666;
                margin-top: 4px;
            }
            .system-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 25px;
            }
            .system-table th {
                background: #C6A43F;
                color: #0A0A0A;
                padding: 12px;
                text-align: left;
                font-weight: bold;
                font-size: 13px;
            }
            .system-table td {
                padding: 12px;
                border: 1px solid #E0E0E0;
                vertical-align: top;
            }
            .cost-box {
                background: #FEFBF5;
                border: 2px solid #C6A43F;
                padding: 20px;
                text-align: center;
                margin: 25px 0;
                border-radius: 8px;
            }
            .cost-label {
                font-size: 14px;
                color: #666;
                margin-bottom: 8px;
            }
            .cost-value {
                font-size: 32px;
                font-weight: bold;
                color: #C6A43F;
            }
            .cost-note {
                font-size: 10px;
                color: #999;
                margin-top: 8px;
            }
            .warranty-box {
                background: #F0F0F0;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #C6A43F;
            }
            .warranty-box strong {
                color: #C6A43F;
            }
            .footer-note {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #E0E0E0;
                font-size: 11px;
                color: #888;
            }
            .highlight {
                font-weight: bold;
                color: #C6A43F;
            }
            .appliance-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0 25px 0;
            }
            .appliance-table th {
                background: #F5F5F5;
                padding: 10px;
                text-align: left;
                font-weight: bold;
                font-size: 11px;
                border: 1px solid #E0E0E0;
            }
            .appliance-table td {
                padding: 8px 10px;
                border: 1px solid #E0E0E0;
                font-size: 12px;
            }
            .badge {
                display: inline-block;
                background: #C6A43F;
                color: #0A0A0A;
                padding: 2px 10px;
                border-radius: 15px;
                font-size: 9px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>';

    // Title Section
    $html .= '
    <div class="proposal-title">
        <h1>SOLAR POWER SYSTEM PROPOSAL</h1>
        <p>Professional Energy Assessment & Recommendation</p>
    </div>
    
    <div class="ref-box">
        <strong>Reference Number:</strong> ' . $reference . ' &nbsp;|&nbsp; 
        <strong>Date Issued:</strong> ' . date('F j, Y') . ' &nbsp;|&nbsp;
        <strong>Valid Until:</strong> ' . date('F j, Y', strtotime('+30 days')) . '
    </div>';

    // Customer Information Section
    $html .= '
    <div class="section-title">CUSTOMER INFORMATION</div>
    <table class="info-table">
        <tr><td>Full Name</td><td><strong>' . htmlspecialchars($data['full_name'] ?? 'N/A') . '</strong></td></tr>
        <tr><td>Email Address</td><td>' . htmlspecialchars($data['email'] ?? 'N/A') . '</td></tr>
        <tr><td>Phone Number</td><td>' . htmlspecialchars($data['phone'] ?? 'N/A') . '</td></tr>
        <tr><td>Location</td><td>' . htmlspecialchars($data['city_state'] ?? 'N/A') . '</td></tr>
        <tr><td>Property Type</td><td>' . htmlspecialchars(ucfirst($data['property_type'] ?? 'N/A')) . '</td></tr>
    </table>';

    // Appliances Section
    if (!empty($data['appliances'])) {
        $appliances = is_array($data['appliances']) ? $data['appliances'] : json_decode($data['appliances'], true);
        if (!empty($appliances)) {
            $html .= '
            <div class="section-title">APPLIANCES TO BE POWERED</div>
            <table class="appliance-table">
                <tr>
                    <th>APPLIANCE</th>
                    <th width="15%">QUANTITY</th>
                    <th width="20%">WATTAGE (W)</th>
                    <th width="25%">TOTAL POWER (W)</th>
                </tr>';
            $totalWattage = 0;
            foreach ($appliances as $appliance) {
                $total = ($appliance['quantity'] ?? 1) * ($appliance['watts'] ?? 0);
                $totalWattage += $total;
                $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($appliance['name'] ?? 'N/A') . '</strong></td>
                    <td>' . ($appliance['quantity'] ?? 1) . '</td>
                    <td>' . number_format($appliance['watts'] ?? 0) . ' W</td>
                    <td>' . number_format($total) . ' W</td>
                </tr>';
            }
            $html .= '
                <tr style="background:#FAFAFA; font-weight:bold;">
                    <td colspan="3" align="right"><strong>Total Connected Load:</strong></td>
                    <td><strong>' . number_format($totalWattage) . ' W</strong></td>
                </tr>
            </table>';
        }
    }

    // Load Analysis Section
    $dailyKwh = $data['daily_kwh'] ?? ($totalWattage * (($data['backup_hours'] ?? 24) / 24) / 1000);
    $html .= '
    <div class="section-title">LOAD ANALYSIS SUMMARY</div>
    <table class="load-summary">
        <tr>
            <td>
                <div class="load-value">' . number_format($data['total_load_watts'] ?? $totalWattage) . ' W</div>
                <div class="load-label">Total Connected Load</div>
            </td>
            <td>
                <div class="load-value">' . number_format($dailyKwh, 2) . ' kWh</div>
                <div class="load-label">Daily Energy Consumption</div>
            </td>
            <td>
                <div class="load-value">' . ($data['backup_hours'] ?? 24) . ' Hrs</div>
                <div class="load-label">Desired Backup Duration</div>
            </td>
        </tr>
    </table>';

    // Recommended System Section
    $systemSize = $data['system_size'] ?? ceil($dailyKwh / 5);
    $panels = $data['recommended_panels'] ?? max(8, ceil($systemSize * 1000 / 550));
    $html .= '
    <div class="section-title">RECOMMENDED SYSTEM CONFIGURATION</div>
    <table class="system-table">
        <tr>
            <th>Component</th>
            <th>Specification</th>
            <th width="20%">Quantity</th>
        </tr>
        <tr>
            <td><strong>Solar Panels</strong></td>
            <td>550W Monocrystalline PERC (Tier-1)<br><span style="font-size:10px; color:#888;">25-year performance warranty</span></td>
            <td><strong>' . number_format($panels) . ' Units</strong></td>
        </tr>
        <tr>
            <td><strong>Inverter</strong></td>
            <td>' . htmlspecialchars($data['recommended_inverter'] ?? ($systemSize <= 5 ? '8kVA' : ($systemSize <= 10 ? '12kVA' : '20kVA')) . ' Hybrid Inverter') . '<br><span style="font-size:10px; color:#888;">Pure sine wave, WiFi monitoring</span></td>
            <td><strong>1 Unit</strong></td>
        </tr>
        <tr>
            <td><strong>Battery Bank</strong></td>
            <td>' . htmlspecialchars($data['recommended_battery'] ?? '48V ' . max(100, ceil($dailyKwh * 1.2 * 1000 / 48 / 100) * 100) . 'Ah LiFePO4') . '<br><span style="font-size:10px; color:#888;">Deep cycle, 6000+ cycles @80% DoD</span></td>
            <td><strong>' . ($data['battery_units'] ?? max(2, ceil($dailyKwh * 1.5 / 10))) . ' Units</strong></td>
        </tr>
    </table>';

    // Cost Summary Section
    $estimatedCost = $data['estimated_cost'] ?? ($systemSize * 1200000);
    $monthlySavings = $data['monthly_savings'] ?? ($dailyKwh * 30 * 225);
    $paybackYears = $data['payback_years'] ?? ($estimatedCost / ($monthlySavings * 12));
    $roi20 = (($monthlySavings * 12 * 20) / $estimatedCost) * 100;
    
    $html .= '
    <div class="section-title">INVESTMENT SUMMARY</div>
    <div class="cost-box">
        <div class="cost-label">TOTAL PROJECT INVESTMENT</div>
        <div class="cost-value">₦' . number_format($estimatedCost) . '</div>
        <div class="cost-note">Includes: Panels, Inverter, Batteries, Installation, Cabling, & 2-Year Workmanship</div>
    </div>
    
    <table class="info-table">
        <tr><td>Estimated Monthly Savings</td><td><strong>₦' . number_format($monthlySavings) . '</strong> <span style="color:#888; font-size:11px;">(from reduced grid dependency)</span></td></tr>
        <tr><td>Estimated Annual Savings</td><td><strong>₦' . number_format($monthlySavings * 12) . '</strong></td></tr>
        <tr><td>Estimated Payback Period</td><td><strong>' . number_format($paybackYears, 1) . ' years</strong></td></tr>
        <tr><td>20-Year ROI</td><td><strong>' . number_format($roi20, 0) . '%</strong></td></tr>
        <tr><td>Carbon Offset (20 years)</td><td><strong>' . number_format(($dailyKwh * 0.85 / 2204.62) * 365 * 20, 1) . ' metric tons CO₂</strong></td></tr>
    </table>';

    // Warranty & Terms Section
    $html .= '
    <div class="warranty-box">
        <strong>🔒 WARRANTY COVERAGE</strong><br><br>
        • <strong>Solar Panels:</strong> 25-year performance warranty, 12-year product warranty<br>
        • <strong>Inverter:</strong> 5-year manufacturer warranty (extendable to 7 years)<br>
        • <strong>Battery Bank:</strong> 10-year warranty or 6,000 cycles (whichever comes first)<br>
        • <strong>Installation Workmanship:</strong> 2-year comprehensive warranty<br>
        • <strong>System Monitoring:</strong> Free lifetime access to mobile/web monitoring platform
    </div>';

    // Next Steps Section
    $html .= '
    <div class="section-title">NEXT STEPS</div>
    <table class="info-table">
        <tr><td width="30%">Step 1</td><td><strong>Review & Acceptance</strong><br>Review this proposal and confirm acceptance</td></tr>
        <tr><td>Step 2</td><td><strong>Free Site Assessment</strong><br>Our engineers will visit for detailed site survey</td></tr>
        <tr><td>Step 3</td><td><strong>Final Quotation</strong><br>Receive exact pricing based on site assessment</td></tr>
        <tr><td>Step 4</td><td><strong>Installation</strong><br>Professional installation (3-7 days depending on system size)</td></tr>
        <tr><td>Step 5</td><td><strong>Commissioning</strong><br>Final testing, training, and system handover</td></tr>
    </table>
    
    <div class="warranty-box" style="background:#FEFBF5; border-left-color:#C6A43F;">
        <strong>📋 IMPORTANT NOTES</strong><br><br>
        • This proposal is based on the information provided in your assessment.<br>
        • Final system design may vary after physical site inspection.<br>
        • Prices are subject to change based on market conditions and final specifications.<br>
        • Financing options are available — ask your representative for details.<br>
        • Government incentives and tax benefits may apply (subject to location).
    </div>';

    // Thank You Section
    $html .= '
    <div style="text-align:center; margin: 40px 0 30px 0; padding-top: 20px;">
        <div class="badge">POWERING AFRICA'S FUTURE</div>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            Thank you for considering <strong style="color:#C6A43F;">KINAS VOLT</strong> for your solar energy needs.<br>
            Our team will contact you within 24 hours to schedule your free site assessment.
        </p>
        <p style="margin-top: 20px; font-size: 11px; color: #999;">
            <em>This is a system-generated proposal. For inquiries, please contact:<br>
            📞 +234 913 717 5523 | ✉️ solar@kinasgroup.com</em>
        </p>
    </div>';

    $html .= '
    </body>
    </html>';

    $mpdf->WriteHTML($html);

    // Save PDF
    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filepath = $uploadDir . $reference . '.pdf';
    $mpdf->Output($filepath, 'F');

    return $filepath;
}
?>
        
