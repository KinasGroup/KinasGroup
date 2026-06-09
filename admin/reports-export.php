<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$db = Database::getInstance()->getConnection();
$rows = $db->prepare("
    SELECT a.created_at, a.action, a.details, u.name AS user_name, u.email AS user_email
    FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id
    WHERE a.created_at BETWEEN ? AND ?
    ORDER BY a.created_at DESC
");
$rows->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
$logs = $rows->fetchAll(PDO::FETCH_ASSOC);

$filename = 'activity-report-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Time', 'Action', 'Details', 'User Name', 'Email']);
foreach ($logs as $r) {
    fputcsv($out, [
        $r['created_at'],
        $r['action'],
        $r['details'] ?? '',
        $r['user_name'] ?? '',
        $r['user_email'] ?? '',
    ]);
}
fclose($out);
exit;
