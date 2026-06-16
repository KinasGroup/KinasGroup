<?php
// generate-sample-pdf.php
// Place this in your project root: /workspaces/KinasGroup/

require_once __DIR__ . '/vendor/autoload.php';

use Mpdf\Mpdf;

function generateSampleSolarPDF() {
    // Sample data
    $sampleData = [
        'full_name' => 'Mr. John Adeyemi',
        'email' => 'john.adeyemi@example.com',
        'phone' => '+234 803 456 7890',
        'city_state' => 'Lekki Phase 1, Lagos',
        'property_type' => 'Duplex',
        'total_load_watts' => 4850,
        'daily_kwh' => 23.5,
        'backup_hours' => 24,
        'system_size' => 8.5,
        'recommended_panels' => 16,
        'recommended_inverter' => '12kVA Hybrid Inverter (Pure Sine Wave)',
        'recommended_battery' => '48V 400Ah Lithium LiFePO4 (2 Units)',
        'battery_units' => 2,
        'estimated_cost' => 14850000,
        'monthly_savings' => 158750,
        'payback_years' => 7.8,
        'roi' => 215,
        'co2_saved' => 4.2,
        'appliances' => [
            ['name' => 'Refrigerator (Energy Star)', 'quantity' => 2, 'watts' => 150],
            ['name' => 'LED Bulbs', 'quantity' => 12, 'watts' => 10],
            ['name' => 'Ceiling Fans', 'quantity' => 4, 'watts' => 70],
            ['name' => 'Air Conditioner (1.5HP)', 'quantity' => 2, 'watts' => 1200],
            ['name' => 'TV (55" LED)', 'quantity' => 2, 'watts' => 100],
            ['name' => 'Laptops', 'quantity' => 3, 'watts' => 50],
            ['name' => 'Microwave', 'quantity' => 1, 'watts' => 1000],
            ['name' => 'Water Pump', 'quantity' => 1, 'watts' => 750],
            ['name' => 'Washing Machine', 'quantity' => 1, 'watts' => 500],
        ]
    ];
    
    $reference = 'SOL-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
    
    $mpdf = new Mpdf([
        'margin_top'    => 45,
        'margin_bottom' => 35,
        'margin_left'   => 20,
        'margin_right'  => 20,
        'format'        => 'A4',
        'default_font'  => 'dejavusans'
    ]);

    // Professional Header
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F;">
        <div style="font-size:24px; font-weight:bold; color:#0A0A0A; font-family:Prata,serif;">KINAS VOLT</div>
        <div style="font-size:10px; color:#666; letter-spacing:2px; margin-top:2px;">POWERING A SUSTAINABLE FUTURE</div>
        <div style="font-size:8px; color:#999; margin-top:4px;">Gwarimpa, 900108, Federal Capital Territory, Abuja • +234 913 717 5523</div>
    </div>');

    // Professional Footer
    $mpdf->SetHTMLFooter('
    <table width="100%" style="border-top:1px solid #E0E0E0; padding-top:8px; font-size:8px; color:#999;">
        <tr>
            <td width="33%">KINAS VOLT • Solar Division</td>
            <td width="34%" align="center">volt.kinasgroup.com</td>
            <td width="33%" align="right">Page {PAGENO} of {nb}</td>
        </tr>
    </table>');

    // Build the HTML content (full comprehensive version)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: "dejavusans", "Helvetica", "Arial", sans-serif; line-height: 1.5; color: #2C2C2C; font-size: 11px; }
            .proposal-title { text-align: center; margin: 20px 0 12px 0; }
            .proposal-title h1 { font-size: 24px; font-weight: bold; color: #C6A43F; margin: 0; letter-spacing: 1px; }
            .proposal-title p { font-size: 11px; color: #666; margin: 4px 0 0 0; }
            .ref-box { text-align: center; background: #F8F6F1; padding: 10px; margin-bottom: 20px; border: 1px solid #E8E0D0; }
            .ref-box strong { color: #C6A43F; font-size: 12px; }
            .ref-box .label { color: #666; font-size: 10px; }
            .section-title { font-size: 16px; font-weight: bold; color: #C6A43F; margin: 20px 0 12px 0; padding-bottom: 4px; border-bottom: 2px solid #C6A43F; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11px; }
            .info-table td { padding: 8px 12px; border: 1px solid #E0E0E0; vertical-align: top; }
            .info-table td:first-child { width: 30%; background: #FAFAFA; font-weight: 600; font-size: 10px; text-transform: uppercase; color: #555; letter-spacing: 0.5px; }
            .load-summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; text-align: center; }
            .load-summary td { padding: 12px; border: 1px solid #E0E0E0; background: #FEFBF5; }
            .load-value { font-size: 20px; font-weight: bold; color: #C6A43F; }
            .load-label { font-size: 9px; color: #666; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.5px; }
            .system-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11px; }
            .system-table th { background: #C6A43F; color: #0A0A0A; padding: 10px; text-align: left; font-weight: bold; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
            .system-table td { padding: 10px; border: 1px solid #E0E0E0; vertical-align: top; }
            .system-table .spec-note { font-size: 9px; color: #888; }
            .cost-box { background: #FEFBF5; border: 2px solid #C6A43F; padding: 16px; text-align: center; margin: 18px 0; border-radius: 4px; }
            .cost-label { font-size: 11px; color: #666; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; }
            .cost-value { font-size: 28px; font-weight: bold; color: #C6A43F; }
            .cost-note { font-size: 9px; color: #999; margin-top: 4px; }
            .warranty-box { background: #F0F0F0; padding: 14px; margin: 16px 0; border-left: 4px solid #C6A43F; font-size: 10px; }
            .warranty-box strong { color: #C6A43F; }
            .appliance-table { width: 100%; border-collapse: collapse; margin: 12px 0 18px 0; font-size: 10px; }
            .appliance-table th { background: #F5F5F5; padding: 8px 10px; text-align: left; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #E0E0E0; color: #555; }
            .appliance-table td { padding: 6px 10px; border: 1px solid #E0E0E0; }
            .appliance-table .total-row { background: #FAFAFA; font-weight: bold; }
            .badge { display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 2px 12px; border-radius: 20px; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
            .green-badge { display: inline-block; background: #E8F5E9; color: #2E7D32; padding: 2px 10px; border-radius: 12px; font-size: 8px; font-weight: bold; }
        </style>
    </head>
    <body>';

    // Title Section
    $html .= '
    <div class="proposal-title">
        <h1>SOLAR POWER SYSTEM PROPOSAL</h1>
        <p>Professional Energy Assessment &amp; Recommendation</p>
    </div>
    
    <div class="ref-box">
        <span class="label">Reference Number:</span> <strong>' . $reference . '</strong>
        <span style="margin:0 12px; color:#CCC;">|</span>
        <span class="label">Date Issued:</span> <strong>' . date('F j, Y') . '</strong>
        <span style="margin:0 12px; color:#CCC;">|</span>
        <span class="label">Valid Until:</span> <strong>' . date('F j, Y', strtotime('+30 days')) . '</strong>
    </div>';

    // Customer Information Section
    $html .= '
    <div class="section-title">CUSTOMER INFORMATION</div>
    <table class="info-table">
        <tr><td>Full Name</td><td><strong>' . htmlspecialchars($sampleData['full_name']) . '</strong></td></tr>
        <tr><td>Email Address</td><td>' . htmlspecialchars($sampleData['email']) . '</td></tr>
        <tr><td>Phone Number</td><td>' . htmlspecialchars($sampleData['phone']) . '</td></tr>
        <tr><td>Location</td><td>' . htmlspecialchars($sampleData['city_state']) . '</td></tr>
        <tr><td>Property Type</td><td><strong>' . htmlspecialchars(ucfirst($sampleData['property_type'])) . '</strong></td></tr>
    </table>';

    // Appliances Section
    $html .= '
    <div class="section-title">APPLIANCES TO BE POWERED</div>
    <table class="appliance-table">
        <tr>
            <th>Appliance</th>
            <th width="15%">Qty</th>
            <th width="20%">Wattage (W)</th>
            <th width="25%">Total Power (W)</th>
        </tr>';
    
    $totalWattage = 0;
    foreach ($sampleData['appliances'] as $appliance) {
        $total = ($appliance['quantity']) * ($appliance['watts']);
        $totalWattage += $total;
        $html .= '
        <tr>
            <td><strong>' . htmlspecialchars($appliance['name']) . '</strong></td>
            <td>' . $appliance['quantity'] . '</td>
            <td>' . number_format($appliance['watts']) . ' W</td>
            <td>' . number_format($total) . ' W</td>
        </tr>';
    }
    
    $html .= '
        <tr class="total-row">
            <td colspan="3" align="right"><strong>TOTAL CONNECTED LOAD:</strong></td>
            <td><strong>' . number_format($totalWattage) . ' W</strong></td>
        </tr>
    </table>';

    // Load Analysis Section
    $html .= '
    <div class="section-title">LOAD ANALYSIS SUMMARY</div>
    <table class="load-summary">
        <tr>
            <td>
                <div class="load-value">' . number_format($sampleData['total_load_watts']) . ' W</div>
                <div class="load-label">Total Connected Load</div>
            </td>
            <td>
                <div class="load-value">' . number_format($sampleData['daily_kwh'], 2) . ' kWh</div>
                <div class="load-label">Daily Energy Consumption</div>
            </td>
            <td>
                <div class="load-value">' . $sampleData['backup_hours'] . ' Hrs</div>
                <div class="load-label">Desired Backup Duration</div>
            </td>
        </tr>
    </table>';

    // Recommended System Section
    $html .= '
    <div class="section-title">RECOMMENDED SYSTEM CONFIGURATION</div>
    <table class="system-table">
        <tr>
            <th width="28%">Component</th>
            <th>Specification</th>
            <th width="18%">Quantity</th>
        </tr>
        <tr>
            <td><strong>Solar Panels</strong></td>
            <td>550W Monocrystalline PERC (Tier-1)<br><span class="spec-note">25-year performance warranty • 21.5% efficiency</span></td>
            <td><strong>' . number_format($sampleData['recommended_panels']) . ' Units</strong></td>
        </tr>
        <tr>
            <td><strong>Inverter</strong></td>
            <td>' . htmlspecialchars($sampleData['recommended_inverter']) . '<br><span class="spec-note">Pure sine wave • WiFi monitoring • 5-year warranty</span></td>
            <td><strong>1 Unit</strong></td>
        </tr>
        <tr>
            <td><strong>Battery Bank</strong></td>
            <td>' . htmlspecialchars($sampleData['recommended_battery']) . '<br><span class="spec-note">6000+ cycles @80% DoD • 10-year warranty</span></td>
            <td><strong>' . $sampleData['battery_units'] . ' Units</strong></td>
        </tr>
    </table>';

    // Cost Summary Section
    $html .= '
    <div class="section-title">INVESTMENT SUMMARY</div>
    <div class="cost-box">
        <div class="cost-label">TOTAL PROJECT INVESTMENT</div>
        <div class="cost-value">₦' . number_format($sampleData['estimated_cost']) . '</div>
        <div class="cost-note">Includes: Panels, Inverter, Batteries, Installation, Cabling, &amp; 2-Year Workmanship Warranty</div>
    </div>
    
    <table class="info-table">
        <tr>
            <td>Estimated Monthly Savings</td>
            <td><strong>₦' . number_format($sampleData['monthly_savings']) . '</strong> <span style="color:#888; font-size:9px;">(from reduced grid dependency)</span></td>
        </tr>
        <tr>
            <td>Estimated Annual Savings</td>
            <td><strong>₦' . number_format($sampleData['monthly_savings'] * 12) . '</strong></td>
        </tr>
        <tr>
            <td>Estimated Payback Period</td>
            <td><strong>' . number_format($sampleData['payback_years'], 1) . ' years</strong></td>
        </tr>
        <tr>
            <td>20-Year ROI</td>
            <td><strong>' . number_format($sampleData['roi'], 0) . '%</strong> <span class="green-badge">EXCELLENT</span></td>
        </tr>
        <tr>
            <td>Carbon Offset (20 years)</td>
            <td><strong>' . number_format($sampleData['co2_saved'] * 20, 1) . ' metric tons CO₂</strong> <span style="color:#888; font-size:9px;">(equivalent to planting 350 trees)</span></td>
        </tr>
    </table>';

    // Warranty & Terms Section
    $html .= '
    <div class="warranty-box">
        <strong>🔒 WARRANTY COVERAGE</strong><br><br>
        • <strong>Solar Panels:</strong> 25-year performance warranty (90% @ 10 years, 80% @ 25 years)<br>
        • <strong>Inverter:</strong> 5-year manufacturer warranty (extendable to 7 years)<br>
        • <strong>Battery Bank:</strong> 10-year warranty or 6,000 cycles (whichever comes first)<br>
        • <strong>Installation Workmanship:</strong> 2-year comprehensive warranty<br>
        • <strong>System Monitoring:</strong> Free lifetime access to mobile/web monitoring platform
    </div>';

    // Next Steps Section
    $html .= '
    <div class="section-title">NEXT STEPS</div>
    <table class="info-table">
        <tr><td width="25%"><strong>Step 1</strong></td><td><strong>Review &amp; Acceptance</strong><br><span style="font-size:9px; color:#666;">Review this proposal and confirm acceptance</span></td></tr>
        <tr><td><strong>Step 2</strong></td><td><strong>Free Site Assessment</strong><br><span style="font-size:9px; color:#666;">Our engineers will visit for detailed site survey</span></td></tr>
        <tr><td><strong>Step 3</strong></td><td><strong>Final Quotation</strong><br><span style="font-size:9px; color:#666;">Receive exact pricing based on site assessment</span></td></tr>
        <tr><td><strong>Step 4</strong></td><td><strong>Professional Installation</strong><br><span style="font-size:9px; color:#666;">Installation completed within 3-7 days</span></td></tr>
        <tr><td><strong>Step 5</strong></td><td><strong>Commissioning &amp; Handover</strong><br><span style="font-size:9px; color:#666;">Final testing, training, and system handover</span></td></tr>
    </table>';

    // Important Notes
    $html .= '
    <div style="background:#FEFBF5; border-left:4px solid #C6A43F; padding:14px; margin:16px 0;">
        <strong>📋 IMPORTANT NOTES</strong><br><br>
        <span style="font-size:9px; color:#666;">
        • This proposal is based on the information provided in your assessment.<br>
        • Final system design may vary after physical site inspection.<br>
        • Prices are subject to change based on market conditions and final specifications.<br>
        • Financing options are available — ask your representative for details.<br>
        • Government incentives and tax benefits may apply (subject to location).
        </span>
    </div>';

    // Thank You Section
    $html .= '
    <div style="text-align:center; margin: 30px 0 20px 0; padding-top: 16px; border-top: 1px solid #E0E0E0;">
        <div class="badge">POWERING AFRICA\'S FUTURE</div>
        <p style="margin-top: 12px; font-size: 11px; color: #666;">
            Thank you for choosing <strong style="color:#C6A43F;">KINAS VOLT</strong> for your solar energy needs.<br>
            Our team will contact you within <strong>24 hours</strong> to schedule your free site assessment.
        </p>
        <p style="margin-top: 12px; font-size: 9px; color: #999;">
            📞 +234 913 717 5523 &nbsp;|&nbsp; ✉️ solar@kinasgroup.com &nbsp;|&nbsp; 🌐 volt.kinasgroup.com
        </p>
    </div>';

    $html .= '
    </body>
    </html>';

    $mpdf->WriteHTML($html);

    // Generate the PDF
    $outputDir = __DIR__ . '/generated-pdfs/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $filename = 'sample-solar-proposal-' . date('Y-m-d') . '.pdf';
    $filepath = $outputDir . $filename;
    
    $mpdf->Output($filepath, 'F');
    
    return $filepath;
}

// Run the generator
try {
    // Check if mPDF is installed
    if (!class_exists('Mpdf\Mpdf')) {
        throw new Exception('mPDF is not installed. Run: composer require mpdf/mpdf');
    }
    
    $pdfPath = generateSampleSolarPDF();
    echo "✅ PDF generated successfully!\n";
    echo "📄 File: " . $pdfPath . "\n";
    echo "📊 Size: " . number_format(filesize($pdfPath)) . " bytes\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Make sure you have run: composer require mpdf/mpdf\n";
}
