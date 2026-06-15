<?php
require_once __DIR__ . '/../vendor/autoload.php';

function generateSolarRecommendationPDF($data, $reference) {
    $mpdf = new \Mpdf\Mpdf(['margin_top' => 50]);

    $html = "<h1>Solar Proposal - Ref: $reference</h1>";
    $html .= "<p>Customer: " . htmlspecialchars($data['full_name']) . "</p>";
    // Full professional HTML content here...

    $mpdf->WriteHTML($html);
    $filepath = __DIR__ . '/../uploads/solar-reports/' . $reference . '.pdf';
    $mpdf->Output($filepath, 'F');
    return $filepath;
}
