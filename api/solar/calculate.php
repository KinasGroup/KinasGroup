<?php
// api/solar/calculate.php
// Solar calculator API endpoint - with proper error handling

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/solar-pdf.php';

header('Content-Type: application/json');

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    // [REST OF YOUR VALIDATION AND CALCULATION CODE]
    // ... (the full API code you already have)

    // Generate PDF
    try {
        $pdfPath = generateSolarRecommendationPDF($pdfData, $reference);
    } catch (Exception $pdfError) {
        error_log('PDF Generation Error: ' . $pdfError->getMessage());
        throw new Exception('Unable to generate PDF. Our team has been notified.');
    }

    // Send emails
    // ... (email sending code)

    echo json_encode([
        'success' => true,
        'message' => 'Proposal generated successfully! Check your email for the PDF.',
        'reference' => $reference,
        'pdf_url' => $pdfUrl
    ]);

} catch (Exception $e) {
    error_log('Solar Calculator Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
