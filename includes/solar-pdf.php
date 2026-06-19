<?php
// includes/solar-pdf.php
// Production PDF generator for solar calculator submissions
// Generates professional solar proposals with KINAS VOLT branding

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

    $generationTime = date('F j, Y - h:i:s A');

    // Professional Header with LOGO
    $mpdf->SetHTMLHeader('
    <div style="text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F;">
        <img src="' . __DIR__ . '/../assets/images/logos/kinas-volt-logo.jpg" 
             style="max-height:60px; width:auto;" alt="KINAS VOLT">
        <div style="font-size:10px; color:#666; letter-spacing:2px; margin-top:4px;">POWERING A SUSTAINABLE FUTURE</div>
        <div style="font-size:8px; color:#999; margin-top:2px;">Gwarimpa, 900108, Federal Capital Territory, Abuja • +234 913 717 5523</div>
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

    // Decode appliances if they're JSON
    $appliances = $data['appliances'] ?? [];
    if (is_string($appliances)) {
        $appliances = json_decode($appliances, true);
    }
    if (!is_array($appliances)) {
        $appliances = [];
    }

    // Calculate total wattage from appliances
    $totalWattage = 0;
    foreach ($appliances as $appliance) {
        $totalWattage += ($appliance['quantity'] ?? 1) * ($appliance['watts'] ?? 0);
    }

    // Build the HTML content
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
            .generation-time { text-align: center; font-size: 9px; color: #999; margin-bottom: 15px; font-style: italic; }
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
            .financial-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11px; }
            .financial-table th { background: #1A3A2A; color: #FFFFFF; padding: 10px; text-align: left; font-weight: bold; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
            .financial-table td { padding: 8px 12px; border: 1px solid #E0E0E0; vertical-align: top; }
            .financial-table .subtotal-row { background: #FAFAFA; font-weight: bold; }
            .financial-table .total-row { background: #C6A43F; color: #0A0A0A; font-weight: bold; font-size: 13px; }
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
    </div>
    
    <div class="generation-time">
        <strong>Document Generated:</strong> ' . $generationTime . '
    </div>';

    // Customer Information Section
    $html .= '
    <div class="section-title">CUSTOMER INFORMATION</div>
    <table class="info-table">
        <tr><td>Full Name</td><td><strong>' . htmlspecialchars($data['full_name'] ?? 'N/A') . '</strong></td></tr>
        <tr><td>Email Address</td><td>' . htmlspecialchars($data['email'] ?? 'N/A') . '</td></tr>
        <tr><td>Phone Number</td><td>' . htmlspecialchars($data['phone'] ?? 'N/A') . '</td></tr>
        <tr><td>Location</td><td>' . htmlspecialchars($data['city_state'] ?? 'N/A') . '</td></tr>
        <tr><td>Property Type</td><td><strong>' . htmlspecialchars(ucfirst($data['property_type'] ?? 'N/A')) . '</strong></td></tr>
    </table>';

    // Appliances Section
    if (!empty($appliances)) {
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
            <tr class="total-row">
                <td colspan="3" align="right"><strong>TOTAL CONNECTED LOAD:</strong></td>
                <td><strong>' . number_format($totalWattage) . ' W</strong></td>
            </tr>
        </table>';
    }

    // Load Analysis Section
    $html .= '
    <div class="section-title">LOAD ANALYSIS SUMMARY</div>
    <table class="load-summary">
        <tr>
            <td>
                <div class="load-value">' . number_format($data['total_load_watts'] ?? $totalWattage) . ' W</div>
                <div class="load-label">Total Connected Load</div>
            </td>
            <td>
                <div class="load-value">' . number_format($data['daily_kwh'] ?? 0, 2) . ' kWh</div>
                <div class="load-label">Daily Energy Consumption</div>
            </td>
            <td>
                <div class="load-value">' . ($data['backup_hours'] ?? 24) . ' Hrs</div>
                <div class="load-label">Desired Backup Duration</div>
            </td>
        </tr>
    </table>';

    // Recommended System Section
    $systemSize = $data['system_size'] ?? ceil(($data['daily_kwh'] ?? 0) / 5);
    $panels = $data['recommended_panels'] ?? max(8, ceil($systemSize * 1000 / 550));
    
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
            <td><strong>' . number_format($panels) . ' Units</strong></td>
        </tr>
        <tr>
            <td><strong>Inverter</strong></td>
            <td>' . htmlspecialchars($data['recommended_inverter'] ?? ($systemSize <= 5 ? '8kVA' : ($systemSize <= 10 ? '12kVA' : '20kVA')) . ' Hybrid Inverter') . '<br><span class="spec-note">Pure sine wave • WiFi monitoring • 5-year warranty</span></td>
            <td><strong>1 Unit</strong></td>
        </tr>
        <tr>
            <td><strong>Battery Bank</strong></td>
            <td>' . htmlspecialchars($data['recommended_battery'] ?? '48V ' . max(100, ceil(($data['daily_kwh'] ?? 0) * 1.2 * 1000 / 48 / 100) * 100) . 'Ah LiFePO4') . '<br><span class="spec-note">6000+ cycles @80% DoD • 10-year warranty</span></td>
            <td><strong>' . ($data['battery_units'] ?? max(2, ceil(($data['daily_kwh'] ?? 0) * 1.5 / 10))) . ' Units</strong></td>
        </tr>
    </table>';

    // ============================================================
    // FINANCIAL BREAKDOWN SECTION
    // ============================================================
    // Define component prices (could be made dynamic based on data)
    $panelPrice = 450000;      // Per panel
    $inverterPrice = 3500000;  // Per inverter
    $batteryPrice = 2800000;   // Per battery unit
    $installationCost = 1500000;
    $cablingCost = 500000;
    $mountingCost = 400000;
    $transportCost = 200000;
    
    $panelTotal = $panelPrice * $panels;
    $batteryTotal = $batteryPrice * ($data['battery_units'] ?? 2);
    $subtotal = $panelTotal + $inverterPrice + $batteryTotal;
    $otherCosts = $installationCost + $cablingCost + $mountingCost + $transportCost;
    $grandTotal = $subtotal + $otherCosts;
    
    $html .= '
    <div class="section-title">📊 FINANCIAL BREAKDOWN</div>
    <table class="financial-table">
        <thead>
            <tr>
                <th width="55%">Item Description</th>
                <th width="20%">Unit Price (₦)</th>
                <th width="15%">Qty</th>
                <th width="25%">Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Solar Panels</strong><br><span style="font-size:9px; color:#888;">550W Monocrystalline PERC Tier-1</span></td>
                <td>' . number_format($panelPrice) . '</td>
                <td>' . $panels . '</td>
                <td><strong>' . number_format($panelTotal) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Inverter</strong><br><span style="font-size:9px; color:#888;">' . htmlspecialchars($data['recommended_inverter'] ?? '12kVA Hybrid Inverter') . '</span></td>
                <td>' . number_format($inverterPrice) . '</td>
                <td>1</td>
                <td><strong>' . number_format($inverterPrice) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Battery Bank</strong><br><span style="font-size:9px; color:#888;">' . htmlspecialchars($data['recommended_battery'] ?? '48V 400Ah LiFePO4') . '</span></td>
                <td>' . number_format($batteryPrice) . '</td>
                <td>' . ($data['battery_units'] ?? 2) . '</td>
                <td><strong>' . number_format($batteryTotal) . '</strong></td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="3" align="right"><strong>SUB TOTAL (Hardware)</strong></td>
                <td><strong>₦' . number_format($subtotal) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Installation &amp; Labour</strong><br><span style="font-size:9px; color:#888;">Professional installation by certified engineers</span></td>
                <td>' . number_format($installationCost) . '</td>
                <td>1</td>
                <td><strong>' . number_format($installationCost) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Cabling &amp; Accessories</strong><br><span style="font-size:9px; color:#888;">Solar cables, connectors, fuses, etc.</span></td>
                <td>' . number_format($cablingCost) . '</td>
                <td>1</td>
                <td><strong>' . number_format($cablingCost) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Mounting Structure</strong><br><span style="font-size:9px; color:#888;">Roof or ground mounting system</span></td>
                <td>' . number_format($mountingCost) . '</td>
                <td>1</td>
                <td><strong>' . number_format($mountingCost) . '</strong></td>
            </tr>
            <tr>
                <td><strong>Transport &amp; Logistics</strong><br><span style="font-size:9px; color:#888;">Delivery to site</span></td>
                <td>' . number_format($transportCost) . '</td>
                <td>1</td>
                <td><strong>' . number_format($transportCost) . '</strong></td>
            </tr>
            <tr class="subtotal-row">
                <td colspan="3" align="right"><strong>OTHER COSTS</strong></td>
                <td><strong>₦' . number_format($otherCosts) . '</strong></td>
            </tr>
            <tr class="total-row">
                <td colspan="3" align="right"><strong>GRAND TOTAL</strong></td>
                <td><strong>₦' . number_format($grandTotal) . '</strong></td>
            </tr>
        </tbody>
    </table>
    <div style="font-size:9px; color:#888; text-align:right; margin-top:-10px; margin-bottom:18px;">
        * All prices are in Nigerian Naira (₦) and inclusive of VAT where applicable.
    </div>';

    // Cost Summary Section
    $estimatedCost = $data['estimated_cost'] ?? $grandTotal;
    $monthlySavings = $data['monthly_savings'] ?? (($data['daily_kwh'] ?? 0) * 30 * 225);
    $paybackYears = $data['payback_years'] ?? ($estimatedCost / ($monthlySavings * 12));
    $roi = $data['roi'] ?? (($monthlySavings * 12 * 20) / $estimatedCost * 100);
    
    $html .= '
    <div class="section-title">INVESTMENT SUMMARY</div>
    <div class="cost-box">
        <div class="cost-label">TOTAL PROJECT INVESTMENT</div>
        <div class="cost-value">₦' . number_format($estimatedCost) . '</div>
        <div class="cost-note">Includes: Panels, Inverter, Batteries, Installation, Cabling, Mounting, Transport &amp; 2-Year Workmanship Warranty</div>
    </div>
    
    <table class="info-table">
        <tr>
            <td>Estimated Monthly Savings</td>
            <td><strong>₦' . number_format($monthlySavings) . '</strong> <span style="color:#888; font-size:9px;">(from reduced grid dependency)</span></td>
        </tr>
        <tr>
            <td>Estimated Annual Savings</td>
            <td><strong>₦' . number_format($monthlySavings * 12) . '</strong></td>
        </tr>
        <tr>
            <td>Estimated Payback Period</td>
            <td><strong>' . number_format($paybackYears, 1) . ' years</strong></td>
        </tr>
        <tr>
            <td>20-Year ROI</td>
            <td><strong>' . number_format($roi, 0) . '%</strong> <span class="green-badge">EXCELLENT</span></td>
        </tr>
        <tr>
            <td>Carbon Offset (20 years)</td>
            <td><strong>' . number_format(($data['co2_saved'] ?? 4.2) * 20, 1) . ' metric tons CO₂</strong> <span style="color:#888; font-size:9px;">(equivalent to planting 350 trees)</span></td>
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

    // Save PDF
    $uploadDir = __DIR__ . '/../uploads/solar-reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filepath = $uploadDir . $reference . '.pdf';
    $mpdf->Output($filepath, 'F');

    return $filepath;
}
