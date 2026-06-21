<?php
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

// Update agent status to active
$update = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'agent'");
$update->execute([$agentId]);

header('Location: agents.php?success=Agent activated successfully');
exit;
