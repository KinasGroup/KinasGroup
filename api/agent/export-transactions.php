<?php
/**
 * Agent: export transactions as CSV.
 */
require_once '../config/database.php';
require_once '../../includes/session.php';

SessionManager::requireAgent();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-12 months'));
$to   = $_GET['to']   ?? date('Y-m-d');

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT t.*, u.name AS listing_title
    FROM transactions t
    WHERE t.agent_id = ?
      AND DATE(t.created_at) BETWEEN ? AND ?
    ORDER BY t.created_at DESC
");
$stmt->execute([(int)$_SESSION['user_id'], $from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'transactions-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Listing Type', 'Listing ID', 'Buyer', 'Buyer Email', 'Amount (NGN)', 'Commission %', 'Commission (NGN)', 'Status', 'Paid At']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['created_at'],
        $r['listing_type'],
        '#' . str_pad((string)$r['listing_id'], 4, '0', STR_PAD_LEFT),
        $r['buyer_name'] ?? '',
        $r['buyer_email'] ?? '',
        number_format((float)$r['amount'], 2, '.', ''),
        $r['commission_pct'] . '%',
        number_format((float)$r['commission'], 2, '.', ''),
        $r['status'],
        $r['paid_at'] ?? '',
    ]);
}
fclose($out);
exit;
