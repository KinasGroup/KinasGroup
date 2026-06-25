<?php
/**
 * KINAS GROUP - User Messages
 * Premium Gmail/Yahoo style messaging interface
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
            'subject' => $msg['subject'] ?? 'Message'
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
        $conversations[$key]['subject'] = $msg['subject'] ?? 'Message';
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
<!-- GMAIL/YAHOO STYLE MESSAGING - COMPLETE REDESIGN -->
<!-- ============================================================ -->
<style>
/* ----- RESET & BASE ----- */
* {
    box-sizing: border-box;
}

.mail-app {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px 24px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #ffffff;
    min-height: 100vh;
}

/* ----- HEADER (Gmail-style) ----- */
.mail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0 16px 0;
    border-bottom: 1px solid #e8eaed;
    margin-bottom: 16px;
}

.mail-header .logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mail-header .logo-area .menu-icon {
    font-size: 20px;
    color: #5f6368;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 50%;
    transition: background 0.2s;
}

.mail-header .logo-area .menu-icon:hover {
    background: #f1f3f4;
}

.mail-header .logo-area h1 {
    font-size: 22px;
    font-weight: 500;
    color: #1a1a2e;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mail-header .logo-area h1 .mail-icon {
    color: #C6A43F;
}

.mail-header .logo-area h1 .count-badge {
    font-size: 12px;
    font-weight: 400;
    color: #5f6368;
    background: #f1f3f4;
    padding: 2px 10px;
    border-radius: 12px;
}

.mail-header .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mail-header .header-actions button {
    background: none;
    border: none;
    padding: 8px 12px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    color: #5f6368;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.mail-header .header-actions button:hover {
    background: #f1f3f4;
}

.mail-header .header-actions .compose-btn {
    background: #C6A43F;
    color: #fff;
    padding: 8px 20px;
    border-radius: 24px;
    font-weight: 500;
}

.mail-header .header-actions .compose-btn:hover {
    background: #b8942f;
    box-shadow: 0 2px 8px rgba(198, 164, 63, 0.3);
}

/* ----- MAIN LAYOUT (Gmail 3-column) ----- */
.mail-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0;
    background: #ffffff;
    border: 1px solid #e8eaed;
    border-radius: 12px;
    overflow: hidden;
    min-height: 620px;
}

/* ----- SIDEBAR ----- */
.mail-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #e8eaed;
    display: flex;
    flex-direction: column;
    max-height: 700px;
}

/* Sidebar Tabs */
.mail-tabs {
    padding: 12px 12px 8px 12px;
    display: flex;
    gap: 2px;
    border-bottom: 1px solid #e8eaed;
    background: #fff;
    flex-shrink: 0;
}

.mail-tabs button {
    padding: 6px 14px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #5f6368;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s;
}

.mail-tabs button.active {
    background: #e8f0fe;
    color: #1a73e8;
}

.mail-tabs button:hover {
    background: #f1f3f4;
}

/* Sidebar Search */
.mail-search {
    padding: 8px 12px;
    flex-shrink: 0;
}

.mail-search input {
    width: 100%;
    padding: 7px 14px;
    border: 1px solid #e8eaed;
    border-radius: 20px;
    font-size: 13px;
    background: #fff;
    transition: all 0.2s;
}

.mail-search input:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 2px rgba(198, 164, 63, 0.1);
}

/* Conversation List */
.mail-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
}

.mail-list::-webkit-scrollbar {
    width: 4px;
}
.mail-list::-webkit-scrollbar-track {
    background: transparent;
}
.mail-list::-webkit-scrollbar-thumb {
    background: #dadce0;
    border-radius: 4px;
}

/* ----- Conversation Item (Gmail-style) ----- */
.mail-item {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    cursor: pointer;
    transition: all 0.1s;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f1f3f4;
    gap: 10px;
}

.mail-item:hover {
    background: #f1f3f4;
}

.mail-item.active {
    background: #e8f0fe;
    border-left: 3px solid #C6A43F;
}

.mail-item .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #dadce0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 15px;
    color: #3c4043;
    flex-shrink: 0;
}

.mail-item .avatar.unread {
    background: #C6A43F;
    color: #fff;
}

.mail-item .content {
    flex: 1;
    min-width: 0;
}

.mail-item .content .sender {
    font-weight: 500;
    font-size: 14px;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 6px;
}

.mail-item .content .sender .tag {
    font-size: 10px;
    font-weight: 400;
    color: #5f6368;
    background: #f1f3f4;
    padding: 0 8px;
    border-radius: 10px;
}

.mail-item .content .sender .tag.admin {
    background: #C6A43F;
    color: #fff;
}
.mail-item .content .sender .tag.agent {
    background: #1B5E20;
    color: #fff;
}

.mail-item .content .subject-line {
    font-size: 13px;
    color: #3c4043;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.mail-item .content .preview-line {
    font-size: 12px;
    color: #5f6368;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 1px;
}

.mail-item .meta {
    text-align: right;
    flex-shrink: 0;
}

.mail-item .meta .time {
    font-size: 11px;
    color: #5f6368;
    white-space: nowrap;
}

.mail-item .meta .unread-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #C6A43F;
    border-radius: 50%;
    margin-top: 4px;
}

/* ----- MESSAGE VIEW (Gmail-style) ----- */
.mail-view {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    min-height: 500px;
}

/* View Header */
.mail-view-header {
    padding: 14px 20px;
    border-bottom: 1px solid #e8eaed;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fafbfc;
    flex-shrink: 0;
}

.mail-view-header .sender-block {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mail-view-header .sender-block .av-sm {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #C6A43F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: #fff;
    flex-shrink: 0;
}

.mail-view-header .sender-block .info .name {
    font-weight: 600;
    font-size: 15px;
    color: #1a1a2e;
}

.mail-view-header .sender-block .info .email-line {
    font-size: 12px;
    color: #5f6368;
}

.mail-view-header .listing-tag {
    font-size: 12px;
    color: #C6A43F;
    background: #f1f3f4;
    padding: 4px 14px;
    border-radius: 16px;
}

/* View Body */
.mail-view-body {
    flex: 1;
    padding: 20px 24px;
    overflow-y: auto;
    background: #ffffff;
    max-height: 480px;
}

.mail-view-body::-webkit-scrollbar {
    width: 6px;
}
.mail-view-body::-webkit-scrollbar-track {
    background: transparent;
}
.mail-view-body::-webkit-scrollbar-thumb {
    background: #dadce0;
    border-radius: 4px;
}

/* Message Row - Clean Gmail Style */
.msg-row {
    display: flex;
    margin-bottom: 14px;
    padding: 2px 0;
}

.msg-row.sent {
    justify-content: flex-end;
}

.msg-row.received {
    justify-content: flex-start;
}

.msg-row .bubble {
    max-width: 72%;
    padding: 10px 16px;
    border-radius: 18px;
    word-wrap: break-word;
    line-height: 1.6;
    font-size: 14px;
}

.msg-row.sent .bubble {
    background: #e8f0fe;
    color: #1a1a2e;
    border-bottom-right-radius: 4px;
}

.msg-row.received .bubble {
    background: #f1f3f4;
    color: #1a1a2e;
    border-bottom-left-radius: 4px;
}

.msg-row .bubble .text {
    margin: 0;
    white-space: pre-wrap;
}

.msg-row .bubble .footer {
    font-size: 11px;
    color: #5f6368;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.msg-row.sent .bubble .footer {
    justify-content: flex-end;
}

/* Viewing Request Badge */
.msg-row .bubble .v-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 12px;
    border-radius: 12px;
    margin-bottom: 6px;
    background: #C6A43F;
    color: #fff;
}

.msg-row.received .bubble .v-badge {
    background: #f1f3f4;
    color: #C6A43F;
}

.msg-row .bubble .v-details {
    font-size: 12px;
    color: #5f6368;
    margin-bottom: 4px;
}

/* Date Separator */
.date-divider {
    text-align: center;
    margin: 18px 0 14px 0;
    position: relative;
}

.date-divider span {
    background: #ffffff;
    padding: 0 16px;
    font-size: 12px;
    color: #5f6368;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.date-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e8eaed;
    z-index: 0;
}

/* ----- REPLY AREA (Gmail-style) ----- */
.mail-reply {
    padding: 10px 20px;
    border-top: 1px solid #e8eaed;
    background: #fafbfc;
    flex-shrink: 0;
}

.mail-reply form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.mail-reply .input-wrap {
    flex: 1;
    position: relative;
}

.mail-reply .input-wrap textarea {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #dadce0;
    border-radius: 24px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    resize: none;
    height: 42px;
    transition: all 0.2s;
    background: #fff;
    line-height: 1.5;
}

.mail-reply .input-wrap textarea:focus {
    outline: none;
    border-color: #C6A43F;
    box-shadow: 0 0 0 2px rgba(198, 164, 63, 0.08);
}

.mail-reply .input-wrap textarea::placeholder {
    color: #9aa0a6;
}

.mail-reply .send-btn {
    padding: 10px 24px;
    background: #C6A43F;
    color: #fff;
    border: none;
    border-radius: 24px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    height: 42px;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.mail-reply .send-btn:hover {
    background: #b8942f;
    box-shadow: 0 2px 8px rgba(198, 164, 63, 0.3);
}

.mail-reply .send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}

/* ----- EMPTY STATE ----- */
.empty-mail {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 30px;
    color: #5f6368;
    text-align: center;
    height: 100%;
}

.empty-mail .big-icon {
    font-size: 48px;
    color: #dadce0;
    margin-bottom: 16px;
}

.empty-mail h3 {
    font-size: 18px;
    font-weight: 500;
    color: #1a1a2e;
    margin: 0 0 6px 0;
}

.empty-mail p {
    font-size: 14px;
    color: #5f6368;
    max-width: 300px;
    margin: 0;
}

/* ----- SUCCESS TOAST ----- */
.toast-success {
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

.toast-success i {
    color: #1e7e34;
    font-size: 18px;
}

/* ----- ANIMATIONS ----- */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

.msg-row {
    animation: fadeIn 0.2s ease;
}

/* ----- RESPONSIVE ----- */
@media (max-width: 992px) {
    .mail-app { padding: 12px 16px; }
    .mail-layout { grid-template-columns: 1fr; border-radius: 8px; }
    .mail-sidebar { max-height: 340px; border-right: none; border-bottom: 1px solid #e8eaed; }
    .mail-list { max-height: 260px; }
    .mail-view-body { max-height: 340px; }
    .mail-header .logo-area h1 { font-size: 18px; }
}

@media (max-width: 576px) {
    .mail-app { padding: 8px 10px; }
    .mail-header { flex-wrap: wrap; gap: 8px; }
    .mail-header .header-actions .compose-btn { padding: 6px 14px; font-size: 13px; }
    .mail-view-header { flex-wrap: wrap; gap: 8px; padding: 10px 14px; }
    .mail-view-body { padding: 12px 14px; }
    .mail-reply { padding: 8px 14px; }
    .mail-reply form { flex-direction: column; align-items: stretch; }
    .mail-reply .send-btn { width: 100%; justify-content: center; }
    .msg-row .bubble { max-width: 92%; }
    .mail-item { padding: 8px 10px; }
}
</style>

<!-- ============================================================ -->
<!-- MAIL APP -->
<!-- ============================================================ -->
<div class="mail-app">

    <!-- Header -->
    <div class="mail-header">
        <div class="logo-area">
            <span class="menu-icon"><i class="fas fa-bars"></i></span>
            <h1>
                <span class="mail-icon"><i class="fas fa-envelope"></i></span>
                Messages
                <span class="count-badge">
                    <?php if ($viewingConversation && $conversationInfo): ?>
                        <?= htmlspecialchars($otherUser['name'] ?? 'User') ?>
                    <?php else: ?>
                        <?= count($conversations) ?> conversations
                    <?php endif; ?>
                </span>
            </h1>
        </div>
        <div class="header-actions">
            <?php if ($viewingConversation): ?>
                <a href="/user/messages.php" style="color: #5f6368; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; transition: background 0.2s;" onmouseover="this.style.background='#f1f3f4'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <?php endif; ?>
            <button><i class="fas fa-refresh"></i></button>
            <button><i class="fas fa-ellipsis-v"></i></button>
            <button class="compose-btn"><i class="fas fa-pen"></i> Compose</button>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="mail-layout">

        <!-- Sidebar -->
        <div class="mail-sidebar">
            <!-- Tabs -->
            <div class="mail-tabs">
                <button class="active"><i class="fas fa-inbox"></i> Inbox</button>
                <button><i class="fas fa-clock"></i> Snoozed</button>
                <button><i class="fas fa-check-circle"></i> Done</button>
            </div>

            <!-- Search -->
            <div class="mail-search">
                <input type="text" placeholder="Search messages..." id="mailSearch">
            </div>

            <!-- Conversation List -->
            <div class="mail-list" id="mailList">
                <?php if (empty($conversations)): ?>
                    <div class="empty-mail" style="padding: 30px 20px;">
                        <div class="big-icon"><i class="fas fa-inbox"></i></div>
                        <h3>No messages</h3>
                        <p>Messages will appear here when you receive them.</p>
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
                           class="mail-item <?= $isActive ? 'active' : '' ?>">
                            <div class="avatar <?= $unread ? 'unread' : '' ?>">
                                <?= $avatarLetter ?>
                            </div>
                            <div class="content">
                                <div class="sender">
                                    <?= htmlspecialchars($otherName) ?>
                                    <span class="tag <?= $otherRole ?>"><?= ucfirst($otherRole) ?></span>
                                </div>
                                <div class="subject-line">
                                    <?php if (!empty($conv['listing_id'])): ?>
                                        <span style="color: #C6A43F; font-weight: 500;">[#<?= $conv['listing_id'] ?>]</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($subject) ?>
                                </div>
                                <div class="preview-line">
                                    <?php 
                                    if (!empty($lastMessage)) {
                                        echo htmlspecialchars(substr($lastMessage, 0, 45));
                                        if (strlen($lastMessage) > 45) echo '...';
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

        <!-- Message View -->
        <div class="mail-view">

            <?php if ($viewingConversation && $conversationInfo && !empty($messages)): ?>
                <!-- View Header -->
                <div class="mail-view-header">
                    <div class="sender-block">
                        <div class="av-sm">
                            <?= strtoupper(substr($otherUser['name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="info">
                            <div class="name"><?= htmlspecialchars($otherUser['name'] ?? 'Unknown') ?></div>
                            <div class="email-line">
                                <?= htmlspecialchars($otherUser['email'] ?? '') ?> 
                                <span style="color: #5f6368;">·</span> 
                                <?= ucfirst($otherUser['role'] ?? 'user') ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($listingInfo): ?>
                        <div class="listing-tag">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($listingInfo['title']) ?>
                            <?php if (!empty($listingInfo['price'])): ?>
                                · ₦<?= number_format($listingInfo['price']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- View Body -->
                <div class="mail-view-body" id="mailBody">
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
                        <div class="date-divider">
                            <span><?= $displayDate ?></span>
                        </div>
                    <?php endif; ?>
                        
                        <div class="msg-row <?= $isSent ? 'sent' : 'received' ?>">
                            <div class="bubble">
                                <?php if ($isViewing): ?>
                                    <div class="v-badge">
                                        <i class="fas fa-calendar-check"></i> Viewing Request
                                    </div>
                                    <?php if (!empty($msg['preferred_date'])): ?>
                                        <div class="v-details">
                                            📅 <?= date('M j, Y', strtotime($msg['preferred_date'])) ?>
                                            <?php if (!empty($msg['preferred_time'])): ?>
                                                at <?= htmlspecialchars($msg['preferred_time']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p class="text"><?= nl2br(htmlspecialchars($msg['body'])) ?></p>
                                <div class="footer">
                                    <span><?= htmlspecialchars($senderName) ?></span>
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
                <div class="mail-reply">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="input-wrap">
                            <textarea name="reply_message" placeholder="Type your reply..." required></textarea>
                        </div>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>

            <?php elseif ($viewingConversation && $conversationInfo): ?>
                <!-- Empty conversation -->
                <div class="empty-mail" style="height: 100%;">
                    <div class="big-icon"><i class="fas fa-comment-dots"></i></div>
                    <h3>No messages yet</h3>
                    <p>Start the conversation by sending a message.</p>
                </div>
                
                <div class="mail-reply">
                    <form method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                        <input type="hidden" name="listing_id" value="<?= $conversationInfo['listing_id'] ?? 0 ?>">
                        <input type="hidden" name="listing_type" value="<?= $conversationInfo['listing_type'] ?? 'property' ?>">
                        
                        <div class="input-wrap">
                            <textarea name="reply_message" placeholder="Type your message..." required></textarea>
                        </div>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- No conversation selected -->
                <div class="empty-mail" style="height: 100%;">
                    <div class="big-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the sidebar to view messages.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if (isset($_GET['sent']) && $_GET['sent'] == 1): ?>
        <div class="toast-success">
            <i class="fas fa-check-circle"></i>
            <span>Reply sent successfully!</span>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom
    const mailBody = document.getElementById('mailBody');
    if (mailBody) {
        mailBody.scrollTop = mailBody.scrollHeight;
    }
    
    // Auto-resize textarea (Gmail style)
    document.querySelectorAll('.input-wrap textarea').forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = '42px';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    });
    
    // Enter to send, Shift+Enter for new line
    document.querySelectorAll('.input-wrap textarea').forEach(function(textarea) {
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    });
    
    // Search filter
    const searchInput = document.getElementById('mailSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.mail-item');
            
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
