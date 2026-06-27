<?php
/**
 * Get a new CSRF token
 * GET /api/auth/csrf-token.php
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$csrf_token = Security::generateCSRFToken();

echo json_encode([
    'success' => true,
    'csrf_token' => $csrf_token
]);
