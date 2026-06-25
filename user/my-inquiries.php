<?php
/**
 * KINAS GROUP — My Inquiries (Redirected to Messages)
 * This page has been replaced by /user/messages.php
 */
require_once __DIR__ . '/../includes/session.php';
SessionManager::requireLogin();

// Redirect to messages page
header('Location: /user/messages.php');
exit;
