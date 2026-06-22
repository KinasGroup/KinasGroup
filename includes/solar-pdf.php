<?php
// includes/solar-pdf.php
// Production PDF generator with robust vendor loading

// Try multiple possible vendor paths
$vendorPaths = [
    __DIR__ . '/../vendor/autoload.php',
    '/var/www/html/vendor/autoload.php',
    '/app/vendor/autoload.php',
];

$vendorLoaded = false;
foreach ($vendorPaths as $vendorPath) {
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        $vendorLoaded = true;
        break;
    }
}

if (!$vendorLoaded) {
    error_log('Vendor autoload not found');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'System is being set up. Please try again in a few minutes.'
    ]);
    exit;
}

// Ensure mPDF temp directory exists and is writable
$tmpDir = __DIR__ . '/../vendor/mpdf/mpdf/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
}
if (!is_writable($tmpDir)) {
    chmod($tmpDir, 0777);
}

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/../uploads/solar-reports/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/**
 * Get the professional email header HTML (same as PDF header)
 */
function getSolarEmailHeader() {
    return '
    <div style="text-align:center; padding-bottom:16px; border-bottom:3px solid #C6A43F; margin-bottom:24px;">
        <img src="https://kinas-group.com/assets/images/logos/kinas-volt-logo.jpg" 
             style="max-height:60px; width:auto;" alt="KINAS VOLT">
        <div style="font-size:11px; color:#666; letter-spacing:2px; margin-top:6px; font-family: Arial, sans-serif;">POWERING A SUSTAINABLE FUTURE</div>
        <div style="font-size:9px; color:#999; margin-top:2px; font-family: Arial, sans-serif;">Gwarimpa, Abuja • +234 913 717 5523</div>
    </div>';
}

/**
 * Get the professional email footer HTML (same as PDF footer)
 */
function getSolarEmailFooter() {
    return '
    <div style="text-align:center; padding-top:16px; border-top:2px solid #E0E0E0; margin-top:24px; font-size:10px; color:#999; font-family: Arial, sans-serif;">
        KINAS VOLT • Solar Division<br>
        volt.kinasgroup.com
    </div>';
}

function generateSolarRecommendationPDF($data, $reference) {
    try {
        // Ensure temp directory exists
        $tmpDir = __DIR__ . '/../vendor/mpdf/mpdf/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        if (!is_writable($tmpDir)) {
            chmod($tmpDir, 0777);
        }
        
        $mpdf = new \Mpdf\Mpdf([
            'margin_top'    => 45,
            'margin_bottom' => 35,
            'margin_left'   => 20,
            'margin_right'  => 20,
            'format'        => 'A4',
            'default_font'  => 'dejavusans',
            'tempDir'       => $tmpDir
        ]);

        $generationTime = date('F j, Y - h:i:s A');

        // Decode appliances
        $appliances = $data['appliances'] ?? [];
        if (is_string($appliances)) {
            $appliances = json_decode($appliances, true);
        }
        if (!is_array($appliances)) {
            $appliances = [];
        }

        $totalWattage = 0;
        foreach ($appliances as $appliance) {
            $totalWattage += ($appliance['quantity'] ?? 1) * ($appliance['watts'] ?? 0);
        }

        // Professional Header with LOGO
        $mpdf->SetHTMLHeader('
        <div style="text-align:center; padding-bottom:12px; border-bottom:2px solid #C6A43F;">
            <img src="' . __DIR__ . '/../assets/images/logos/kinas-volt-logo.jpg" 
                 style="max-height:60px; width:auto;" alt="KINAS VOLT">
            <div style="font-size:10px; color:#666; letter-spacing:2px; margin-top:4px;">POWERING A SUSTAINABLE FUTURE</div>
            <div style="font-size:8px; color:#999; margin-top:2px;">Gwarimpa, Abuja • +234 913 717 5523</div>
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

        // Build HTML
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.5; color: #2C2C2C; font-size: 11px; }
                .proposal-title { text-align: center; margin: 20px 0 12px 0; }
                .proposal-title h1 { font-size: 24px; font-weight: bold; color: #C6A43F; margin: 0; }
                .ref-box { text-align: center; background: #F8F6F1; padding: 10px; margin-bottom: 20px; border: 1px solid #E8E0D0; }
                .section-title { font-size: 16px; font-weight: bold; color: #C6A43F; margin: 20px 0 12px 0; padding-bottom: 4px; border-bottom: 2px solid #C6A43F; }
                .info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11px; }
                .info-table td { padding: 8px 12px; border: 1px solid #E0E0E0; }
                .info-table td:first-child { width: 30%; background: #FAFAFA; font-weight: 600; }
                .load-summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; text-align: center; }
                .load-summary td { padding: 12px; border: 1px solid #E0E0E0; background: #FEFBF5; }
                .load-value { font-size: 20px; font-weight: bold; color: #C6A43F; }
                .load-label { font-size: 9px; color: #666; margin-top: 3px; text-transform: uppercase; }
                .system-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11px; }
                .system-table th { background: #C6A43F; color: #0A0A0A; padding: 10px; text-align: left; font-weight: bold; }
                .system-table td { padding: 10px; border: 1px solid #E0E0E0; }
                .cost-box { background: #FEFBF5; border: 2px solid #C6A43F; padding: 16px; text-align: center; margin: 18px 0; border-radius: 4px; }
                .cost-value { font-size: 28px; font-weight: bold; color: #C6A43F; }
                .appliance-table { width: 100%; border-collapse: collapse; margin: 12px 0 18px 0; font-size: 10px; }
                .appliance-table th { background: #F5F5F5; padding: 8px 10px; text-align: left; font-weight: bold; font-size: 9px; border: 1px solid #E0E0E0; }
                .appliance-table td { padding: 6px 10px; border: 1px solid #E0E0E0; }
                .appliance-table .total-row { background: #FAFAFA; font-weight: bold; }
                .badge { display: inline-block; background: #C6A43F; color: #0A0A0A; padding: 2px 12px; border-radius: 20px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
            </style>
        </head>
        <body>';

        // Title
        $html .= '
        <div class="proposal-title">
            <h1>SOLAR POWER SYSTEM PROPOSAL</h1>
            <p>Professional Energy Assessment &amp; Recommendation</p>
        </div>
        <div class="ref-box">
            <span style="color:#666;font-size:10px;">Reference:</span> <strong>' . $reference . '</strong>
            <span style="margin:0 12px;color:#CCC;">|</span>
            <span style="color:#666;font-size:10px;">Date:</span> <strong>' . date('F j, Y') . '</strong>
        </div>';

        // Customer Info
        $html .= '
        <div class="section-title">CUSTOMER INFORMATION</div>
        <table class="info-table">
            <tr><td>Full Name</td><td><strong>' . htmlspecialchars($data['full_name'] ?? 'N/A') . '</strong></td></tr>
            <tr><td>Email</td><td>' . htmlspecialchars($data['email'] ?? 'N/A') . '</td></tr>
            <tr><td>Phone</td><td>' . htmlspecialchars($data['phone'] ?? 'N/A') . '</td></tr>
            <tr><td>Location</td><td>' . htmlspecialchars($data['city_state'] ?? 'N/A') . '</td></tr>
            <tr><td>Property Type</td><td><strong>' . htmlspecialchars(ucfirst($data['property_type'] ?? 'N/A')) . '</strong></td></tr>
        </table>';

        // Appliances
        if (!empty($appliances)) {
            $html .= '
            <div class="section-title">APPLIANCES TO BE POWERED</div>
            <table class="appliance-table">
                <tr><th>Appliance</th><th width="15%">Qty</th><th width="20%">Watts</th><th width="25%">Total</th></tr>';
            
            $totalWattage = 0;
            foreach ($appliances as $appliance) {
                $total = ($appliance['quantity'] ?? 1) * ($appliance['watts'] ?? 0);
                $totalWattage += $total;
                $html .= '<tr><td><strong>' . htmlspecialchars($appliance['name'] ?? 'N/A') . '</strong></td><td>' . ($appliance['quantity'] ?? 1) . '</td><td>' . number_format($appliance['watts'] ?? 0) . ' W</td><td>' . number_format($total) . ' W</td></tr>';
            }
            $html .= '<tr class="total-row"><td colspan="3" align="right"><strong>TOTAL:</strong></td><td><strong>' . number_format($totalWattage) . ' W</strong></td></tr>';
            $html .= '</table>';
        }

        // Load Analysis
        $systemSize = $data['system_size'] ?? ceil(($data['daily_kwh'] ?? 0) / 5);
        $panels = $data['recommended_panels'] ?? max(8, ceil($systemSize * 1000 / 550));
        
        $html .= '
        <div class="section-title">LOAD ANALYSIS</div>
        <table class="load-summary">
            <tr>
                <td><div class="load-value">' . number_format($data['total_load_watts'] ?? $totalWattage) . ' W</div><div class="load-label">Total Load</div></td>
                <td><div class="load-value">' . number_format($data['daily_kwh'] ?? 0, 2) . ' kWh</div><div class="load-label">Daily Consumption</div></td>
                <td><div class="load-value">' . ($data['backup_hours'] ?? 24) . ' Hrs</div><div class="load-label">Backup Duration</div></td>
            </tr>
        </table>';

        // Recommended System
        $html .= '
        <div class="section-title">RECOMMENDED SYSTEM</div>
        <table class="system-table">
            <tr><th width="28%">Component</th><th>Specification</th><th width="18%">Quantity</th></tr>
            <tr><td><strong>Solar Panels</strong></td><td>550W Monocrystalline PERC Tier-1</td><td><strong>' . number_format($panels) . ' Units</strong></td></tr>
            <tr><td><strong>Inverter</strong></td><td>' . htmlspecialchars($data['recommended_inverter'] ?? '12kVA Hybrid Inverter') . '</td><td><strong>1 Unit</strong></td></tr>
            <tr><td><strong>Battery Bank</strong></td><td>' . htmlspecialchars($data['recommended_battery'] ?? '48V 400Ah LiFePO4') . '</td><td><strong>' . ($data['battery_units'] ?? 2) . ' Units</strong></td></tr>
        </table>';

        // Financial Breakdown
        $panelPrice = 450000;
        $inverterPrice = 3500000;
        $batteryPrice = 2800000;
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
        <div class="section-title">FINANCIAL BREAKDOWN</div>
        <table class="system-table">
            <tr><th>Item</th><th>Unit Price</th><th>Qty</th><th>Total</th></tr>
            <tr><td><strong>Solar Panels</strong></td><td>₦' . number_format($panelPrice) . '</td><td>' . $panels . '</td><td><strong>₦' . number_format($panelTotal) . '</strong></td></tr>
            <tr><td><strong>Inverter</strong></td><td>₦' . number_format($inverterPrice) . '</td><td>1</td><td><strong>₦' . number_format($inverterPrice) . '</strong></td></tr>
            <tr><td><strong>Battery Bank</strong></td><td>₦' . number_format($batteryPrice) . '</td><td>' . ($data['battery_units'] ?? 2) . '</td><td><strong>₦' . number_format($batteryTotal) . '</strong></td></tr>
            <tr style="background:#FAFAFA;font-weight:bold;"><td colspan="3" align="right">SUB TOTAL</td><td><strong>₦' . number_format($subtotal) . '</strong></td></tr>
            <tr><td><strong>Installation</strong></td><td>₦' . number_format($installationCost) . '</td><td>1</td><td><strong>₦' . number_format($installationCost) . '</strong></td></tr>
            <tr><td><strong>Cabling</strong></td><td>₦' . number_format($cablingCost) . '</td><td>1</td><td><strong>₦' . number_format($cablingCost) . '</strong></td></tr>
            <tr><td><strong>Mounting</strong></td><td>₦' . number_format($mountingCost) . '</td><td>1</td><td><strong>₦' . number_format($mountingCost) . '</strong></td></tr>
            <tr><td><strong>Transport</strong></td><td>₦' . number_format($transportCost) . '</td><td>1</td><td><strong>₦' . number_format($transportCost) . '</strong></td></tr>
            <tr style="background:#FAFAFA;font-weight:bold;"><td colspan="3" align="right">OTHER COSTS</td><td><strong>₦' . number_format($otherCosts) . '</strong></td></tr>
            <tr style="background:#C6A43F;color:#0A0A0A;font-weight:bold;font-size:13px;"><td colspan="3" align="right"><strong>GRAND TOTAL</strong></td><td><strong>₦' . number_format($grandTotal) . '</strong></td></tr>
        </table>';

        // Investment Summary
        $estimatedCost = $data['estimated_cost'] ?? $grandTotal;
        $monthlySavings = $data['monthly_savings'] ?? (($data['daily_kwh'] ?? 0) * 30 * 225);
        $paybackYears = $data['payback_years'] ?? ($estimatedCost / ($monthlySavings * 12));
        $roi = $data['roi'] ?? (($monthlySavings * 12 * 20) / $estimatedCost * 100);
        
        $html .= '
        <div class="section-title">INVESTMENT SUMMARY</div>
        <div class="cost-box">
            <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">Total Investment</div>
            <div class="cost-value">₦' . number_format($estimatedCost) . '</div>
        </div>
        <table class="info-table">
            <tr><td>Monthly Savings</td><td><strong>₦' . number_format($monthlySavings) . '</strong></td></tr>
            <tr><td>Payback Period</td><td><strong>' . number_format($paybackYears, 1) . ' years</strong></td></tr>
            <tr><td>20-Year ROI</td><td><strong>' . number_format($roi, 1) . '%</strong></td></tr>
            <tr><td>CO₂ Offset</td><td><strong>' . number_format(($data['co2_saved'] ?? 0), 2) . ' tons/year</strong></td></tr>
        </table>';

        // Thank You
        $html .= '
        <div style="text-align:center;margin-top:30px;padding-top:16px;border-top:1px solid #E0E0E0;">
            <div class="badge">POWERING AFRICA\'S FUTURE</div>
            <p style="margin-top:12px;font-size:11px;color:#666;">Thank you for choosing <strong style="color:#C6A43F;">KINAS VOLT</strong></p>
        </div>';

        $html .= '</body></html>';

        $mpdf->WriteHTML($html);

        // Save PDF
        $uploadDir = __DIR__ . '/../uploads/solar-reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filepath = $uploadDir . $reference . '.pdf';
        $mpdf->Output($filepath, 'F');

        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        throw $e;
    }
}
