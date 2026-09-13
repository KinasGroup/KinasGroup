<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

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

$check = $db->prepare("SELECT id, email, role, status FROM users WHERE id = ?");
$check->execute([$userId]);
$user = $check->fetch();

if (!$user) {
    header('Location: users.php?error=User not found');
    exit;
}

if ($userId == $_SESSION['user_id']) {
    header('Location: users.php?error=You cannot delete your own account');
    exit;
}

if (($user['status'] ?? '') === 'deleted') {
    header('Location: users.php?error=Account is already deleted');
    exit;
}

try {
    $db->beginTransaction();

    // 1. Soft delete the user (DO NOT hard delete, or reactivation becomes impossible)
    $db->prepare("UPDATE users SET status = 'deleted', deleted_by = 'admin', deleted_at = NOW() WHERE id = ?")
       ->execute([$userId]);

    // 2. If the user is an agent, suspend profile and hide listings
    if ($user['role'] === 'agent') {
        try {
            $db->prepare("UPDATE agent_profiles SET verification_status = 'suspended' WHERE user_id = ?")
               ->execute([$userId]);
        } catch (Exception $e) {}

        $tables = ['solar_listings', 'car_listings', 'property_listings', 'marketplace_listings'];
        foreach ($tables as $table) {
            try {
                $db->prepare("UPDATE $table SET status = 'removed' WHERE agent_id = ? AND status NOT IN ('sold','rented')")
                   ->execute([$userId]);
            } catch (Exception $e) {}
        }
    }

    // 3. Invalidate active sessions
    try {
        $db->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$userId]);
    } catch (Exception $e) {}

    $db->commit();

    header('Location: users.php?success=User account deactivated successfully');
    exit;
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin delete-user error: ' . $e->getMessage());
    header('Location: users.php?error=Failed to delete user');
    exit;
}
