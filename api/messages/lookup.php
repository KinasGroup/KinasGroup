<?php
/**
* Lookup a user by email OR username for the compose-to form.
* Returns the user id and the PUBLIC display name (@username).
*/
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/public-identity.php';
SessionManager::requireLogin();
$q = trim($_GET['q'] ?? $_GET['email'] ?? $_GET['username'] ?? '');
if ($q === '') {
http_response_code(422);
echo json_encode(['success' => false, 'error' => 'Provide an email or username']);
exit;
}
$isEmail = filter_var($q, FILTER_VALIDATE_EMAIL);
$username = $isEmail ? null : kinas_normalize_username($q);
if (!$isEmail && $username === '') {
http_response_code(422);
echo json_encode(['success' => false, 'error' => 'Invalid username']);
exit;
}
try {
$db = Database::getInstance()->getConnection();
if ($isEmail) {
$stmt = $db->prepare("SELECT id, name, username, email FROM users WHERE email = ? LIMIT 1");
$stmt->execute([strtolower($q)]);
} else {
$stmt = $db->prepare("SELECT id, name, username, email FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
}
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'No user found with that ' . ($isEmail ? 'email' : 'username')]);
exit;
}
echo json_encode([
'success'  => true,
'user_id'  => (int)$u['id'],
'name'     => kinas_public_display_name($u['username'] ?? null, $u['name'] ?? ''),
'username' => $u['username'] ?? null,
'email'    => $u['email'],
]);
} catch (Exception $e) {
http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Lookup failed']);
}
