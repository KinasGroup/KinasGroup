<?php
/**
 * Lookup a user by email for the compose-to form.
 * Returns the user id and display name (or 404 if not found).
 */
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireLogin();

$email = trim($_GET['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        http_response_code(404);
        echo json_encode(['error' => 'No user with that email', 'success' => false]);
        exit;
    }
    echo json_encode([
        'success'  => true,
        'user_id'  => (int)$u['id'],
        'name'     => $u['name'],
        'email'    => $u['email'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lookup failed']);
}
