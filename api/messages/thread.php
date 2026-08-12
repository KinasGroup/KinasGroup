<?php
/**
 * KINAS GROUP — Chat thread (rebuilt messenger)
 *
 * GET /api/messages/thread.php?other_user=ID&listing=ID[&since_id=N]
 *
 * Returns one conversation (both directions) between the logged-in user
 * and another user about one listing. With since_id, returns only newer
 * messages (used for 5-second polling). Also returns conversation meta
 * (other user, listing card, can_reply).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/session.php';

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

function chat_media_urls(array $m): array
{
    $raw = (string)($m['media_url'] ?? '');
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

$userId   = (int)SessionManager::getUserId();
$otherId  = (int)($_GET['other_user'] ?? 0);
$listingId = (int)($_GET['listing'] ?? 0);
$sinceId  = max(0, (int)($_GET['since_id'] ?? 0));

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

// ------------------------------------------------------------
// Other user + roles
// ------------------------------------------------------------
$uStmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
$uStmt->execute([$otherId]);
$other = $uStmt->fetch(PDO::FETCH_ASSOC);

if (!$other) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found.']);
    exit;
}

$myRole    = (string)($_SESSION['user_role'] ?? '');
$otherRole = (string)($other['role'] ?? '');

$pairValid =
    ($myRole === 'user'  && $otherRole === 'agent') ||
    ($myRole === 'agent' && $otherRole === 'user')  ||
    ($myRole === 'admin');

// ------------------------------------------------------------
// Listing meta (if a listing is attached)
// ------------------------------------------------------------
$tableMap = [
    'car'         => 'car_listings',
    'property'    => 'property_listings',
    'solar'       => 'solar_listings',
    'marketplace' => 'marketplace_listings',
];
$folderMap = [
    'car'         => 'kinas-automobile',
    'property'    => 'williams-connect-home',
    'solar'       => 'kinas-volt',
    'marketplace' => 'kinas-marketplace',
];

$listingMeta = null;
$listingAgentId = 0;

if ($listingId > 0) {
    foreach ($tableMap as $type => $table) {
        try {
            $ls = $db->prepare("SELECT id, title, price, agent_id FROM {$table} WHERE id = ?");
            $ls->execute([$listingId]);
            $row = $ls->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $listingAgentId = (int)$row['agent_id'];
                $listingMeta = [
                    'listing_id'    => (int)$row['id'],
                    'listing_type'  => $type,
                    'listing_title' => $row['title'],
                    'listing_price' => (float)$row['price'],
                    'listing_url'   => '/divisions/' . $folderMap[$type] . '/detail.php?id=' . (int)$row['id'],
                    'listing_thumb' => null,
                ];
                try {
                    $im = $db->prepare("SELECT url FROM listing_images WHERE listing_type = ? AND listing_id = ? ORDER BY sort_order ASC LIMIT 1");
                    $im->execute([$type, $listingId]);
                    $listingMeta['listing_thumb'] = $im->fetchColumn() ?: null;
                } catch (Throwable $e) {
                }
                break;
            }
        } catch (Throwable $e) {
        }
    }
}

// ------------------------------------------------------------
// Messages
// ------------------------------------------------------------
try {
    $mStmt = $db->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        LEFT JOIN users u ON u.id = m.sender_id
        WHERE m.id > ?
          AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
          AND (? = 0 OR m.listing_id = ?)
        ORDER BY m.id ASC
    ");
    $mStmt->execute([$sinceId, $userId, $otherId, $otherId, $userId, $listingId, $listingId]);
    $rows = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('thread.php query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load messages.']);
    exit;
}

// Initial load: keep only the latest 200 messages.
if ($sinceId === 0 && count($rows) > 200) {
    $rows = array_slice($rows, -200);
}

$messages = [];
foreach ($rows as $m) {
    $messages[] = [
        'id'                 => (int)$m['id'],
        'mine'               => (int)$m['sender_id'] === $userId,
        'sender_name'        => $m['sender_name'] ?? 'Unknown',
        'body'               => (string)($m['body'] ?? ''),
        'message_type'       => (string)($m['message_type'] ?? 'text'),
        'media_urls'         => chat_media_urls($m),
        'media_duration_sec' => isset($m['media_duration_sec']) ? (int)$m['media_duration_sec'] : null,
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

// ------------------------------------------------------------
// can_reply — same rules as api/messages/send.php
// ------------------------------------------------------------
$hasThread = count($messages) > 0;
if (!$hasThread && $sinceId === 0) {
    $cStmt = $db->prepare("
        SELECT COUNT(*) FROM messages
        WHERE listing_id = ?
          AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    ");
    $cStmt->execute([$listingId, $userId, $otherId, $otherId, $userId]);
    $hasThread = ((int)$cStmt->fetchColumn()) > 0;
}

$canReply = false;
if ($pairValid) {
    if ($hasThread) {
        $canReply = true;
    } elseif ($listingMeta !== null) {
        $canReply = ($myRole === 'user' && $otherId === $listingAgentId)
                 || ($myRole === 'agent' && $userId === $listingAgentId);
    }
}

echo json_encode([
    'success'      => true,
    'conversation' => [
        'other_user_id' => $otherId,
        'other_name'    => $other['name'] ?? 'Unknown',
        'other_role'    => $otherRole,
        'can_reply'     => $canReply,
        'listing'       => $listingMeta,
    ],
    'messages'     => $messages,
]);
