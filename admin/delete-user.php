<?php
/**
 * Admin: Delete User
 */

require_once '../includes/session.php';
require_once '../api/config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

// Get the user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: users.php?error=Invalid user ID');
    exit;
}

// Check if the user exists
$check = $db->prepare("SELECT id, email, role FROM users WHERE id = ?");
$check->execute([$userId]);
$user = $check->fetch();

if (!$user) {
    header('Location: users.php?error=User not found');
    exit;
}

// Prevent admin from deleting themselves
if ($userId == $_SESSION['user_id']) {
    header('Location: users.php?error=You cannot delete your own account');
    exit;
}

// Begin transaction
try {
    $db->beginTransaction();
    
    // If the user is an agent, delete agent profile first
    if ($user['role'] === 'agent') {
        // Delete from agent_profiles
        try {
            $deleteProfile = $db->prepare("DELETE FROM agent_profiles WHERE user_id = ?");
            $deleteProfile->execute([$userId]);
        } catch (Exception $e) {
            // Table might not exist, continue
        }
        
        // Delete agent's listings from all divisions
        $tables = ['solar_listings', 'car_listings', 'property_listings', 'marketplace_listings'];
        foreach ($tables as $table) {
            try {
                $deleteListings = $db->prepare("DELETE FROM $table WHERE agent_id = ?");
                $deleteListings->execute([$userId]);
            } catch (Exception $e) {
                // Table might not exist
            }
        }
    }
    
    // Delete the user
    $deleteUser = $db->prepare("DELETE FROM users WHERE id = ?");
    $deleteUser->execute([$userId]);
    
    // Commit transaction
    $db->commit();
    
    // Redirect back to users page with success message
    header('Location: users.php?success=User deleted successfully');
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    header('Location: users.php?error=Failed to delete user');
    exit;
}
