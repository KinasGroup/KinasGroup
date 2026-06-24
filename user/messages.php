<?php
/**
 * KINAS GROUP - User Messages
 * Premium messaging interface inspired by Gmail/Yahoo
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

// Group messages by conversation
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
            'unread_count' => 0,
            'subject' => $msg['subject']
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
        $conversations[$key]['subject'] = $msg['subject'];
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
<!-- PREMIUM GMAIL/YAHOO STYLE MESSAGING -->
<!-- ============================================================ -->
<style>
/* ----- RESET & BASE ----- */
* {
    box-sizing: border-box;
}

.messages-app {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px 30px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* ----- HEADER ----- */
.messages-app-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    border-bottom: 1px solid #e8eaed;
    margin-bottom: 20px;
}

.messages-app-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.messages-app-header h1 i {
    color: #C6A43F;
}

.messages-app-header .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.messages-app-header .header-actions .compose-btn {
    padding: 8px 20px;
    background: #C6A43F;
    color: #fff;
    border: none;
    border-radius: 20px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.messages-app-header .header-actions .compose-btn:hover {
    background: #b8942f;
    transform: scale(1.02);
}

/* ----- MAIN LAYOUT (Gmail-style) ----- */
.messages-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 0;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e8eaed;
    overflow: hidden;
    min-height: 600px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

/* ----- SIDEBAR (Conversation List) ----- */
.sidebar {
    background: #f8f9fa;
    border-right: 1px solid #e8eaed;
    display: flex;
    flex-direction: column;
    max-height: 680px;
}

.sidebar-tabs {
    display: flex;
    padding: 8px 12px;
    gap: 4px;
    border-bottom: 1px solid #e8eaed;
    background: #fff;
    flex-shrink: 0;
}

.sidebar-tabs button {
    padding: 8px 16px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #5f6368;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.sidebar-tabs button.active {
    background: #e8f0fe;
    color: #1a73e8;
}

.sidebar-tabs button:hover {
    background: #f1f3f4;
}

.sidebar-search {
    padding: 8px 12px;
    flex-shrink: 0;
}

.sidebar-search input {
    width: 100%;
    padding: 8px 14px;
    border: 1px solid #e8eaed;
    border-radius: 20px;
    font-size: 13px;
    background: #fff;
    transition: all 0.2s;
}

.sidebar-search input:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 2px rgba(198, 164, 63, 0.15);
}

.sidebar-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}

.sidebar-list::-webkit-scrollbar {
    width: 4px;
}
.sidebar-list::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-list::-webkit-scrollbar-thumb {
    background: #dadce0;
    border-radius: 4px;
}

/* ----- Conversation Item (Gmail-style) ----- */
.conversation-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f1f3f4;
    position: relative;
}

.conversation-item:hover {
    background: #f1f3f4;
}

.conversation-item.active {
    background: #e8f0fe;
    border-left: 3px solid #C6A43F;
}

.conversation-item .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #dadce0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    color: #3c4043;
    flex-shrink: 0;
    margin-right: 12px;
}

.conversation-item .avatar.unread {
    background: #C6A43F;
    color: #fff;
}

.conversation-item .content {
    flex: 1;
    min-width: 0;
}

.conversation-item .content .sender {
    font-weight: 500;
    font-size: 14px;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 6px;
}

.conversation-item .content .sender .role-tag {
    font-size: 10px;
    font-weight: 400;
    color: #5f6368;
    background: #f1f3f4;
    padding: 0 8px;
    border-radius: 10px;
}

.conversation-item .content .sender .role-tag.admin {
    background: #C6A43F;
    color: #fff;
}

.conversation-item .content .sender .role-tag.agent {
    background: #1B5E20;
    color: #fff;
}

.conversation-item .content .subject {
    font-size: 13px;
    color: #3c4043;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.conversation-item .content .preview {
    font-size: 12px;
    color: #5f6368;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.conversation-item .meta {
    text-align: right;
    flex-shrink: 0;
    margin-left: 8px;
}

.conversation-item .meta .time {
    font-size: 11px;
    color: #5f6368;
    white-space: nowrap;
}

.conversation-item .meta .unread-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #C6A43F;
    border-radius: 50%;
    margin-top: 4px;
}

/* ----- Empty State ----- */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 30px;
    color: #5f6368;
    text-align: center;
    height: 100%;
}

.empty-state .icon {
    font-size: 48px;
    color: #dadce0;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    font-weight: 500;
    color: #1a1a2e;
    margin: 0 0 6px 0;
}

.empty-state p {
    font-size: 14px;
    color: #5f6368;
    max-width: 300px;
    margin: 0;
}

/* ----- MESSAGE AREA (Gmail-style) ----- */
.message-area {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    min-height: 500px;
}

/* --- Message Header --- */
.message-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e8eaed;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fafbfc;
    flex-shrink: 0;
}

.message-header .sender-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.message-header .sender-info .avatar-sm {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #C6A43F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 15px;
    color: #fff;
}

.message-header .sender-info .details .name {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a2e;
}

.message-header .sender-info .details .email {
    font-size: 12px;
    color: #5f6368;
}

.message-header .listing-ref {
    font-size: 12px;
    color: #C6A43F;
    background: #f1f3f4;
    padding: 4px 12px;
    border-radius: 16px;
}

/* --- Message Body --- */
.message-body {
    flex: 1;
    padding: 20px 24px;
    overflow-y: auto;
    background: #ffffff;
    max-height: 500px;
}

.message-body::-webkit-scrollbar {
    width: 6px;
}
.message-body::-webkit-scrollbar-track {
    background: transparent;
}
.message-body::-webkit-scrollbar-thumb {
    background: #dadce0;
    border-radius: 4px;
}

/* Individual Message - Clean Gmail Style */
.message-row {
    display: flex;
    margin-bottom: 16px;
    padding: 0 4px;
}

.message-row.sent {
    justify-content: flex-end;
}

.message-row.received {
    justify-content: flex-start;
}

.message-row .bubble {
    max-width: 75%;
    padding: 10px 16px;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
    line-height: 1.6;
    font-size: 14px;
}

.message-row.sent .bubble {
    background: #e8f0fe;
    color: #1a1a2e;
    border-bottom-right-radius: 4px;
}

.message-row.received .bubble {
    background: #f1f3f4;
    color: #1a1a2e;
    border-bottom-left-radius: 4px;
}

.message-row .bubble .msg-text {
    margin: 0;
    white-space: pre-wrap;
}

.message-row .bubble .msg-meta {
    font-size: 11px;
    color: #5f6368;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.message-row.sent .bubble .msg-meta {
    justify-content: flex-end;
}

.message-row .bubble .msg-meta .sender-name {
    font-weight: 500;
}

.message-row .bubble .msg-meta .read-status {
    color: #1a73e8;
}

/* Viewing Request Badge */
.message-row .bubble .viewing-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 12px;
    border-radius: 12px;
    margin-bottom: 6px;
    background: #C6A43F;
    color: #fff;
}

.message-row.received .bubble .viewing-badge {
    background: #f1f3f4;
    color: #C6A43F;
}

.message-row .bubble .viewing-details {
    font-size: 12px;
    color: #5f6368;
    margin-bottom: 4px;
}

/* Date Separator */
.date-separator {
    text-align: center;
    margin: 20px 0 16px 0;
    position: relative;
}

.date-separator span {
    background: #ffffff;
    padding: 0 16px;
    font-size: 12px;
    color: #5f6368;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.date-separator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e8eaed;
    z-index: 0;
}

/* --- Reply Area (Gmail-style) --- */
.reply-area {
    padding: 12px 20px;
    border-top: 1px solid #e8eaed;
    background: #fafbfc;
    flex-shrink: 0;
}

.reply-area form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.reply-area .reply-input-wrapper {
    flex: 1;
    position: relative;
}

.reply-area .reply-input-wrapper textarea {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #dadce0;
    border-radius: 24px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    resize: none;
    height: 44px;
    transition: all 0.2s;
    background: #fff;
    line-height: 1.5;
}

.reply-area .reply-input-wrapper textarea:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 2px rgba(198, 164, 63, 0.1);
}

.reply-area .reply-input-wrapper textarea::placeholder {
    color: #9aa0a6;
}

.reply-area .send-btn {
    padding: 10px 24px;
    background: #C6A43F;
    color: #fff;
    border: none;
    border-radius: 24px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    height: 44px;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.reply-area .send-btn:hover {
    background: #b8942f;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(198, 164, 63, 0.3);
}

.reply-area .send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ----- Success Toast ----- */
.success-toast {
    margin-top: 16px;
    padding: 12px 20px;
    background: #e6f4ea;
    color: #1e7e34;
    border-radius: 8px;
    border: 1px solid #ceead6;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    animation: slideDown 0.3s ease;
}

.success-toast i {
    color: #1e7e34;
    font-size: 18px;
}

/* ----- Animations ----- */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-row {
    animation: fadeIn 0.25s ease;
}

/* ----- Responsive ----- */
@media (max-width: 992px) {
    .messages-app {
        padding: 12px 16px;
    }
    
    .messages-layout {
        grid-template-columns: 1fr;
        border-radius: 8px;
    }
    
    .sidebar {
        max-height: 350px;
        border-right: none;
        border-bottom: 1px solid #e8eaed;
    }
    
    .sidebar-list {
        max-height: 280px;
    }
    
    .message-body {
        max-height: 350px;
    }
    
    .messages-app-header {
        flex-wrap: wrap;
        gap: 10px;
    }
}

@media (max-width: 576px) {
    .messages-app {
        padding: 8px 10px;
    }
    
    .messages-app-header h1 {
        font-size: 18px;
    }
    
    .message-header {
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px 16px;
    }
    
    .message-body {
        padding: 12px 16px;
    }
    
    .reply-area {
        padding: 10px 16px;
    }
    
    .reply-area form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .reply-area .send-btn {
        width: 100%;
        justify-content: center;
    }
    
    .message-row .bubble {
        max-width: 92%;
    }
    
    .conversation-item {
        padding: 8px 12px;
    }
}
</style>

<!-- ============================================================ -->
<!-- MESSAGES APP -->
<!-- ============================================================ -->
<div class="messages-app">

    <!-- App Header -->
    <div class="messages-app-header">
        <h1>
            <i class="fas fa-envelope"></i> Messages
            <span style="font-size: 14px; font-weight: 400; color: #5f6368; margin-left: 8px;">
                <?php if ($viewingConversation && $conversationInfo): ?>
                    · <?= htmlspecialchars($otherUser['name'] ?? 'User') ?>
                <?php else: ?>
                    · <?= count($conversations) ?> conversations
                <?php endif; ?>
            </span>
        </h1>
        <div class="header-actions">
            <?php if ($viewingConversation): ?>
                <a href="/user/messages.php" style="color: #5f6368; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="messages-layout">

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Tabs -->
            <div class="sidebar-tabs">
                <button class="active"><i class="fas fa-inbox"></i> Inbox</button>
                <button><i class="fas fa-clock"></i> Snoozed</button>
                <button><i class="fas fa-check-circle"></i> Done</button>
            </div>

            <!-- Search -->
            <div class="sidebar-search">
                <input type="text" placeholder="Search messages..." id="messageSearch">
            </div>

            <!-- Conversation List -->
            <div class="sidebar-list" id="conversationList">
                <?php if (empty($conversations)): ?>
                    <div class="empty-state" style="padding: 40px 20px;">
                        <div class="icon"><i class="fas fa-inbox"></i></div>
                        <h3>No messages</h3>
                        <p>When you receive messages, they'll appear here.</p>
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
                        $subject = $conv['subject'] ?? 'Message';
                        ?>
                        <a href="/user/messages.php?user=<?= $conv['other_user_id'] ?>&listing=<?= $conv['listing_id'] ?? 0 ?>" 
                           class="conversation-item <?= $isActive ? 'active' : '' ?>">
                            <div class="avatar <?= $unread ? 'unread' : '' ?>">
                                <?= $avatarLetter ?>
                            </div>
                            <div class="content">
                                <div class="sender">
                                    <?= htmlspecialchars($otherName) ?>
                                    <span class="role-tag <?= $otherRole ?>"><?= ucfirst($otherRole) ?></span>
                                </div>
                                <div class="subject">
                                    <?php if (!empty($conv['listing_id'])): ?>
                                        <span style="color: #C6A43F;">[Listing #<?= $conv['listing_id'] ?>]</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($subject) ?>
                                </div>
                                <div class="preview">
                                    <?php 
                                    if (!empty($lastMessage)) {
                                        echo htmlspecialchars(substr($lastMessage, 0, 50));
                                        if (strlen($lastMessage) > 50) echo '...';
                                    } else {
                                        echo 'No messages yet';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="meta">
                                <div class="time">
                                    <?php if ($conv['last_message_time']): ?>
                                        <?= date('M d', strtotime($conv['last_message_time'])) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($unread): ?>
                                    <div class="unread-dot"></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Message Area -->
        <div class="message-area">

            <?php if ($viewingConversation && $conversationInfo && !empty($messages)): ?>
                <!-- Header -->
                <div class="message-header">
                    <div class="sender-info">
                        <div class="avatar-sm">
                            <?= strtoupper(substr($otherUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="details">
                            <div class="name"><?= htmlspecialchars($otherUser['name'] ?? 'Unknown') ?></div>
                            <div class="email"><?= htmlspecialchars($otherUser['email'] ?? '') ?> · <?= ucfirst($otherUser['role'] ?? 'user') ?></div>
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
                <div class="message-body" id="messageBody">
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
                        <div class="date-separator">
                            <span><?= $displayDate ?></span>
                        </div>
                    <?php endif; ?>
                        
                        <div class="message-row <?= $isSent ? 'sent' : 'received' ?>">
                            <div class="bubble">
                                <?php if ($isViewing): ?>
                                    <div class="viewing-badge">
                                        <i class="fas fa-calendar-check"></i> Viewing Request
                                    </div>
                                    <?php if (!empty($msg['preferred_date'])): ?>
                                        <div class="viewing-details">
                                            📅 <?= date('M j, Y', strtotime($msg['preferred_date'])) ?>
                                            <?php if (!empty($msg['preferred_time'])): ?>
                                                at <?= htmlspecialchars($msg['preferred_time']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p class="msg-text"><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                                <div class="msg-meta">
                                    <span class="sender-name"><?= htmlspecialchars($senderName) ?></span>
                                    <span>·</span>
                                    <span><?= date('g:i A', strtotime($msg['created_at'])) ?></span>
                                    <?php if ($isSent && $msg['is_read']): ?>
                                        <span><i class="fas fa-check-circle" style="color: #1a73e8;"></i> Read</span>
                                    <?php elseif ($isSent): ?>
                                        <span><i class="fas fa-check" style="color: #5f6368;"></i> Sent</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply -->
                <div class="reply-area">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="reply-input-wrapper">
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
                
                <div class="reply-area">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="reply-input-wrapper">
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
        <div class="success-toast">
            <i class="fas fa-check-circle"></i>
            <span>Reply sent successfully!</span>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom of messages
    const messageBody = document.getElementById('messageBody');
    if (messageBody) {
        messageBody.scrollTop = messageBody.scrollHeight;
    }
    
    // Auto-resize textarea (Gmail style)
    document.querySelectorAll('.reply-input-wrapper textarea').forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = '44px';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    });
    
    // Enter key to send (Shift+Enter for new line)
    document.querySelectorAll('.reply-input-wrapper textarea').forEach(function(textarea) {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('messageSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.conversation-item');
            
            items.forEach(function(item) {
                const text = item.textContent.toLowerCase();
                if (query === '' || text.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include '../templates/footer.php'; ?>
