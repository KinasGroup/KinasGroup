<?php
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
