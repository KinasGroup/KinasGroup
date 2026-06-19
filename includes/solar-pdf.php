<?php
// includes/solar-pdf.php
// Production PDF generator with robust vendor loading

// Try multiple possible vendor paths
$vendorPaths = [
    __DIR__ . '/../vendor/autoload.php',           // Normal path
    __DIR__ . '/../../vendor/autoload.php',        // Alternative path
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php', // Document root
    '/app/vendor/autoload.php',                    // Docker/Railway path
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
    // Log the error but don't die - show helpful message
    error_log('Vendor autoload not found. Please run: composer install');
    
    // Return a user-friendly error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'System is being set up. Please try again in a few minutes.',
        'error' => 'Vendor dependencies not found. Composer install is required.'
    ]);
    exit;
}

function generateSolarRecommendationPDF($data, $reference) {
    try {
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

        // [REST OF YOUR PDF GENERATION CODE HERE - SAME AS BEFORE]
        // ... (the full HTML generation code you already have)

        // Save PDF
        $uploadDir = __DIR__ . '/../uploads/solar-reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . $reference . '.pdf';
        $mpdf->Output($filepath, 'F');

        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        throw $e;
    }
}
