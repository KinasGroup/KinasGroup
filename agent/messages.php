<?php
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

// Auth: handled by SessionManager::requireAgent()

// KYC soft-guard
$kycStatus='pending';try{$st=Database::getInstance()->getConnection()->prepare("SELECT verification_status FROM agent_profiles WHERE user_id = ?");$st->execute([(int)$_SESSION['user_id']]);$kycStatus=$st->fetchColumn()?:'pending';}catch(Exception $e){}

require_once __DIR__ . '/../templates/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #F5F7FA; }
.agent-container { max-width: 1400px; margin: 0 auto; padding: 30px; }
.agent-header { margin-bottom: 32px; }
.agent-header h1 { font-family: 'Prata', serif; font-size: 28px; color: #0A0A0A; }
.agent-header h1 i { color: #C6A43F; margin-right: 12px; }
.messages-container { display: grid; grid-template-columns: 350px 1fr; gap: 0; background: white; border-radius: 20px; border: 1px solid #E0E0E0; overflow: hidden; min-height: 600px; }
.conversation-list { border-right: 1px solid #E0E0E0; display: flex; flex-direction: column; }
.conversation-header { padding: 20px; border-bottom: 1px solid #E0E0E0; }
.conversation-header h3 { font-size: 16px; font-weight: 600; color: #C6A43F; margin-bottom: 16px; }
.search-messages { position: relative; }
.search-messages i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #C6A43F; }
.search-messages input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid #E0E0E0; border-radius: 10px; font-family: 'Inter', sans-serif; }
.conversations { flex: 1; overflow-y: auto; }
.conversation-item { display: flex; align-items: center; gap: 12px; padding: 16px 20px; cursor: pointer; transition: all 0.3s; }
.conversation-item:hover { background: #F8F8F8; }
.conversation-item.active { background: rgba(198,164,63,0.05); border-left: 3px solid #C6A43F; }
.conversation-avatar { width: 48px; height: 48px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0A0A0A; flex-shrink: 0; }
.conversation-info { flex: 1; min-width: 0; }
.conversation-info h4 { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.conversation-info p { font-size: 12px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.time { font-size: 10px; color: #999; }
.unread-badge { background: #C6A43F; color: #0A0A0A; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 10px; min-width: 20px; text-align: center; }
.chat-area { display: flex; flex-direction: column; height: 100%; }
.chat-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #E0E0E0; }
.chat-user { display: flex; align-items: center; gap: 12px; }
.chat-avatar { width: 40px; height: 40px; background: #C6A43F; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0A0A0A; }
.chat-user h4 { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
.chat-user span { font-size: 11px; color: #666; }
.btn-more { background: none; border: none; color: #C6A43F; cursor: pointer; padding: 8px; }
.chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px; }
.message { display: flex; }
.message.received { justify-content: flex-start; }
.message.sent { justify-content: flex-end; }
.message-content { max-width: 70%; padding: 12px 16px; border-radius: 18px; position: relative; }
.message.received .message-content { background: #F5F5F5; border-bottom-left-radius: 4px; }
.message.sent .message-content { background: #C6A43F; color: #0A0A0A; border-bottom-right-radius: 4px; }
.message-content p { font-size: 13px; margin-bottom: 4px; }
.message-time { font-size: 10px; opacity: 0.7; display: block; text-align: right; }
.chat-input { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-top: 1px solid #E0E0E0; background: #F8F8F8; }
.attach-btn { background: none; border: none; color: #C6A43F; cursor: pointer; font-size: 18px; }
.chat-input input { flex: 1; padding: 12px 16px; border: 1px solid #E0E0E0; border-radius: 24px; font-family: 'Inter', sans-serif; }
.send-btn { background: #C6A43F; border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; }
.send-btn:hover { transform: scale(1.05); }
@media (max-width: 768px) { .agent-container { padding: 20px; } .messages-container { grid-template-columns: 1fr; } .conversation-list { border-right: none; border-bottom: 1px solid #E0E0E0; max-height: 300px; } }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . '/../includes/partials/agent-sidebar.php'; ?>
<main class="je-dash-main">

<div class="agent-container">
    <div class="agent-header"><h1><i class="fas fa-envelope"></i> Messages</h1><p>Manage inquiries from potential buyers</p></div>

    <div class="messages-container">
        <div class="conversation-list"><div class="conversation-header"><h3>Conversations</h3><div class="search-messages"><i class="fas fa-search"></i><input type="text" placeholder="Search messages..."></div></div>
        <div class="conversations"><div class="conversation-item active"><div class="conversation-avatar">JD</div><div class="conversation-info"><h4>John Doe</h4><p>Is this still available? I'm very interested...</p><span class="time">2 min ago</span></div><div class="unread-badge">2</div></div>
        <div class="conversation-item"><div class="conversation-avatar">JS</div><div class="conversation-info"><h4>Jane Smith</h4><p>Thank you for the information! I'd like to...</p><span class="time">1 hour ago</span></div></div>
        <div class="conversation-item"><div class="conversation-avatar">MR</div><div class="conversation-info"><h4>Michael Roberts</h4><p>Can you provide more details about the...</p><span class="time">3 hours ago</span></div></div>
        <div class="conversation-item"><div class="conversation-avatar">SL</div><div class="conversation-info"><h4>Sarah Lee</h4><p>What's the condition of the vehicle? Has...</p><span class="time">Yesterday</span></div></div></div></div>

        <div class="chat-area"><div class="chat-header"><div class="chat-user"><div class="chat-avatar">JD</div><div><h4>John Doe</h4><span>Inquiry about: 2024 Mercedes-Benz S-Class</span></div></div><button class="btn-more"><i class="fas fa-ellipsis-v"></i></button></div>
        <div class="chat-messages"><div class="message received"><div class="message-content"><p>Hi, I'm very interested in the 2024 Mercedes-Benz S-Class you listed. Is it still available?</p><span class="message-time">10:30 AM</span></div></div>
        <div class="message sent"><div class="message-content"><p>Yes, it's still available! Would you like to schedule a viewing?</p><span class="message-time">10:32 AM</span></div></div>
        <div class="message received"><div class="message-content"><p>That would be great. When is a good time? Also, can you share more photos?</p><span class="message-time">10:35 AM</span></div></div>
        <div class="message sent"><div class="message-content"><p>I'm available tomorrow afternoon. I'll send you more photos shortly. What's your email?</p><span class="message-time">10:38 AM</span></div></div>
        <div class="message received"><div class="message-content"><p>Great! My email is john.doe@example.com. Looking forward to seeing the car!</p><span class="message-time">10:40 AM</span></div></div></div>
        <div class="chat-input"><button class="attach-btn"><i class="fas fa-paperclip"></i></button><input type="text" placeholder="Type your message..."><button class="send-btn"><i class="fas fa-paper-plane"></i></button></div></div>
    </div>
</div>

</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
