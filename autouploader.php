<?php
/**
 * AutoUploader for mPDF
 */

require_once __DIR__ . "/vendor/autoload.php";

use Mpdf\Mpdf;

$mpdf = new Mpdf();

function generatePDF($html, $filename = "output.pdf") {
    global $mpdf;
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, "F");
    return $filename;
}

echo "AutoUploader loaded successfully!\\n";
?>
