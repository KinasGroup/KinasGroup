<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireAdmin();

try {
    $db = Database::getInstance()->getConnection();
    
    $pendingApprovals = $db->query("SELECT COUNT(*) as count FROM agent_profiles WHERE verification_status = 'pending'")->fetch()['count'];
    $totalAgents = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'")->fetch()['count'];
    $activeListings = $db->query("SELECT (SELECT COUNT(*) FROM car_listings WHERE status = 'active') + (SELECT COUNT(*) FROM property_listings WHERE status = 'active') + (SELECT COUNT(*) FROM marketplace_listings WHERE status = 'active') as count")->fetch()['count'];
    $flaggedItems = $db->query("SELECT (SELECT COUNT(*) FROM car_listings WHERE status = 'flagged') + (SELECT COUNT(*) FROM property_listings WHERE status = 'flagged') + (SELECT COUNT(*) FROM marketplace_listings WHERE status = 'flagged') as count")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'pendingApprovals' => $pendingApprovals,
        'totalAgents' => $totalAgents,
        'activeListings' => $activeListings,
        'flaggedItems' => $flaggedItems
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch stats']);
}