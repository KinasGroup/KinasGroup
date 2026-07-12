<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Admin: Suspend User
 */

require_once '../includes/session.php';
require_once '../api/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: users.php?error=Invalid user ID');
    exit;
}

// Prevent admin from suspending themselves
if ($userId == $_SESSION['user_id']) {
    header('Location: users.php?error=You cannot suspend your own account');
    exit;
}

// Update user status to suspended
$update = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
$update->execute([$userId]);

header('Location: users.php?success=User suspended successfully');
exit;
