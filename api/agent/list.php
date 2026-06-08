<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$offset = ($page - 1) * $limit;
$division = $_GET['division'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    $sql = "SELECT a.*, u.name, u.email, u.verified FROM agent_profiles a JOIN users u ON a.user_id = u.id";
    $params = [];
    
    if ($division) {
        $sql .= " WHERE a.division = ?";
        $params[] = $division;
    }
    
    $sql .= " ORDER BY a.created_at DESC LIMIT $offset, $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agents = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'agents' => $agents, 'page' => $page]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch agents']);
}