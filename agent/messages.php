<?php
/**
 * KINAS GROUP — Agent: Messages / Inbox
 * Real conversations list pulled from `messages` table.
 * Right pane shows selected conversation + a compose form.
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAgent();

$db       = Database::getInstance()->getConnection();
$agent_id = (int)$_SESSION['user_id'];

// ── Build conversation list: per distinct counterpart ─────────
$convStmt = $db->prepare("
    SELECT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_user_id,
        MAX(m.id) AS last_message_id,
        SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread_count
    FROM messages m
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_id DESC
");
$convStmt->execute([$agent_id, $agent_id, $agent_id, $agent_id]);
$conversationRows = $convStmt->fetchAll(PDO::FETCH_ASSOC);

// Load the counterpart user data and the latest message per conversation
$userMap = [];
$lastMsgMap = [];
if (!empty($conversationRows)) {
    $userIds = array_unique(array_column($conversationRows, 'other_user_id'));
    $ph = implode(',', array_fill(0, count($userIds), '?'));
    $uStmt = $db->prepare("SELECT id, name, email, phone, avatar FROM users WHERE id IN ($ph)");
    $uStmt->execute(array_values($userIds));
    foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $userMap[(int)$u['id']] = $u;
    }
    foreach ($conversationRows as $c) {
        $lmStmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
        $lmStmt->execute([$c['last_message_id']]);
        $lastMsgMap[(int)$c['other_user_id']] = $lmStmt->fetch(PDO::FETCH_ASSOC);
    }
}

// ── Currently-selected conversation ──────────────────────────
$selectedUserId = (int)($_GET['user'] ?? 0);
$selectedUser   = null;
$selectedMessages = [];
if ($selectedUserId && isset($userMap[$selectedUserId])) {
    $selectedUser = $userMap[$selectedUserId];
    $msgsStmt = $db->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $msgsStmt->execute([$agent_id, $selectedUserId, $selectedUserId, $agent_id]);
    $selectedMessages = $msgsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark messages from this user as read
    $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
       ->execute([$selectedUserId, $agent_id]);
}

// ── Unread badge for sidebar ─────────────────────────────────
$totalUnread = 0;
foreach ($conversationRows as $c) $totalUnread += (int)$c['unread_count'];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrf = Security::generateCSRFToken();
require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.messages-container { display: grid; grid-template-columns: 320px 1fr; gap: 20px; background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; min-height: 600px; }
.conversation-list { border-right: 1px solid #E0E0E0; display: flex; flex-direction: column; }
.conversation-header { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; }
.conversation-header h3 { font-family: 'Prata', serif; font-size: 16px; }
.conversation-header .badge-count { display:inline-block; margin-left:8px; background:#DC2626; color:white; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
.conversations { overflow-y: auto; flex: 1; }
.conversation-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #F0F0F0; cursor: pointer; text-decoration: none; color: inherit; transition: background 0.2s; }
.conversation-item:hover { background: #F8F8F8; }
.conversation-item.active { background: #FEFBF5; border-left: 3px solid #C6A43F; }
.conversation-avatar { width: 40px; height: 40px; border-radius: 50%; background: #C6A43F; color: #0A0A0A; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.conversation-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.conversation-info { flex: 1; min-width: 0; }
.conversation-info h4 { font-size: 14px; color: #0A0A0A; margin-bottom: 2px; }
.conversation-info p { font-size: 12px; color: #888; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conversation-meta { text-align: right; font-size: 11px; color: #999; }
.unread-badge { background: #DC2626; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; font-weight: 600; margin-top: 4px; display: inline-block; }
.chat-area { display: flex; flex-direction: column; }
.chat-header { padding: 16px 20px; border-bottom: 1px solid #E0E0E0; display: flex; align-items: center; gap: 12px; }
.chat-header .chat-user { display: flex; align-items: center; gap: 12px; flex: 1; }
.chat-header .chat-avatar { width: 40px; height: 40px; border-radius: 50%; background: #C6A43F; color: #0A0A0A; display: flex; align-items: center; justify-content: center; font-weight: 600; }
.chat-header .chat-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.chat-header h4 { font-size: 14px; }
.chat-header small { display:block; font-size: 11px; color: #888; }
.chat-messages { flex: 1; padding: 20px; overflow-y: auto; background: #FAFBFC; display: flex; flex-direction: column; gap: 12px; min-height: 360px; max-height: 60vh; }
.message { display: flex; }
.message.sent { justify-content: flex-end; }
.message-bubble { max-width: 70%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.45; word-wrap: break-word; }
.message.received .message-bubble { background: white; color: #0A0A0A; border: 1px solid #E8E8E8; border-bottom-left-radius: 4px; }
.message.sent .message-bubble { background: #C6A43F; color: #0A0A0A; border-bottom-right-radius: 4px; }
.message-meta { font-size: 10px; color: #999; margin-top: 4px; padding: 0 4px; }
.message.sent .message-meta { text-align: right; }
.chat-input { padding: 14px 20px; border-top: 1px solid #E0E0E0; background: #F8F8F8; display: flex; gap: 10px; }
.chat-input input { flex: 1; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 24px; font-family: 'Inter', sans-serif; font-size: 14px; }
.chat-input input:focus { outline: none; border-color: #C6A43F; }
.send-btn { background: #C6A43F; border: none; color: #0A0A0A; padding: 10px 18px; border-radius: 24px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.send-btn:hover { background: #A8882E; }
.send-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.empty-state { padding: 60px 24px; text-align: center; color: #999; }
.empty-state i { font-size: 48px; color: #C6A43F; opacity: 0.4; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 14px; }
.empty-state a, .empty-state button { color: #C6A43F; font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; }
.composer-link { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #C6A43F; color: #0A0A0A; border-radius: 24px; text-decoration: none; font-weight: 600; margin-top: 12px; }
.composer-link:hover { background: #A8882E; }
.flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
.flash.success { background: #E8F5E9; color: #2E7D32; border: 1px solid #C8E6C9; }
.flash.error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
@media (max-width: 900px) { .messages-container { grid-template-columns: 1fr; } .conversation-list { border-right: none; border-bottom: 1px solid #E0E0E0; max-height: 280px; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <?php if ($flashSuccess): ?><div class="flash success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError):   ?><div class="flash error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <div class="agent-header">
        <h1><i class="fas fa-envelope"></i> Messages</h1>
        <a href="#compose" class="composer-link" onclick="document.getElementById('compose').scrollIntoView({behavior:'smooth'}); return false;">
            <i class="fas fa-pen"></i> Compose
        </a>
    </div>

    <div class="messages-container">
        <div class="conversation-list">
            <div class="conversation-header">
                <h3>Conversations
                    <?php if ($totalUnread > 0): ?>
                        <span class="badge-count"><?= (int)$totalUnread ?> unread</span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="conversations">
                <?php if (empty($conversationRows)): ?>
                    <div class="empty-state" style="padding: 40px 16px;">
                        <i class="fas fa-inbox"></i>
                        <p>No messages yet.</p>
                        <p style="margin-top:8px; font-size:12px;">When buyers message you about your listings, they'll appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversationRows as $c):
                        $other = $userMap[(int)$c['other_user_id']] ?? null;
                        $last  = $lastMsgMap[(int)$c['other_user_id']] ?? null;
                        if (!$other) continue;
                        $initials = strtoupper(substr($other['name'], 0, 1) . (strpos($other['name'],' ') !== false ? substr($other['name'], strpos($other['name'],' ')+1, 1) : ''));
                        $isActive = $selectedUserId === (int)$other['id'];
                    ?>
                    <a href="?user=<?= (int)$other['id'] ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>">
                        <div class="conversation-avatar">
                            <?php if (!empty($other['avatar'])): ?>
                                <img src="<?= htmlspecialchars($other['avatar']) ?>" alt="">
                            <?php else: ?>
                                <?= htmlspecialchars($initials) ?>
                            <?php endif; ?>
                        </div>
                        <div class="conversation-info">
                            <h4><?= htmlspecialchars($other['name']) ?></h4>
                            <p><?= htmlspecialchars(mb_strimwidth((string)($last['body'] ?? ''), 0, 50, '…')) ?></p>
                        </div>
                        <div class="conversation-meta">
                            <?= htmlspecialchars($last ? date('M j', strtotime($last['created_at'])) : '') ?>
                            <?php if ((int)$c['unread_count'] > 0): ?>
                                <br><span class="unread-badge"><?= (int)$c['unread_count'] ?> new</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <?php if ($selectedUser && $selectedMessages !== null): ?>
                <div class="chat-header">
                    <div class="chat-user">
                        <div class="chat-avatar">
                            <?php
                                $initials = strtoupper(substr($selectedUser['name'], 0, 1) . (strpos($selectedUser['name'],' ') !== false ? substr($selectedUser['name'], strpos($selectedUser['name'],' ')+1, 1) : ''));
                                if (!empty($selectedUser['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($selectedUser['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                        </div>
                        <div>
                            <h4><?= htmlspecialchars($selectedUser['name']) ?></h4>
                            <small><?= htmlspecialchars($selectedUser['email']) ?><?= !empty($selectedUser['phone']) ? ' · ' . htmlspecialchars($selectedUser['phone']) : '' ?></small>
                        </div>
                    </div>
                    <?php if (!empty($selectedUser['phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($selectedUser['phone']) ?>" class="send-btn" style="text-decoration:none;"><i class="fas fa-phone"></i> Call</a>
                    <?php endif; ?>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($selectedMessages)): ?>
                        <div class="empty-state" style="padding: 40px 16px; margin: auto;">
                            <p>No messages yet. Send the first message below.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($selectedMessages as $m): ?>
                            <div class="message <?= (int)$m['sender_id'] === $agent_id ? 'sent' : 'received' ?>">
                                <div>
                                    <div class="message-bubble"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                                    <div class="message-meta"><?= htmlspecialchars(date('M j, g:i a', strtotime($m['created_at']))) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form class="chat-input" id="sendForm" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="receiver_id" value="<?= (int)$selectedUser['id'] ?>">
                    <input type="text" name="message" id="messageInput" placeholder="Type your message…" required>
                    <button type="submit" class="send-btn" id="sendBtn"><i class="fas fa-paper-plane"></i> Send</button>
                </form>

                <script>
                document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
                document.getElementById('sendForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    var input = document.getElementById('messageInput');
                    var msg = input.value.trim();
                    if (!msg) return;
                    var btn = document.getElementById('sendBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
                    try {
                        var fd = new FormData(this);
                        var res = await fetch('/api/messages/send.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                        var data = await res.json();
                        if (data.success) {
                            // Optimistic bubble
                            var chat = document.getElementById('chatMessages');
                            var empty = chat.querySelector('.empty-state');
                            if (empty) empty.remove();
                            var div = document.createElement('div');
                            div.className = 'message sent';
                            div.innerHTML = '<div><div class="message-bubble">' + msg.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</div><div class="message-meta">just now</div></div>';
                            chat.appendChild(div);
                            chat.scrollTop = chat.scrollHeight;
                            input.value = '';
                        } else {
                            alert(data.error || 'Failed to send.');
                        }
                    } catch (err) {
                        alert('Network error. Please try again.');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                        input.focus();
                    }
                });
                </script>
            <?php else: ?>
                <div class="empty-state" style="margin: auto; padding: 60px 24px;">
                    <i class="fas fa-comments"></i>
                    <p>Select a conversation to read messages,</p>
                    <p>or compose a new one below.</p>
                </div>
                <div id="compose" style="border-top: 1px solid #E0E0E0; padding: 20px; background: #F8F8F8;">
                    <form id="composeForm" autocomplete="off" style="display:flex; gap:10px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="email" name="recipient_email" id="recipientEmail" placeholder="Recipient email" required style="flex: 1; min-width: 200px; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 24px; font-family: 'Inter', sans-serif; font-size: 14px;">
                        <input type="text" name="message" id="composeMessage" placeholder="Type a message…" required style="flex: 2; min-width: 240px; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 24px; font-family: 'Inter', sans-serif; font-size: 14px;">
                        <input type="text" name="subject" placeholder="Subject (optional)" style="flex: 1; min-width: 160px; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 24px; font-family: 'Inter', sans-serif; font-size: 14px;">
                        <button type="submit" class="send-btn" id="composeBtn"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                </div>
                <script>
                document.getElementById('composeForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    var btn = document.getElementById('composeBtn');
                    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    try {
                        var fd = new FormData(this);
                        // First lookup the recipient by email
                        var lookup = await fetch('/api/messages/lookup.php?email=' + encodeURIComponent(fd.get('recipient_email')), { credentials: 'same-origin' });
                        var lk = await lookup.json();
                        if (!lk.success || !lk.user_id) {
                            alert('No user found with that email.');
                            btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                            return;
                        }
                        fd.set('receiver_id', lk.user_id);
                        fd.delete('recipient_email');
                        var res = await fetch('/api/messages/send.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                        var data = await res.json();
                        if (data.success) {
                            window.location.href = '?user=' + lk.user_id;
                        } else {
                            alert(data.error || 'Failed to send.');
                        }
                    } catch (err) {
                        alert('Network error.');
                    } finally {
                        btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                    }
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
