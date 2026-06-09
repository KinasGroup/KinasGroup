<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../api/config/database.php';
SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

$division = $_GET['division'] ?? '';
$status   = $_GET['status']   ?? '';
$search   = trim($_GET['q']   ?? '');

$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];

$out = [];
$agentMap = [];

foreach ($tableMap as $type => $table) {
    if ($division !== '' && $division !== $type) continue;

    $where = [];
    $params = [];
    if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    if ($search !== '') {
        $where[] = '(title LIKE ? OR id = ?)';
        $params[] = "%$search%";
        $params[] = is_numeric($search) ? (int)$search : 0;
    }
    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db->prepare("SELECT id, title, price, status, created_at, agent_id FROM $table $whereSQL ORDER BY created_at DESC LIMIT 5000");
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'type'      => $type,
            'id'        => $r['id'],
            'title'     => $r['title'],
            'price'     => $r['price'],
            'status'    => $r['status'],
            'created'   => $r['created_at'],
            'agent_id'  => $r['agent_id'],
        ];
        if ($r['agent_id']) $agentMap[(int)$r['agent_id']] = true;
    }
}

// Bulk load agents
$agentData = [];
if ($agentMap) {
    $ph = implode(',', array_fill(0, count($agentMap), '?'));
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id IN ($ph)");
    $stmt->execute(array_keys($agentMap));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $agentData[(int)$a['id']] = $a;
    }
}

$filename = 'listings-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$f = fopen('php://output', 'w');
fputcsv($f, ['Division', 'ID', 'Title', 'Price (NGN)', 'Status', 'Created', 'Agent ID', 'Agent Name', 'Agent Email']);
foreach ($out as $r) {
    $a = $agentData[(int)$r['agent_id']] ?? null;
    fputcsv($f, [
        $r['type'],
        '#' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT),
        $r['title'],
        $r['price'],
        $r['status'],
        $r['created'],
        $r['agent_id'],
        $a['name'] ?? '',
        $a['email'] ?? '',
    ]);
}
fclose($f);
exit;
