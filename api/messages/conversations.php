<?php
/**
 * KINAS GROUP — Chat conversation list (rebuilt messenger)
 *
 * GET /api/messages/conversations.php
 *
 * Returns the logged-in user's conversations, grouped by
 * (other user + listing), newest first, with unread counts,
 * type-aware previews ("📷 Photo", "🎤 Voice note") and listing info.
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

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------
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

function chat_preview(array $m): string
{
    $type = (string)($m['message_type'] ?? 'text');

    if ($type === 'image') {
        $urls = chat_media_urls($m);
        return '📷 Photo' . (count($urls) > 1 ? ' (' . count($urls) . ')' : '');
    }

    if ($type === 'audio') {
        return '🎤 Voice note';
    }

    $body = trim((string)($m['body'] ?? ''));

    if (!empty($m['is_viewing_request'])) {
        $body = '📅 Viewing request' . ($body !== '' ? ': ' . $body : '');
    }

    if ($body === '') return '(attachment)';

    if (function_exists('mb_strlen')) {
        return mb_strlen($body) > 60 ? mb_substr($body, 0, 60) . '…' : $body;
    }
    return strlen($body) > 60 ? substr($body, 0, 60) . '…' : $body;
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

// ------------------------------------------------------------
// Main
// ------------------------------------------------------------
$userId = (int)SessionManager::getUserId();

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Messaging is temporarily unavailable.']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT m.*,
               s.name AS sender_name,   s.role AS sender_role,
               r.name AS receiver_name, r.role AS receiver_role
        FROM messages m
        LEFT JOIN users s ON s.id = m.sender_id
        LEFT JOIN users r ON r.id = m.receiver_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.id DESC
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('conversations.php query error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load conversations.']);
    exit;
}

// Group by (other user + listing). Rows arrive newest-first, so the
// first row seen per group is the latest message.
$groups = [];
foreach ($rows as $m) {
    $isMine    = (int)$m['sender_id'] === $userId;
    $otherId   = $isMine ? (int)$m['receiver_id'] : (int)$m['sender_id'];
    $otherName = $isMine ? ($m['receiver_name'] ?? 'Unknown') : ($m['sender_name'] ?? 'Unknown');
    $otherRole = $isMine ? ($m['receiver_role'] ?? 'user') : ($m['sender_role'] ?? 'user');
    $lType     = (string)($m['listing_type'] ?? '');
    $lId       = (int)($m['listing_id'] ?? 0);
    $key       = $otherId . '|' . $lType . '|' . $lId;

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'other_user_id'     => $otherId,
            'other_name'        => $otherName !== '' ? $otherName : 'Unknown',
            'other_role'        => $otherRole,
            'listing_id'        => $lId,
            'listing_type'      => $lType,
            'last_preview'      => chat_preview($m),
            'last_time'         => $m['created_at'] ?? null,
            'last_sender_is_me' => $isMine,
            'unread_count'      => 0,
        ];
    }

    if (!$isMine && (int)($m['is_read'] ?? 0) === 0) {
        $groups[$key]['unread_count']++;
    }
}

$conversations = array_values($groups);
if (count($conversations) > 100) {
    $conversations = array_slice($conversations, 0, 100);
}

// ------------------------------------------------------------
// Batch-load listing titles / prices / thumbnails
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

$byType = [];
foreach ($conversations as $c) {
    if ($c['listing_id'] > 0 && isset($tableMap[$c['listing_type']])) {
        $byType[$c['listing_type']][] = $c['listing_id'];
    }
}

$listingInfo = [];
foreach ($byType as $type => $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (empty($ids)) continue;
    $in = implode(',', array_fill(0, count($ids), '?'));

    try {
        $ls = $db->prepare("SELECT id, title, price FROM {$tableMap[$type]} WHERE id IN ($in)");
        $ls->execute($ids);
        foreach ($ls->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $listingInfo[$type . '|' . (int)$row['id']] = [
                'title' => $row['title'],
                'price' => (float)$row['price'],
                'thumb' => null,
            ];
        }

        $im = $db->prepare("SELECT listing_id, url FROM listing_images WHERE listing_type = ? AND listing_id IN ($in) ORDER BY sort_order ASC");
        $im->execute(array_merge([$type], $ids));
        foreach ($im->fetchAll(PDO::FETCH_ASSOC) as $img) {
            $k = $type . '|' . (int)$img['listing_id'];
            if (isset($listingInfo[$k]) && empty($listingInfo[$k]['thumb'])) {
                $listingInfo[$k]['thumb'] = $img['url'];
            }
        }
    } catch (Throwable $e) {
        // Listing info is decorative — never fail the whole request.
    }
}

foreach ($conversations as &$c) {
    $k    = $c['listing_type'] . '|' . $c['listing_id'];
    $info = $listingInfo[$k] ?? null;

    $c['listing_title'] = $info['title'] ?? null;
    $c['listing_price'] = $info['price'] ?? null;
    $c['listing_thumb'] = $info['thumb'] ?? null;
    $c['listing_url']   = ($c['listing_id'] > 0 && isset($folderMap[$c['listing_type']]))
        ? '/divisions/' . $folderMap[$c['listing_type']] . '/detail.php?id=' . $c['listing_id']
        : null;

    $c['last_time_formatted'] = $c['last_time'] ? date('g:i A', strtotime($c['last_time'])) : '';
    $c['last_date_label']     = chat_date_label($c['last_time']);
}
unset($c);

echo json_encode([
    'success'       => true,
    'conversations' => $conversations,
]);
