<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../includes/session.php';
require_once '../api/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agentId) {
    header('Location: agents.php?error=Invalid agent ID');
    exit;
}

// Update agent status to suspended
$update = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'agent'");
$update->execute([$agentId]);

header('Location: agents.php?success=Agent suspended successfully');
exit;
