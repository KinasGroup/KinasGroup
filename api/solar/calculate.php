<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$data = $_POST;
// Perform calculations
$reference = 'KV-SOL-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));

$pdfPath = generateSolarRecommendationPDF($data, $reference);

echo json_encode([
    'success' => true,
    'reference' => $reference,
    'pdf' => $pdfPath
]);
