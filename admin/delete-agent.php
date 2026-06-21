<?php
/**
 * Admin: Delete Agent
 */

require_once '../includes/session.php';
require_once '../api/config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Get the agent ID from URL
$agentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$agentId) {
    header('Location: agents.php?error=Invalid agent ID');
    exit;
}

// Check if the user exists and is an agent
$check = $db->prepare("SELECT id, email, role FROM users WHERE id = ? AND role = 'agent'");
$check->execute([$agentId]);
$agent = $check->fetch();

if (!$agent) {
    header('Location: agents.php?error=Agent not found');
    exit;
}

// Prevent deleting Super Admin
if ($agent['role'] === 'admin') {
    header('Location: agents.php?error=Cannot delete Super Admin');
    exit;
}

// Begin transaction
try {
    $db->beginTransaction();
    
    // Delete from agent_profiles
    try {
        $deleteProfile = $db->prepare("DELETE FROM agent_profiles WHERE user_id = ?");
        $deleteProfile->execute([$agentId]);
    } catch (Exception $e) {
        // Table might not exist, continue
    }
    
    // Delete agent's listings from all divisions
    $tables = ['solar_listings', 'car_listings', 'property_listings', 'marketplace_listings'];
    foreach ($tables as $table) {
        try {
            $deleteListings = $db->prepare("DELETE FROM $table WHERE agent_id = ?");
            $deleteListings->execute([$agentId]);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // Delete the user
    $deleteUser = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'agent'");
    $deleteUser->execute([$agentId]);
    
    // Commit transaction
    $db->commit();
    
    // Redirect back to agents page with success message
    header('Location: agents.php?success=Agent deleted successfully');
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    header('Location: agents.php?error=Failed to delete agent');
    exit;
}
