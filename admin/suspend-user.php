<?php
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
