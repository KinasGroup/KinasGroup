<?php
/**
 * KINAS GROUP - User Messages
 * Professional messaging interface for regular users (buyers)
 */
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../api/config/database.php';

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$userId = SessionManager::getUserId();
$db = Database::getInstance()->getConnection();

// Get the other user ID if viewing a conversation
$otherUserId = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$listingId = isset($_GET['listing']) ? (int)$_GET['listing'] : 0;
$viewingConversation = $otherUserId > 0;

// Handle new message reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $replyMessage = trim($_POST['reply_message']);
    $receiverId = (int)$_POST['receiver_id'];
    $listingId = (int)$_POST['listing_id'];
    $listingType = $_POST['listing_type'] ?? 'property';
    
    if (!empty($replyMessage)) {
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, receiver_id, listing_id, listing_type, subject, body, is_read, created_at) 
            VALUES (?, ?, ?, ?, 'Reply', ?, 0, NOW())
        ");
        $stmt->execute([$userId, $receiverId, $listingId, $listingType, $replyMessage]);
        
        header('Location: /user/messages.php?user=' . $receiverId . '&listing=' . $listingId . '&sent=1');
        exit;
    }
}

// Get all conversations for the user
$conversationsStmt = $db->prepare("
    SELECT 
        m.id,
        m.listing_id,
        m.listing_type,
        m.subject,
        m.body,
        m.created_at,
        m.is_read,
        m.sender_id,
        m.receiver_id,
        u.name AS sender_name,
        u.email AS sender_email,
        u.role AS sender_role,
        u2.name AS receiver_name,
        u2.email AS receiver_email,
        u2.role AS receiver_role
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    LEFT JOIN users u2 ON m.receiver_id = u2.id
    WHERE m.receiver_id = ? OR m.sender_id = ?
    ORDER BY m.created_at DESC
");
$conversationsStmt->execute([$userId, $userId]);
$allMessages = $conversationsStmt->fetchAll();

// Group messages by conversation (using other_user_id)
$conversations = [];
foreach ($allMessages as $msg) {
    $otherId = ($msg['sender_id'] == $userId) ? $msg['receiver_id'] : $msg['sender_id'];
    $otherName = ($msg['sender_id'] == $userId) ? $msg['receiver_name'] : $msg['sender_name'];
    $otherRole = ($msg['sender_id'] == $userId) ? $msg['receiver_role'] : $msg['sender_role'];
    $otherEmail = ($msg['sender_id'] == $userId) ? $msg['receiver_email'] : $msg['sender_email'];
    
    $key = $otherId;
    
    if (!isset($conversations[$key])) {
        $conversations[$key] = [
            'other_user_id' => $otherId,
            'other_name' => $otherName,
            'other_email' => $otherEmail,
            'other_role' => $otherRole,
            'listing_id' => $msg['listing_id'],
            'listing_type' => $msg['listing_type'],
            'last_message' => $msg['body'],
            'last_message_time' => $msg['created_at'],
            'unread_count' => 0
        ];
    }
    
    if ($msg['is_read'] == 0 && $msg['receiver_id'] == $userId) {
        $conversations[$key]['unread_count']++;
    }
    
    if (strtotime($msg['created_at']) > strtotime($conversations[$key]['last_message_time'])) {
        $conversations[$key]['last_message'] = $msg['body'];
        $conversations[$key]['last_message_time'] = $msg['created_at'];
        $conversations[$key]['listing_id'] = $msg['listing_id'];
        $conversations[$key]['listing_type'] = $msg['listing_type'];
    }
}

usort($conversations, function($a, $b) {
    return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
});

// Get messages for a specific conversation
$messages = [];
$conversationInfo = null;
$otherUser = null;
$listingInfo = null;

if ($viewingConversation) {
    foreach ($conversations as $conv) {
        if ($conv['other_user_id'] == $otherUserId) {
            $conversationInfo = $conv;
            break;
        }
    }
    
    if ($conversationInfo) {
        $otherUser = [
            'id' => $conversationInfo['other_user_id'],
            'name' => $conversationInfo['other_name'],
            'email' => $conversationInfo['other_email'],
            'role' => $conversationInfo['other_role']
        ];
        
        $stmt = $db->prepare("
            SELECT 
                m.*,
                u.name AS sender_name,
                u.email AS sender_email,
                u.role AS sender_role
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $messages = $stmt->fetchAll();
        
        $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$otherUserId, $userId]);
        
        $listingId = null;
        $listingType = null;
        foreach ($messages as $msg) {
            if (!empty($msg['listing_id'])) {
                $listingId = $msg['listing_id'];
                $listingType = $msg['listing_type'];
                break;
            }
        }
        
        if ($listingId) {
            $tableMap = [
                'car' => 'car_listings',
                'property' => 'property_listings',
                'solar' => 'solar_listings',
                'marketplace' => 'marketplace_listings'
            ];
            $table = $tableMap[$listingType] ?? 'property_listings';
            
            $stmt = $db->prepare("SELECT id, title, price FROM $table WHERE id = ?");
            $stmt->execute([$listingId]);
            $listingInfo = $stmt->fetch();
        }
    }
}

$pageTitle = 'Messages - My Dashboard';
include '../templates/header.php';
?>

<!-- ============================================================ -->
<!-- PROFESSIONAL MESSAGING STYLES -->
<!-- ============================================================ -->
<style>
/* ----- Container ----- */
.messages-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 40px;
}

.messages-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.messages-header h1 {
    font-family: 'Prata', serif;
    font-size: 28px;
    color: #0A0A0A;
    margin: 0;
}

.messages-header h1 i {
    color: #C6A43F;
    margin-right: 12px;
}

.messages-header .subtitle {
    color: #888;
    margin: 4px 0 0 0;
    font-size: 14px;
}

/* ----- Main Container ----- */
.messages-container {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    background: #ffffff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.06);
    border: 1px solid #f0ede8;
    min-height: 600px;
    max-height: 780px;
}

/* ----- Sidebar ----- */
.messages-sidebar {
    background: #faf8f6;
    border-right: 1px solid #f0ede8;
    overflow-y: auto;
    max-height: 780px;
}

.messages-sidebar::-webkit-scrollbar {
    width: 4px;
}
.messages-sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.messages-sidebar::-webkit-scrollbar-thumb {
    background: #d0ccc5;
    border-radius: 4px;
}
.messages-sidebar::-webkit-scrollbar-thumb:hover {
    background: #b8b2a8;
}

.sidebar-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f0ede8;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 5;
}

.sidebar-header h2 {
    font-family: 'Prata', serif;
    font-size: 18px;
    color: #0A0A0A;
    margin: 0;
}

.sidebar-header .badge {
    background: #C6A43F;
    color: #fff;
    font-size: 11px;
    padding: 3px 12px;
    border-radius: 20px;
    font-weight: 600;
}

/* ----- Conversation Item ----- */
.conversation-item {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f5f3f0;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
}

.conversation-item:hover {
    background: #f5f0e8;
}

.conversation-item.active {
    background: #f0e8dc;
    border-left: 4px solid #C6A43F;
}

.conversation-item .avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e8e5e0, #d5d0c8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
    color: #0A0A0A;
    flex-shrink: 0;
    margin-right: 14px;
    position: relative;
}

.conversation-item .avatar .online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background: #28a745;
    border-radius: 50%;
    border: 2px solid #fff;
}

.conversation-item .info {
    flex: 1;
    min-width: 0;
}

.conversation-item .info .name {
    font-weight: 600;
    font-size: 14px;
    color: #0A0A0A;
    display: flex;
    align-items: center;
    gap: 8px;
}

.conversation-item .info .name .role-badge {
    font-size: 10px;
    font-weight: 500;
    padding: 1px 8px;
    border-radius: 12px;
    background: #e8e5e0;
    color: #666;
    text-transform: uppercase;
}

.conversation-item .info .name .role-badge.admin {
    background: #C6A43F;
    color: #fff;
}

.conversation-item .info .name .role-badge.agent {
    background: #1B5E20;
    color: #fff;
}

.conversation-item .info .last-message {
    font-size: 13px;
    color: #888;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}

.conversation-item .info .listing-ref {
    font-size: 11px;
    color: #C6A43F;
    margin-top: 2px;
}

.conversation-item .time {
    font-size: 11px;
    color: #aaa;
    flex-shrink: 0;
    margin-left: 10px;
    text-align: right;
}

.conversation-item .unread-badge {
    background: #C6A43F;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    margin-left: 8px;
    flex-shrink: 0;
}

.conversation-item .unread-badge.zero {
    background: transparent;
    color: transparent;
}

/* ----- Message Area ----- */
.messages-area {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    min-height: 500px;
}

/* Message Header */
.messages-area-header {
    padding: 16px 24px;
    border-bottom: 1px solid #f0ede8;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #faf8f6;
    flex-shrink: 0;
}

.messages-area-header .user-info {
    display: flex;
    align-items: center;
    gap: 14px;
}

.messages-area-header .user-info .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C6A43F, #d4af37);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    color: #fff;
}

.messages-area-header .user-info .name {
    font-weight: 600;
    font-size: 15px;
    color: #0A0A0A;
}

.messages-area-header .user-info .role {
    font-size: 12px;
    color: #888;
}

.messages-area-header .listing-ref {
    font-size: 12px;
    color: #C6A43F;
    background: #f5f0e8;
    padding: 4px 14px;
    border-radius: 20px;
    display: inline-block;
}

/* Messages Body */
.messages-body {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    background: #fcfbf9;
    max-height: 550px;
}

.messages-body::-webkit-scrollbar {
    width: 4px;
}
.messages-body::-webkit-scrollbar-track {
    background: transparent;
}
.messages-body::-webkit-scrollbar-thumb {
    background: #d0ccc5;
    border-radius: 4px;
}
.messages-body::-webkit-scrollbar-thumb:hover {
    background: #b8b2a8;
}

/* Individual Message */
.message-item {
    display: flex;
    margin-bottom: 16px;
    animation: fadeInUp 0.3s ease;
}

.message-item.sent {
    justify-content: flex-end;
}

.message-item.received {
    justify-content: flex-start;
}

.message-item .bubble {
    max-width: 75%;
    padding: 14px 20px;
    border-radius: 16px;
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.message-item.sent .bubble {
    background: #C6A43F;
    color: #fff;
    border-bottom-right-radius: 4px;
}

.message-item.received .bubble {
    background: #f0ede8;
    color: #0A0A0A;
    border-bottom-left-radius: 4px;
}

.message-item .bubble .message-text {
    font-size: 14px;
    line-height: 1.7;
    margin: 0;
    white-space: pre-wrap;
}

.message-item .bubble .message-meta {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.message-item.sent .bubble .message-meta {
    color: rgba(255,255,255,0.8);
    justify-content: flex-end;
}

.message-item.received .bubble .message-meta {
    color: #888;
}

.message-item .bubble .message-meta .sender-name {
    font-weight: 500;
}

/* Viewing Request Badge */
.message-item .bubble .viewing-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 12px;
    border-radius: 12px;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.2);
    color: #fff;
}

.message-item.received .bubble .viewing-badge {
    background: rgba(198, 164, 63, 0.15);
    color: #C6A43F;
}

.message-item .bubble .viewing-details {
    font-size: 12px;
    margin-bottom: 6px;
    opacity: 0.8;
}

/* Date Separator */
.message-date-separator {
    text-align: center;
    margin: 24px 0;
    position: relative;
}

.message-date-separator span {
    background: #fcfbf9;
    padding: 0 20px;
    font-size: 12px;
    color: #aaa;
    font-weight: 500;
    position: relative;
    z-index: 1;
    letter-spacing: 0.5px;
}

.message-date-separator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e8e5e0;
    z-index: 0;
}

/* Reply Area */
.messages-reply {
    padding: 16px 24px;
    border-top: 1px solid #f0ede8;
    background: #faf8f6;
    flex-shrink: 0;
}

.messages-reply form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.messages-reply .input-wrapper {
    flex: 1;
    position: relative;
}

.messages-reply textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e0dcd5;
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    resize: none;
    height: 48px;
    transition: all 0.3s ease;
    background: #fff;
    line-height: 1.5;
}

.messages-reply textarea:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 3px rgba(198, 164, 63, 0.1);
}

.messages-reply .send-btn {
    padding: 12px 28px;
    background: #0A0A0A;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
    height: 48px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.messages-reply .send-btn:hover {
    background: #C6A43F;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(198, 164, 63, 0.3);
}

.messages-reply .send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
    padding: 40px;
    text-align: center;
}

.empty-state .icon {
    font-size: 56px;
    color: #e0dcd5;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-family: 'Prata', serif;
    font-size: 20px;
    color: #0A0A0A;
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 14px;
    color: #888;
    max-width: 320px;
}

/* Success Banner */
.success-banner {
    margin-top: 16px;
    padding: 14px 20px;
    background: #d4edda;
    color: #155724;
    border-radius: 12px;
    border-left: 4px solid #28a745;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideInRight 0.3s ease;
}

.success-banner i {
    color: #28a745;
    font-size: 18px;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Typing indicator (optional) */
.typing-indicator {
    display: none;
    padding: 10px 20px;
    color: #888;
    font-size: 13px;
    font-style: italic;
}

/* Responsive */
@media (max-width: 992px) {
    .messages-wrapper {
        padding: 16px;
    }
    
    .messages-container {
        grid-template-columns: 1fr;
        max-height: none;
        border-radius: 16px;
    }
    
    .messages-sidebar {
        max-height: 350px;
        border-right: none;
        border-bottom: 1px solid #f0ede8;
    }
    
    .messages-area {
        min-height: 400px;
    }
    
    .messages-body {
        max-height: 400px;
    }
}

@media (max-width: 576px) {
    .messages-wrapper {
        padding: 12px;
    }
    
    .messages-header h1 {
        font-size: 22px;
    }
    
    .messages-area-header {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .messages-reply form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .messages-reply .send-btn {
        width: 100%;
        justify-content: center;
    }
    
    .message-item .bubble {
        max-width: 90%;
    }
}
</style>

<div class="messages-wrapper">

    <!-- Header -->
    <div class="messages-header">
        <div>
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p class="subtitle">
                <?php if ($viewingConversation && $conversationInfo): ?>
                    Conversation with <strong><?= htmlspecialchars($otherUser['name'] ?? 'User') ?></strong>
                    <?php if ($listingInfo): ?>
                        · <span style="color: #C6A43F;"><?= htmlspecialchars($listingInfo['title']) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= count($conversations) ?> conversation(s)
                <?php endif; ?>
            </p>
        </div>
        <?php if ($viewingConversation): ?>
            <a href="/user/messages.php" style="color: #C6A43F; text-decoration: none; font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-arrow-left"></i> Back to Inbox
            </a>
        <?php endif; ?>
    </div>

    <!-- Messages Container -->
    <div class="messages-container">

        <!-- Sidebar -->
        <div class="messages-sidebar">
            <div class="sidebar-header">
                <h2>Inbox</h2>
                <span class="badge">
                    <?php 
                    $unreadTotal = 0;
                    foreach ($conversations as $conv) {
                        $unreadTotal += $conv['unread_count'] ?? 0;
                    }
                    echo $unreadTotal;
                    ?>
                </span>
            </div>

            <?php if (empty($conversations)): ?>
                <div class="empty-state" style="padding: 40px 20px; min-height: 300px;">
                    <div class="icon"><i class="fas fa-inbox"></i></div>
                    <h3>No messages yet</h3>
                    <p>When agents respond to your inquiries, their messages will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <?php 
                    $otherName = $conv['other_name'] ?? 'Unknown';
                    $otherRole = $conv['other_role'] ?? 'user';
                    $isActive = $viewingConversation && $conv['other_user_id'] == $otherUserId;
                    $unread = ($conv['unread_count'] ?? 0) > 0;
                    $avatarLetter = strtoupper(substr($otherName, 0, 1));
                    $lastMessage = $conv['last_message'] ?? '';
                    ?>
                    <a href="/user/messages.php?user=<?= $conv['other_user_id'] ?>&listing=<?= $conv['listing_id'] ?? 0 ?>" 
                       class="conversation-item <?= $isActive ? 'active' : '' ?>">
                        <div class="avatar">
                            <?= $avatarLetter ?>
                            <?php if ($unread): ?>
                                <span class="online-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="info">
                            <div class="name">
                                <?= htmlspecialchars($otherName) ?>
                                <span class="role-badge <?= $otherRole ?>"><?= ucfirst($otherRole) ?></span>
                                <?php if ($unread): ?>
                                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="last-message">
                                <?php 
                                if (!empty($lastMessage)) {
                                    echo htmlspecialchars(substr($lastMessage, 0, 60));
                                    if (strlen($lastMessage) > 60) echo '...';
                                } else {
                                    echo 'No messages yet';
                                }
                                ?>
                            </div>
                            <?php if (!empty($conv['listing_id'])): ?>
                                <div class="listing-ref">
                                    <i class="fas fa-tag" style="font-size: 9px;"></i> 
                                    Listing #<?= $conv['listing_id'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="time">
                            <?php if ($conv['last_message_time']): ?>
                                <?= date('M j', strtotime($conv['last_message_time'])) ?>
                                <br>
                                <?= date('g:i A', strtotime($conv['last_message_time'])) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Message Area -->
        <div class="messages-area">

            <?php if ($viewingConversation && $conversationInfo && !empty($messages)): ?>
                <!-- Header -->
                <div class="messages-area-header">
                    <div class="user-info">
                        <div class="avatar">
                            <?= strtoupper(substr($otherUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div>
                            <div class="name"><?= htmlspecialchars($otherUser['name'] ?? 'Unknown') ?></div>
                            <div class="role">
                                <?= ucfirst($otherUser['role'] ?? 'user') ?>
                                <?php if (!empty($otherUser['email'])): ?>
                                    · <?= htmlspecialchars($otherUser['email']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($listingInfo): ?>
                        <div class="listing-ref">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($listingInfo['title']) ?>
                            <?php if (!empty($listingInfo['price'])): ?>
                                · ₦<?= number_format($listingInfo['price']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Messages -->
                <div class="messages-body" id="messagesBody">
                    <?php 
                    $lastDate = '';
                    foreach ($messages as $msg): 
                        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                        $isSent = $msg['sender_id'] == $userId;
                        $senderName = $isSent ? 'You' : ($msg['sender_name'] ?? 'Unknown');
                        $isViewing = !empty($msg['is_viewing_request']) && $msg['is_viewing_request'] == 1;
                        
                        if ($msgDate != $lastDate): 
                            $lastDate = $msgDate;
                            $displayDate = date('l, F j, Y', strtotime($msg['created_at']));
                    ?>
                        <div class="message-date-separator">
                            <span><?= $displayDate ?></span>
                        </div>
                    <?php endif; ?>
                        
                        <div class="message-item <?= $isSent ? 'sent' : 'received' ?>">
                            <div class="bubble">
                                <?php if ($isViewing): ?>
                                    <div class="viewing-badge">
                                        <i class="fas fa-calendar-check"></i> Viewing Request
                                    </div>
                                    <?php if (!empty($msg['preferred_date'])): ?>
                                        <div class="viewing-details">
                                            📅 <?= date('l, F j, Y', strtotime($msg['preferred_date'])) ?>
                                            <?php if (!empty($msg['preferred_time'])): ?>
                                                at <?= htmlspecialchars($msg['preferred_time']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p class="message-text"><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                                <div class="message-meta">
                                    <span class="sender-name"><?= htmlspecialchars($senderName) ?></span>
                                    <span>·</span>
                                    <span><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                                    <?php if ($isSent && $msg['is_read']): ?>
                                        <span><i class="fas fa-check-circle" style="color: #28a745;"></i> Read</span>
                                    <?php elseif ($isSent): ?>
                                        <span><i class="fas fa-check" style="color: #888;"></i> Sent</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply -->
                <div class="messages-reply">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="input-wrapper">
                            <textarea name="reply_message" placeholder="Type your reply..." required></textarea>
                        </div>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>

            <?php elseif ($viewingConversation && $conversationInfo): ?>
                <!-- Empty conversation -->
                <div class="empty-state" style="height: 100%;">
                    <div class="icon"><i class="fas fa-comment-dots"></i></div>
                    <h3>No messages yet</h3>
                    <p>Start the conversation by sending a message.</p>
                </div>
                
                <div class="messages-reply">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="input-wrapper">
                            <textarea name="reply_message" placeholder="Type your message..." required></textarea>
                        </div>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- No conversation selected -->
                <div class="empty-state" style="height: 100%;">
                    <div class="icon"><i class="fas fa-envelope-open-text"></i></div>
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the sidebar to view and reply to messages.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if (isset($_GET['sent']) && $_GET['sent'] == 1): ?>
        <div class="success-banner">
            <i class="fas fa-check-circle"></i>
            <span>Reply sent successfully!</span>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom
    const messagesBody = document.getElementById('messagesBody');
    if (messagesBody) {
        messagesBody.scrollTop = messagesBody.scrollHeight;
    }
    
    // Auto-resize textarea
    document.querySelectorAll('.messages-reply textarea').forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = '48px';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    });
    
    // Enter key to send (Shift+Enter for new line)
    document.querySelectorAll('.messages-reply textarea').forEach(function(textarea) {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    });
});
</script>

<?php include '../templates/footer.php'; ?>
