<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

$from      = $_GET['from']  ?? date('Y-m-d', strtotime('-30 days'));
$to        = $_GET['to']    ?? date('Y-m-d');
$action    = trim($_GET['action'] ?? '');
$userQuery = trim($_GET['user'] ?? '');

$where = ["DATE(a.created_at) BETWEEN ? AND ?"];
$params = [$from, $to];
if ($action !== '')         { $where[] = "a.action LIKE ?";    $params[] = "%$action%"; }
if ($userQuery !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$userQuery%";
    $params[] = "%$userQuery%";
}
$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT a.created_at, COALESCE(u.email,'(anonymous)') AS user_email, u.name AS user_name,
           a.action, a.details, a.ip_address
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $whereSQL
    ORDER BY a.created_at DESC
    LIMIT 10000
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'activity-logs-' . date('Y-m-d-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Time', 'User Name', 'Email', 'Action', 'Details', 'IP']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['created_at'],
        $r['user_name'] ?? '',
        $r['user_email'],
        $r['action'],
        $r['details'] ?? '',
        $r['ip_address'] ?? '',
    ]);
}
fclose($out);
exit;
