<?php
/**
 * GET /api/auth/check-username.php?username=xyz
 * Live username availability check for the registration forms.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/public-identity.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
Security::rateLimitDB('check_username_' . Security::getClientIP(), 30, 60);

$username = kinas_normalize_username($_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['available' => false, 'message' => 'Enter a username.']);
    exit;
}
$error = kinas_username_error($username);
if ($error !== null) {
    echo json_encode(['available' => false, 'username' => $username, 'message' => $error]);
    exit;
}
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Username check unavailable. Try again.']);
    exit;
}
$taken = kinas_username_taken($db, $username);
echo json_encode([
    'available' => !$taken,
    'username'  => $username,
    'message'   => $taken ? 'This username is already taken.' : '✓ Username is available.',
]);
