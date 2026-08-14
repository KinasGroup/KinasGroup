<?php
/**
* KINAS GROUP — Chat Thread Endpoint (revamped)
*
* REVAMPED POLICY (replaces the "thread always stays open" amendment):
*   • Listing status removed/inactive (or row deleted) => thread CLOSED.
*     History stays readable; can_reply = false; closed_reason returned
*     so the client shows the proper locked-composer notice.
*   • listing=0 opens are RESOLVED to the pair's most recent listing
*     thread — messages from different listings can never mix again.
*   • Messages now carry inquiry_meta for the formal inquiry card.
*
* AMENDED: participant names returned to the client are the PUBLIC
* identity (@username); legal names stay private.
*/
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/public-identity.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
exit;
}
if (!SessionManager::isLoggedIn()) {
http_response_code(401);
echo json_encode(['success' => false, 'error' => 'Please log in.']);
exit;
}
define('CHAT_CLOSED_STATUSES', ['removed', 'inactive']);
function chat_media_urls($raw): array
{
$raw = (string)($raw ?? '');
if ($raw === '') return [];
if ($raw[0] === '[') {
$arr = json_decode($raw, true);
return is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
}
return [$raw];
}
function chat_date_label($datetime): string
{
$ts = strtotime((string)$datetime);
if (!$ts) return '';
$today = strtotime('today');
if ($ts >= $today) return 'Today';
if ($ts >= strtotime('-1 day', $today)) return 'Yesterday';
return date('M j, Y', $ts);
}
$userId    = (int)SessionManager::getUserId();
$otherId   = (int)($_GET['other_user'] ?? 0);
$listingId = (int)($_GET['listing'] ?? 0);
$sinceId   = max(0, (int)($_GET['since_id'] ?? 0));
if ($otherId <= 0 || $otherId === $userId) {
http_response_code(422);
echo json_encode(['success' => false, 'error' => 'Invalid conversation.']);
exit;
}
try {
$db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Messaging is temporarily unavailable.']);
exit;
}
// Other user (AMENDED: include username for public identity)
$uStmt = $db->prepare("SELECT id, name, username, role FROM users WHERE id = ?");
$uStmt->execute([$otherId]);
$other = $uStmt->fetch(PDO::FETCH_ASSOC);
if (!$other) {
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'User not found.']);
exit;
}
$senderRole = (string)($_SESSION['user_role'] ?? '');
$otherRole  = (string)($other['role'] ?? '');
$pairValid  =
($senderRole === 'user'  && $otherRole === 'agent') ||
($senderRole === 'agent' && $otherRole === 'user')  ||
($senderRole === 'admin');
// ------------------------------------------------------------
// LISTING-LOCK: a listing=0 open resolves to the pair's most
// recent listing thread so threads can never mix listings.
// ------------------------------------------------------------
if ($listingId === 0) {
$rStmt = $db->prepare("
SELECT listing_id, listing_type FROM messages
WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
AND listing_id > 0
ORDER BY id DESC LIMIT 1
");
$rStmt->execute([$userId, $otherId, $otherId, $userId]);
$resolved = $rStmt->fetch(PDO::FETCH_ASSOC);
if ($resolved) {
$listingId = (int)$resolved['listing_id'];
}
}
// Listing meta + closure state
$listingMeta   = null;
$listingClosed = true;   // closed by default; only a live row reopens
$listingAgentId = 0;
if ($listingId > 0) {
$tableMap = [
'car' => 'car_listings', 'property' => 'property_listings',
'solar' => 'solar_listings', 'marketplace' => 'marketplace_listings',
];
$folderMap = [
'car' => 'kinas-automobile', 'property' => 'williams-connect-home',
'solar' => 'kinas-volt', 'marketplace' => 'kinas-marketplace',
];
$ltStmt = $db->prepare("SELECT listing_type FROM messages WHERE listing_id = ? LIMIT 1");
$ltStmt->execute([$listingId]);
$lt = (string)$ltStmt->fetchColumn();
$type = isset($tableMap[$lt]) ? $lt : 'marketplace';
try {
$ls = $db->prepare("SELECT id, title, price, agent_id, status FROM {$tableMap[$type]} WHERE id = ?");
$ls->execute([$listingId]);
$row = $ls->fetch(PDO::FETCH_ASSOC);
if ($row) {
$listingAgentId = (int)$row['agent_id'];
$status = (string)($row['status'] ?? '');
$listingClosed = in_array($status, CHAT_CLOSED_STATUSES, true);
$listingMeta = [
'listing_id'     => (int)$row['id'],
'listing_type'   => $type,
'listing_title'  => $row['title'],
'listing_price'  => (float)$row['price'],
'listing_url'    => '/divisions/' . ($folderMap[$type] ?? '') . '/detail.php?id=' . (int)$row['id'],
'listing_status' => $status,
];
try {
$im = $db->prepare("SELECT url FROM listing_images WHERE listing_type = ? AND listing_id = ? ORDER BY sort_order ASC LIMIT 1");
$im->execute([$type, $listingId]);
$listingMeta['listing_thumb'] = $im->fetchColumn() ?: null;
} catch (Throwable $e) { }
} else {
$listingClosed = true;
$listingMeta = [
'listing_id'     => $listingId,
'listing_type'   => $type,
'listing_title'  => 'Listing removed',
'listing_price'  => null,
'listing_url'    => null,
'listing_status' => 'removed',
];
}
} catch (Throwable $e) { }
}
// Existing thread?
$hasThread = false;
if ($listingId > 0) {
$threadStmt = $db->prepare("
SELECT COUNT(*) FROM messages
WHERE listing_id = ?
AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
");
$threadStmt->execute([$listingId, $userId, $otherId, $otherId, $userId]);
$hasThread = ((int)$threadStmt->fetchColumn()) > 0;
}
$mayStart = $pairValid && !$listingClosed && $listingId > 0 && (
($senderRole === 'user'  && $otherId === $listingAgentId) ||
($senderRole === 'agent' && $userId === $listingAgentId)
);
// REVAMPED GATE: a closed (delisted/removed) listing ends replies for
// everyone; history remains readable.
$canReply = $pairValid && !$listingClosed && $listingId > 0 && ($hasThread || $mayStart);
// Messages — STRICT listing match (never mixes listings)
// AMENDED: sender_username selected for public identity display.
try {
if ($listingId > 0) {
$mStmt = $db->prepare("
SELECT m.*, u.name AS sender_name, u.username AS sender_username
FROM messages m
LEFT JOIN users u ON u.id = m.sender_id
WHERE m.id > ?
AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
AND m.listing_id = ?
ORDER BY m.id ASC
");
$mStmt->execute([$sinceId, $userId, $otherId, $otherId, $userId, $listingId]);
} else {
$mStmt = $db->prepare("
SELECT m.*, u.name AS sender_name, u.username AS sender_username
FROM messages m
LEFT JOIN users u ON u.id = m.sender_id
WHERE m.id > ?
AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
ORDER BY m.id ASC
");
$mStmt->execute([$sinceId, $userId, $otherId, $otherId, $userId]);
}
$rows = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
error_log('thread.php query error: ' . $e->getMessage());
http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Could not load messages.']);
exit;
}
if ($sinceId === 0 && count($rows) > 200) {
$rows = array_slice($rows, -200);
}
$messages = [];
foreach ($rows as $m) {
$meta = null;
if (!empty($m['inquiry_meta'])) {
$decoded = json_decode((string)$m['inquiry_meta'], true);
if (is_array($decoded)) $meta = $decoded;
}
$messages[] = [
'id'                 => (int)$m['id'],
'mine'               => (int)$m['sender_id'] === $userId,
// AMENDED: public identity (@username) instead of legal name.
'sender_name'        => kinas_public_display_name($m['sender_username'] ?? null, $m['sender_name'] ?? 'Unknown'),
'body'               => (string)($m['body'] ?? ''),
'message_type'       => (string)($m['message_type'] ?? 'text'),
'media_urls'         => chat_media_urls($m['media_url'] ?? null),
'media_duration_sec' => isset($m['media_duration_sec']) ? (int)$m['media_duration_sec'] : null,
'inquiry_meta'       => $meta,
'is_read'            => (int)($m['is_read'] ?? 0) === 1,
'read_at'            => $m['read_at'] ?? null,
'is_viewing_request' => (int)($m['is_viewing_request'] ?? 0) === 1,
'preferred_date'     => $m['preferred_date'] ?? null,
'preferred_time'     => $m['preferred_time'] ?? null,
'created_at'         => $m['created_at'] ?? null,
'time_formatted'     => $m['created_at'] ? date('g:i A', strtotime($m['created_at'])) : '',
'date_label'         => chat_date_label($m['created_at']),
];
}
echo json_encode([
'success'      => true,
'conversation' => [
'other_user_id' => $otherId,
// AMENDED: public identity (@username) instead of legal name.
'other_name'    => kinas_public_display_name($other['username'] ?? null, $other['name'] ?? 'Unknown'),
'other_role'    => $otherRole,
'can_reply'     => $canReply,
'closed'        => $listingClosed,
'closed_reason' => $listingClosed ? 'delisted' : null,
'listing'       => $listingMeta,
],
'messages'     => $messages,
]);
