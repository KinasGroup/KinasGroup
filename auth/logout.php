<?php
// Authenticated, per-session content — never cache this page. Without
// this, a browser or CDN (e.g. Cloudflare) could keep serving a stale
// snapshot indefinitely after data changes (deletes, status updates,
// etc.), which is exactly what made this dashboard look like it wasn't
// updating.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';

if (SessionManager::isLoggedIn()) {
    // Invalidate server-side token
    try {
        $userId = SessionManager::getUserId();
        $db = Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$userId]);
        Security::logActivity($userId, 'logout', 'User signed out');
    } catch (Exception $e) {
        error_log('Logout DB error: ' . $e->getMessage());
    }
    SessionManager::logout();
}

header('Location: /auth/login.php');
exit;
