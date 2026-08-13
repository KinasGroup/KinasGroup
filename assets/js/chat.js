// /assets/js/chat.js
// KINAS GROUP — Rebuilt Messenger client (REVAMP)
// Pairs with assets/css/chat.css and api/messages/* endpoints.
//
// REVAMP FEATURES:
//  • Formal Gmail-style inquiry card on first messages (inquiry_meta).
//  • Attachments upload on selection with live progress over the
//    thumbnail; Send is disabled until every upload is confirmed.
//  • Voice notes: mobile = WhatsApp hold-to-record + slide-left cancel;
//    PC = click-to-record with send/discard bar; 3:00 cap keeps the
//    note pending (no silent auto-send).
//  • Sounds: WebAudio send blip + receive blip, mute toggle persisted.
//  • Delisted listings: composer locked with a clear closed notice.
//  • listing=0 opens auto-resolve to the pair's latest listing thread.
//  • UNREAD VISIBILITY: incoming messages that are still unread render
//    with a darker-blue bubble (.is-unread) until they are read.
(function () {
'use strict';
var root = document.getElementById('chatRoot');
if (!root) return;

// ------------------------------------------------------------
// CONFIG + STATE
// ------------------------------------------------------------
var CFG = {
    csrf: root.getAttribute('data-csrf') || '',
    userId: parseInt(root.getAttribute('data-user-id') || '0', 10),
    role: root.getAttribute('data-role') || 'user',
    openOther: parseInt(root.getAttribute('data-open-other') || '0', 10),
    openListing: parseInt(root.getAttribute('data-open-listing') || '0', 10),
    openType: root.getAttribute('data-open-type') || '',
    epConversations: '/api/messages/conversations.php',
    epThread: '/api/messages/thread.php',
    epMarkRead: '/api/messages/mark-read.php',
    epSend: '/api/messages/send.php',
    epUpload: '/api/messages/upload-media.php',
    pollThreadMs: 5000,
    pollListMs: 20000,
    maxImages: 4,
    maxImageBytes: 10 * 1024 * 1024,
    maxVoiceSeconds: 180
};
var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
var state = {
    conversations: [],
    current: null,
    canReply: false,
    closed: false,
    listing: null,
    lastId: 0,
    prevDate: null,
    msgEls: {},
    uploads: [],              // {id,file,objectUrl,serverUrl,progress,status,xhr}
    upSeq: 0,
    search: '',
    currentAudioStop: null,
    rec: null,
    recChunks: [],
    recMime: '',
    recSeconds: 0,
    recTimer: null,
    recCancelled: false,
    recHold: false,
    recStopMode: 'send',
    recTouchStartX: 0,
    recTouchCancelled: false,
    recPending: null,         // {blob, ext, seconds} awaiting send/discard (PC)
    markReadQueued: false,
    prevUnreadTotal: null,
    muted: false
};
try { state.muted = localStorage.getItem('kinas_chat_muted') === '1'; } catch (e) {}

// ------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------
function qs(id) { return document.getElementById(id); }
function el(tag, cls) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    return n;
}
function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>'') return '&gt;';
        if (m === '"') return '&quot;';
        return '&#39;';
    });
}
function money(n) { return '₦' + Number(n || 0).toLocaleString('en-NG'); }
function fmtDur(sec) {
    sec = Math.max(0, Math.round(sec || 0));
    var m = Math.floor(sec / 60), s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
}
// Gmail-style formal timestamp: "Wed, Aug 13, 2026, 2:32 PM"
function fmtGmailDate(dt) {
    var d = new Date(dt);
    if (!dt || isNaN(d.getTime())) return '';
    var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var h = d.getHours();
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12; if (h === 0) h = 12;
    var min = d.getMinutes();
    return days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' +
        d.getFullYear() + ', ' + h + ':' + (min < 10 ? '0' : '') + min + ' ' + ampm;
}
function toast(msg, type) {
    if (typeof window.kinasToast === 'function') { window.kinasToast(msg, type || 'info'); return; }
    if (typeof window.showSuccessBanner === 'function') { window.showSuccessBanner(msg, type === 'error'); return; }
    console.warn('[chat]', msg);
}
function roleBadgeClass(role) {
    if (role === 'agent') return 'role-agent';
    if (role === 'admin') return 'role-admin';
    if (role === 'business') return 'role-business';
    return 'role-user';
}
function barsFor(seed, count) {
    var h = 0, i;
    for (i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
    var out = [];
    for (i = 0; i < count; i++) {
        h = (h * 1103515245 + 12345) | 0;
        out.push(6 + (Math.abs(h >> 16) % 19));
    }
    return out;
}
function stopCurrentAudio() {
    if (state.currentAudioStop) {
        try { state.currentAudioStop(); } catch (e) {}
        state.currentAudioStop = null;
    }
}

// ------------------------------------------------------------
// SOUNDS — WebAudio synth (no binary assets to host)
// ------------------------------------------------------------
var AC = null;
function audioCtx() {
    var Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    if (!AC) AC = new Ctor();
    if (AC.state === 'suspended') { AC.resume().catch(function () {}); }
    return AC;
}
function tone(freq, delay, dur, peak) {
    var ctx = audioCtx();
    if (!ctx) return;
    try {
        var o = ctx.createOscillator();
        var g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = freq;
        var t0 = ctx.currentTime + delay;
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(peak || 0.07, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        o.connect(g); g.connect(ctx.destination);
        o.start(t0); o.stop(t0 + dur + 0.05);
    } catch (e) {}
}
function playSend() { if (state.muted) return; tone(880, 0, 0.12, 0.06); tone(1320, 0.09, 0.14, 0.05); }
function playReceive() { if (state.muted) return; tone(660, 0, 0.12, 0.07); tone(520, 0.10, 0.16, 0.06); }

// ------------------------------------------------------------
// BUILD UI SKELETON
// ------------------------------------------------------------
root.innerHTML =
    '<div class="chat-app" id="chatApp">' +
    '  <div class="chat-list-pane">' +
    '    <div class="chat-list-head">' +
    '      <div class="chat-list-title"><i class="fas fa-envelope"></i> Messages <span class="chat-list-count" id="chatListCount"></span></div>' +
    '      <button type="button" class="chat-sound-btn" id="chatSoundBtn" title="Message sounds"><i class="fas fa-volume-up"></i></button>' +
    '    </div>' +
    '    <div class="chat-search"><input type="text" id="chatSearch" placeholder="Search conversations…" autocomplete="off"></div>' +
    '    <div class="chat-list" id="chatList"></div>' +
    '  </div>' +
    '  <div class="chat-thread-pane">' +
    '    <div class="chat-thread-head" id="chatThreadHead" style="display:none">' +
    '      <button type="button" class="chat-back-btn" id="chatBackBtn" title="Back to list"><i class="fas fa-arrow-left"></i></button>' +
    '      <div class="chat-thread-avatar" id="chatThreadAvatar">?</div>' +
    '      <div class="chat-thread-info">' +
    '        <div class="chat-thread-name" id="chatThreadName"></div>' +
    '        <div class="chat-thread-sub" id="chatThreadSub"></div>' +
    '      </div>' +
    '      <a class="chat-listing-ctx" id="chatListingCtx" href="#" target="_blank" rel="noopener" style="display:none">' +
    '        <img id="chatListingThumb" alt="">' +
    '        <div class="chat-listing-ctx-text">' +
    '          <div class="chat-listing-ctx-title" id="chatListingTitle"></div>' +
    '          <div class="chat-listing-ctx-price" id="chatListingPrice"></div>' +
    '        </div>' +
    '      </a>' +
    '    </div>' +
    '    <div class="chat-messages" id="chatMessages">' +
    '      <div class="chat-empty" id="chatEmpty"><i class="fas fa-comments"></i><h3>Select a conversation</h3><p>Choose a conversation from the list to view messages.</p></div>' +
    '    </div>' +
    '    <div class="chat-pending-media" id="chatPendingMedia"></div>' +
    '    <div class="chat-composer-note" id="chatComposerNote" style="display:none"></div>' +
    '    <form class="chat-composer" id="chatComposer" style="display:none">' +
    '      <button type="button" class="chat-attach-btn" id="chatAttachBtn" title="Attach images (max 4, 10MB each)"><i class="far fa-image"></i></button>' +
    '      <input type="file" id="chatFileInput" accept="image/jpeg,image/png,image/webp" multiple hidden>' +
    '      <button type="button" class="chat-mic-btn" id="chatMicBtn" title="' + (isTouch ? 'Hold to record a voice note' : 'Record a voice note') + '"><i class="fas fa-microphone"></i></button>' +
    '      <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message…"></textarea>' +
    '      <button type="submit" class="chat-send-btn" id="chatSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>' +
    '      <div class="chat-recording">' +
    '        <span class="chat-rec-dot"></span>' +
    '        <span class="chat-rec-time" id="chatRecTime">0:00</span>' +
    '        <span class="chat-rec-hint" id="chatRecHint">Recording…</span>' +
    '        <button type="button" class="chat-rec-cancel" id="chatRecCancel" title="Discard recording"><i class="fas fa-trash"></i></button>' +
    '        <button type="button" class="chat-rec-send" id="chatRecSend" title="Stop and send voice note"><i class="fas fa-paper-plane"></i></button>' +
    '      </div>' +
    '      <div class="chat-rec-pending">' +
    '        <i class="fas fa-microphone" style="color:#C6A43F;"></i>' +
    '        <span class="chat-rec-pending-label" id="chatPendingRecLabel">Voice note ready</span>' +
    '        <button type="button" class="chat-rec-cancel" id="chatPendingRecDiscard" title="Discard voice note"><i class="fas fa-trash"></i></button>' +
    '        <button type="button" class="chat-rec-send" id="chatPendingRecSend" title="Send voice note"><i class="fas fa-paper-plane"></i></button>' +
    '      </div>' +
    '    </form>' +
    '  </div>' +
    '</div>' +
    '<div class="chat-lightbox" id="chatLightbox">' +
    '  <img id="chatLightboxImg" src="" alt="">' +
    '  <button type="button" class="chat-lightbox-close" id="chatLightboxClose" title="Close">✕</button>' +
    '</div>';
var app = qs('chatApp'), listWrap = qs('chatList'), listCount = qs('chatListCount'),
    searchInput = qs('chatSearch'), messagesWrap = qs('chatMessages'),
    threadHead = qs('chatThreadHead'), threadAvatar = qs('chatThreadAvatar'),
    threadName = qs('chatThreadName'), threadSub = qs('chatThreadSub'),
    listingCtx = qs('chatListingCtx'), listingThumb = qs('chatListingThumb'),
    listingTitle = qs('chatListingTitle'), listingPrice = qs('chatListingPrice'),
    composer = qs('chatComposer'), composerNote = qs('chatComposerNote'),
    input = qs('chatInput'), sendBtn = qs('chatSendBtn'),
    attachBtn = qs('chatAttachBtn'), fileInput = qs('chatFileInput'),
    micBtn = qs('chatMicBtn'), pendingWrap = qs('chatPendingMedia'),
    recTimeEl = qs('chatRecTime'), recHint = qs('chatRecHint'),
    recCancel = qs('chatRecCancel'), recSend = qs('chatRecSend'),
    pendingRecLabel = qs('chatPendingRecLabel'),
    pendingRecSend = qs('chatPendingRecSend'), pendingRecDiscard = qs('chatPendingRecDiscard'),
    backBtn = qs('chatBackBtn'), soundBtn = qs('chatSoundBtn'),
    lightbox = qs('chatLightbox'), lightboxImg = qs('chatLightboxImg'), lightboxClose = qs('chatLightboxClose');

function updateSoundBtn() {
    soundBtn.innerHTML = state.muted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-up"></i>';
    soundBtn.title = state.muted ? 'Message sounds off — click to enable' : 'Message sounds on — click to mute';
    soundBtn.classList.toggle('is-muted', state.muted);
}
soundBtn.addEventListener('click', function () {
    state.muted = !state.muted;
    try { localStorage.setItem('kinas_chat_muted', state.muted ? '1' : '0'); } catch (e) {}
    updateSoundBtn();
    if (!state.muted) playSend();
});
updateSoundBtn();

// ------------------------------------------------------------
// CONVERSATION LIST
// ------------------------------------------------------------
function loadConversations() {
    fetch(CFG.epConversations, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) return;
            state.conversations = data.conversations || [];
            var t = totalUnread();
            if (state.prevUnreadTotal !== null && t > state.prevUnreadTotal && !state.current) playReceive();
            state.prevUnreadTotal = t;
            renderList();
        })
        .catch(function () {});
}
function totalUnread() {
    var n = 0;
    state.conversations.forEach(function (c) { n += (c.unread_count || 0); });
    return n;
}
function renderList() {
    var unread = totalUnread();
    listCount.textContent = unread > 0 ? (unread + ' unread') : (state.conversations.length + ' conversations');
    var q = state.search.toLowerCase();
    listWrap.innerHTML = '';
    var shown = 0;
    state.conversations.forEach(function (c) {
        var hay = ((c.other_name || '') + ' ' + (c.last_preview || '') + ' ' + (c.listing_title || '')).toLowerCase();
        if (q && hay.indexOf(q) === -1) return;
        shown++;
        var active = state.current && state.current.other === c.other_user_id && state.current.listing === c.listing_id;
        var item = el('div', 'chat-conv-item' + (active ? ' is-active' : '') + ((c.unread_count || 0) > 0 ? ' has-unread' : ''));
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        var av = el('div', 'chat-avatar' + ((c.unread_count || 0) > 0 ? ' is-unread' : ''));
        av.textContent = (c.other_name || '?').charAt(0).toUpperCase();
        var body = el('div', 'chat-conv-body');
        var top = el('div', 'chat-conv-top');
        var name = el('div', 'chat-conv-name');
        name.innerHTML = esc(c.other_name || 'Unknown') +
            ' <span class="chat-role-badge ' + roleBadgeClass(c.other_role) + '">' + esc(c.other_role || 'user') + '</span>';
        var time = el('div', 'chat-time');
        time.textContent = c.last_time_formatted || '';
        top.appendChild(name); top.appendChild(time);
        var prev = el('div', 'chat-conv-preview');
        var prevText = (c.last_sender_is_me ? 'You: ' : '') + (c.last_preview || 'No messages yet');
        prev.innerHTML = esc(prevText) +
            (c.listing_closed ? ' <i class="fas fa-lock chat-conv-lock" title="Listing delisted — thread closed"></i>' : '') +
            ((c.unread_count || 0) > 0 ? ' <span class="chat-unread-count">' + (c.unread_count) + '</span>' : '');
        body.appendChild(top); body.appendChild(prev);
        item.appendChild(av); item.appendChild(body);
        function open() { openConversation(c.other_user_id, c.listing_id, c.listing_type); }
        item.addEventListener('click', open);
        item.addEventListener('keydown', function (e) { if (e.key === 'Enter') open(); });
        listWrap.appendChild(item);
    });
    if (shown === 0) {
        var empty = el('div', 'chat-list-empty');
        empty.innerHTML = '<i class="fas fa-inbox"></i>' + (q ? 'No conversations match your search.' : 'No messages yet. Conversations start from a product listing.');
        listWrap.appendChild(empty);
    }
}

// ------------------------------------------------------------
// THREAD
// ------------------------------------------------------------
function openConversation(otherId, listingId, listingType) {
    state.current = { other: otherId, listing: listingId, type: listingType || '' };
    state.lastId = 0;
    state.prevDate = null;
    state.msgEls = {};
    messagesWrap.innerHTML = '';
    threadHead.style.display = 'flex';
    app.classList.add('show-thread');
    try {
        var base = window.location.pathname;
        window.history.replaceState(null, '', base + '?user=' + otherId + '&listing=' + (listingId || 0));
    } catch (e) {}
    loadThread(true);
    loadConversations();
}
function loadThread(initial) {
    if (!state.current) return;
    var url = CFG.epThread +
        '?other_user=' + state.current.other +
        '&listing=' + (state.current.listing || 0) +
        '&since_id=0';
    fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success || !state.current) return;
            var conv = data.conversation || {};
            state.canReply = !!conv.can_reply;
            state.closed = !!conv.closed;
            state.listing = conv.listing || null;
            // listing=0 auto-resolution sync (thread.php resolves to the
            // pair's latest listing thread) — keep client in lockstep.
            if (state.listing && state.listing.listing_id && state.current.listing !== state.listing.listing_id) {
                state.current.listing = state.listing.listing_id;
                state.current.type = state.listing.listing_type || state.current.type;
                try {
                    window.history.replaceState(null, '', window.location.pathname + '?user=' + state.current.other + '&listing=' + state.current.listing);
                } catch (e) {}
            }
            // Header
            var otherName = conv.other_name || 'Unknown';
            threadAvatar.textContent = otherName.charAt(0).toUpperCase();
            threadName.innerHTML = esc(otherName) +
                ' <span class="chat-role-badge ' + roleBadgeClass(conv.other_role) + '">' + esc(conv.other_role || 'user') + '</span>';
            threadSub.textContent = (conv.other_role === 'agent' ? 'Agent' : conv.other_role === 'admin' ? 'Admin' : 'Customer');
            if (state.listing && state.listing.listing_id) {
                listingCtx.style.display = 'flex';
                listingCtx.href = state.listing.listing_url || '#';
                listingTitle.textContent = state.listing.listing_title || 'Listing #' + state.listing.listing_id;
                listingPrice.textContent = state.listing.listing_price ? money(state.listing.listing_price) : '';
                if (state.listing.listing_thumb) { listingThumb.src = state.listing.listing_thumb; listingThumb.style.display = 'block'; }
                else { listingThumb.style.display = 'none'; }
            } else {
                listingCtx.style.display = 'none';
            }
            // Composer visibility + closed notice
            if (state.canReply) {
                composer.style.display = 'flex';
                composerNote.style.display = 'none';
            } else {
                composer.style.display = 'none';
                composerNote.style.display = 'block';
                if (state.closed) {
                    composerNote.textContent = 'This listing has been delisted — messaging is now closed. History remains readable.';
                    composerNote.classList.add('is-closed');
                } else {
                    composerNote.textContent = 'Replies are only available within a conversation about an active product listing.';
                    composerNote.classList.remove('is-closed');
                }
            }
            applyMessages(data.messages || [], initial);
        })
        .catch(function () {});
}
function nearBottom() {
    return (messagesWrap.scrollHeight - messagesWrap.scrollTop - messagesWrap.clientHeight) < 90;
}
function applyMessages(messages, initial) {
    if (initial) {
        messagesWrap.innerHTML = '';
        state.msgEls = {};
        state.prevDate = null;
    }
    var wasNear = nearBottom();
    var appended = 0;
    var incoming = false;
    messages.forEach(function (m) {
        var id = m.id;
        if (state.msgEls[id]) {
            // Existing node: refresh read-receipt ticks (mine) and
            // clear the darker-blue unread highlight once read (theirs).
            if (m.mine) updateTicks(id, m.is_read);
            else state.msgEls[id].classList.toggle('is-unread', !m.is_read);
            return;
        }
        if (state.prevDate !== m.date_label) {
            var div = el('div', 'chat-date-divider');
            div.innerHTML = '<span>' + esc(m.date_label || '') + '</span>';
            messagesWrap.appendChild(div);
            state.prevDate = m.date_label;
        }
        var node = renderMessage(m);
        messagesWrap.appendChild(node);
        state.msgEls[id] = node;
        if (id > state.lastId) state.lastId = id;
        appended++;
        if (!m.mine) incoming = true;
    });
    if (messages.length === 0 && initial) {
        var empty = el('div', 'chat-empty');
        empty.innerHTML = '<i class="fas fa-comment-dots"></i><h3>No messages yet</h3><p>Start the conversation below.</p>';
        messagesWrap.appendChild(empty);
    }
    if (initial || appended > 0) {
        if (initial || wasNear || document.hasFocus()) {
            messagesWrap.scrollTop = messagesWrap.scrollHeight;
        }
    }
    if (!initial && appended > 0 && incoming && document.hasFocus()) playReceive();
    if (incoming && document.hasFocus()) queueMarkRead();
}
function updateTicks(id, isRead) {
    var node = state.msgEls[id];
    if (!node) return;
    var t = node.querySelector('.chat-ticks');
    if (!t) return;
    t.textContent = isRead ? '✓✓' : '✓';
    t.classList.toggle('is-read', !!isRead);
}
// ------------------------------------------------------------
// MESSAGE RENDERING (with formal inquiry card + unread highlight)
// ------------------------------------------------------------
function renderMessage(m) {
    var row = el('div', 'chat-msg ' + (m.mine ? 'mine' : 'theirs'));
    row.setAttribute('data-msg-id', m.id);
    // UNREAD VISIBILITY: incoming + still unread => darker blue bubble.
    if (!m.mine && !m.is_read) row.classList.add('is-unread');
    var bubble = el('div', 'chat-bubble');

    // Formal inquiry card (structured meta from send-inquiry.php)
    if (m.inquiry_meta) {
        var meta = m.inquiry_meta;
        var card = el('div', 'chat-inquiry');
        var head = el('div', 'chat-inquiry-head');
        head.innerHTML = '<i class="fas fa-envelope-open-text"></i> ' +
            (m.is_viewing_request ? 'Viewing Request' : 'New Inquiry');
        card.appendChild(head);
        var grid = el('div', 'chat-inquiry-grid');
        var addRow = function (label, value) {
            if (!value) return;
            var r = el('div', 'chat-inquiry-row');
            r.innerHTML = '<span class="k">' + esc(label) + '</span><span class="v">' + esc(value) + '</span>';
            grid.appendChild(r);
        };
        addRow('From', meta.name);
        addRow('Email', meta.email);
        addRow('Phone', meta.phone);
        addRow('Listing', meta.listing_title);
        if (meta.preferred_date) addRow('Preferred', meta.preferred_date + (meta.preferred_time ? ' at ' + meta.preferred_time : ''));
        addRow('Sent', fmtGmailDate(m.created_at));
        card.appendChild(grid);
        bubble.appendChild(card);
    } else if (m.is_viewing_request) {
        // Legacy viewing badge (pre-revamp rows without meta)
        var vb = el('div', 'chat-viewing-badge');
        vb.innerHTML = '<i class="fas fa-calendar-check"></i> Viewing Request';
        bubble.appendChild(vb);
        if (m.preferred_date) {
            var vd = el('div', 'chat-viewing-details');
            vd.textContent = '📅 ' + m.preferred_date + (m.preferred_time ? ' at ' + m.preferred_time : '');
            bubble.appendChild(vd);
        }
    }
    if (m.body) {
        var text = el('p', 'text');
        text.textContent = m.body;
        bubble.appendChild(text);
    }
    if (m.message_type === 'image' && m.media_urls && m.media_urls.length) {
        var grid2 = el('div', 'chat-msg-images' + (m.media_urls.length === 1 ? ' single' : ''));
        m.media_urls.forEach(function (u) {
            var img = el('img', 'chat-msg-img');
            img.src = u;
            img.loading = 'lazy';
            img.alt = 'Attached image';
            img.addEventListener('click', function () {
                lightboxImg.src = u;
                lightbox.classList.add('is-open');
            });
            grid2.appendChild(img);
        });
        bubble.appendChild(grid2);
    }
    if (m.message_type === 'audio' && m.media_urls && m.media_urls.length) {
        bubble.appendChild(voiceNode(m.media_urls[0], m.media_duration_sec));
    }
    var meta2 = el('div', 'chat-msg-meta');
    meta2.innerHTML = '<span>' + esc(m.time_formatted || '') + '</span>' +
        (m.mine ? ' <span class="chat-ticks' + (m.is_read ? ' is-read' : '') + '">' + (m.is_read ? '✓✓' : '✓') + '</span>' : '');
    bubble.appendChild(meta2);
    row.appendChild(bubble);
    return row;
}
function voiceNode(url, seconds) {
    var wrap = el('div', 'chat-voice');
    var play = el('button', 'chat-voice-play');
    play.type = 'button';
    play.innerHTML = '<i class="fas fa-play"></i>';
    var bars = el('div', 'chat-voice-bars');
    barsFor(url, 27).forEach(function (h) {
        var s = document.createElement('span');
        s.style.height = h + 'px';
        bars.appendChild(s);
    });
    var time = el('span', 'chat-voice-time');
    time.textContent = fmtDur(seconds);
    wrap.appendChild(play); wrap.appendChild(bars); wrap.appendChild(time);
    var audio = null, playing = false;
    function stop() {
        if (audio) { audio.pause(); audio = null; }
        playing = false;
        play.innerHTML = '<i class="fas fa-play"></i>';
        var spans = bars.querySelectorAll('span');
        for (var i = 0; i < spans.length; i++) spans[i].classList.remove('played');
        time.textContent = fmtDur(seconds);
        if (state.currentAudioStop === stop) state.currentAudioStop = null;
    }
    play.addEventListener('click', function () {
        if (playing) { stop(); return; }
        stopCurrentAudio();
        audio = new Audio(url);
        playing = true;
        state.currentAudioStop = stop;
        play.innerHTML = '<i class="fas fa-pause"></i>';
        audio.addEventListener('timeupdate', function () {
            var d = audio.duration || seconds || 0;
            var ratio = d ? (audio.currentTime / d) : 0;
            var spans = bars.querySelectorAll('span');
            var n = Math.floor(ratio * spans.length);
            for (var i = 0; i < spans.length; i++) spans[i].classList.toggle('played', i < n);
            time.textContent = fmtDur(audio.currentTime);
        });
        audio.addEventListener('ended', stop);
        audio.play().catch(function () { toast('Could not play voice note.', 'error'); stop(); });
    });
    return wrap;
}

// ------------------------------------------------------------
// MARK READ
// ------------------------------------------------------------
function queueMarkRead() {
    if (state.markReadQueued || !state.current) return;
    state.markReadQueued = true;
    setTimeout(function () {
        state.markReadQueued = false;
        if (!state.current) return;
        var fd = new FormData();
        fd.append('csrf_token', CFG.csrf);
        fd.append('other_user', state.current.other);
        fd.append('listing', state.current.listing || 0);
        fetch(CFG.epMarkRead, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () {});
    }, 400);
}

// ------------------------------------------------------------
// ATTACHMENTS — upload-on-select with live progress
// ------------------------------------------------------------
function refreshSendState() {
    var busy = state.uploads.some(function (u) { return u.status === 'uploading'; });
    sendBtn.disabled = busy;
}
function renderPending() {
    pendingWrap.innerHTML = '';
    if (!state.uploads.length) { pendingWrap.classList.remove('has-items'); refreshSendState(); return; }
    pendingWrap.classList.add('has-items');
    state.uploads.forEach(function (u) {
        var item = el('div', 'chat-pending-item is-' + u.status);
        item.setAttribute('data-up-id', String(u.id));
        var img = document.createElement('img');
        img.src = u.objectUrl;
        item.appendChild(img);
        var overlay = el('div', 'chat-up-overlay');
        overlay.innerHTML = u.status === 'uploading'
            ? '<span class="chat-up-pct">' + u.progress + '%</span>'
            : (u.status === 'done'
                ? '<i class="fas fa-check chat-up-status"></i>'
                : '<i class="fas fa-exclamation chat-up-status"></i>');
        item.appendChild(overlay);
        var bar = el('div', 'chat-up-bar');
        bar.innerHTML = '<span style="width:' + u.progress + '%"></span>';
        item.appendChild(bar);
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.innerHTML = '<i class="fas fa-times"></i>';
        rm.title = 'Remove image';
        rm.addEventListener('click', function () {
            if (u.xhr && u.status === 'uploading') { try { u.xhr.abort(); } catch (e) {} }
            try { URL.revokeObjectURL(u.objectUrl); } catch (e) {}
            state.uploads = state.uploads.filter(function (x) { return x.id !== u.id; });
            renderPending();
        });
        item.appendChild(rm);
        pendingWrap.appendChild(item);
    });
    refreshSendState();
}
function updatePendingItem(u) {
    var item = pendingWrap.querySelector('[data-up-id="' + u.id + '"]');
    if (!item) { renderPending(); return; }
    item.className = 'chat-pending-item is-' + u.status;
    var pct = item.querySelector('.chat-up-pct');
    if (pct) pct.textContent = u.progress + '%';
    var bar = item.querySelector('.chat-up-bar span');
    if (bar) bar.style.width = u.progress + '%';
    if (u.status !== 'uploading') {
        var ov = item.querySelector('.chat-up-overlay');
        if (ov) ov.innerHTML = u.status === 'done'
            ? '<i class="fas fa-check chat-up-status"></i>'
            : '<i class="fas fa-exclamation chat-up-status"></i>';
    }
}
function startUpload(u) {
    var fd = new FormData();
    fd.append('csrf_token', CFG.csrf);
    fd.append('file', u.file, u.file.name);
    var xhr = new XMLHttpRequest();
    u.xhr = xhr;
    xhr.open('POST', CFG.epUpload);
    xhr.withCredentials = true;
    xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
            u.progress = Math.min(99, Math.round((e.loaded / e.total) * 100));
            updatePendingItem(u);
        }
    };
    xhr.onload = function () {
        var data = null;
        try { data = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 200 && data && data.success && data.url) {
            u.status = 'done';
            u.serverUrl = data.url;
            u.progress = 100;
        } else {
            u.status = 'error';
            toast((data && data.error) || 'Image upload failed.', 'error');
        }
        updatePendingItem(u);
        refreshSendState();
    };
    xhr.onerror = function () {
        u.status = 'error';
        updatePendingItem(u);
        refreshSendState();
    };
    xhr.send(fd);
}
attachBtn.addEventListener('click', function () { fileInput.click(); });
fileInput.addEventListener('change', function () {
    var files = Array.prototype.slice.call(fileInput.files || []);
    files.forEach(function (f) {
        if (state.uploads.length >= CFG.maxImages) {
            toast('Maximum ' + CFG.maxImages + ' images per message.', 'error');
            return;
        }
        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
            toast('Only JPG, PNG or WEBP images are allowed.', 'error');
            return;
        }
        if (f.size > CFG.maxImageBytes) {
            toast('Image "' + f.name + '" is larger than 10MB.', 'error');
            return;
        }
        state.upSeq++;
        var u = {
            id: state.upSeq,
            file: f,
            objectUrl: URL.createObjectURL(f),
            serverUrl: null,
            progress: 0,
            status: 'uploading',
            xhr: null
        };
        state.uploads.push(u);
        startUpload(u);
    });
    fileInput.value = '';
    renderPending();
});

// ------------------------------------------------------------
// VOICE RECORDING — mobile hold-to-record / PC click-to-record
// ------------------------------------------------------------
function clearPendingRec() {
    state.recPending = null;
    composer.classList.remove('has-pending-rec');
}
function startRecording(holdMode) {
    if (state.rec) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
        toast('Voice notes are not supported in this browser.', 'error');
        return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
        var mime = '';
        var candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidates[i])) { mime = candidates[i]; break; }
        }
        state.recChunks = [];
        state.recMime = mime;
        state.recCancelled = false;
        state.recSeconds = 0;
        state.recHold = !!holdMode;
        state.recStopMode = 'send';
        recTimeEl.textContent = '0:00';
        recHint.textContent = holdMode
            ? 'Recording… release to send, slide ← to cancel'
            : 'Recording… send when done, or discard (max 3:00)';
        var rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
        state.rec = rec;
        rec.ondataavailable = function (e) { if (e.data && e.data.size > 0) state.recChunks.push(e.data); };
        rec.onstop = function () {
            stream.getTracks().forEach(function (t) { t.stop(); });
            clearInterval(state.recTimer);
            composer.classList.remove('is-recording');
            micBtn.classList.remove('is-active');
            var secs = state.recSeconds;
            var mode = state.recStopMode;
            var cancelled = state.recCancelled;
            state.recSeconds = 0;
            state.rec = null;
            state.recCancelled = false;
            if (cancelled || mode === 'cancel' || !state.recChunks.length) {
                state.recChunks = [];
                return;
            }
            var blob = new Blob(state.recChunks, { type: state.recMime || 'audio/webm' });
            state.recChunks = [];
            var ext = (state.recMime.indexOf('mp4') > -1) ? 'mp4' : ((state.recMime.indexOf('ogg') > -1) ? 'ogg' : 'webm');
            if (mode === 'send') {
                doSend({ body: input.value.trim(), imageUrls: [], audio: { blob: blob, ext: ext, seconds: secs } });
            } else {
                // PC max-time overflow: keep the note, let the user decide
                state.recPending = { blob: blob, ext: ext, seconds: secs };
                pendingRecLabel.textContent = 'Voice note ready — ' + fmtDur(secs);
                composer.classList.add('has-pending-rec');
            }
        };
        rec.start();
        composer.classList.add('is-recording');
        micBtn.classList.add('is-active');
        state.recTimer = setInterval(function () {
            state.recSeconds++;
            recTimeEl.textContent = fmtDur(state.recSeconds);
            if (state.recSeconds >= CFG.maxVoiceSeconds && state.rec && state.rec.state !== 'inactive') {
                stopRecording(state.recHold ? 'send' : 'pending');
            }
        }, 1000);
    }).catch(function (err) {
        toast('Microphone unavailable' + (err && err.name ? ' (' + err.name + ')' : '') + '. Check browser permissions (HTTPS required).', 'error');
    });
}
function stopRecording(mode) {
    state.recStopMode = mode;
    if (mode === 'cancel') state.recCancelled = true;
    if (state.rec && state.rec.state !== 'inactive') state.rec.stop();
}
// Mobile: hold to record, release to send, slide left to cancel
micBtn.addEventListener('touchstart', function (e) {
    if (state.rec || !isTouch) return;
    e.preventDefault();
    state.recTouchStartX = e.touches[0].clientX;
    state.recTouchCancelled = false;
    startRecording(true);
}, { passive: false });
micBtn.addEventListener('touchmove', function (e) {
    if (!state.rec || !state.recHold) return;
    var dx = e.touches[0].clientX - (state.recTouchStartX || 0);
    if (dx < -60 && !state.recTouchCancelled) {
        state.recTouchCancelled = true;
        recHint.textContent = 'Release to CANCEL';
        composer.classList.add('is-cancel-arm');
    } else if (dx >= -60 && state.recTouchCancelled) {
        state.recTouchCancelled = false;
        recHint.textContent = 'Recording… release to send, slide ← to cancel';
        composer.classList.remove('is-cancel-arm');
    }
}, { passive: true });
micBtn.addEventListener('touchend', function (e) {
    if (!state.rec || !state.recHold) return;
    e.preventDefault();
    composer.classList.remove('is-cancel-arm');
    if (state.recTouchCancelled) stopRecording('cancel');
    else stopRecording('send');
}, { passive: false });
// PC: click to start; the recording bar's send/discard finish it
micBtn.addEventListener('click', function () {
    if (isTouch || state.rec) return;
    startRecording(false);
});
recCancel.addEventListener('click', function () { stopRecording('cancel'); });
recSend.addEventListener('click', function () { stopRecording('send'); });
pendingRecSend.addEventListener('click', function () {
    if (!state.recPending) return;
    var p = state.recPending;
    clearPendingRec();
    doSend({ body: input.value.trim(), imageUrls: [], audio: p });
});
pendingRecDiscard.addEventListener('click', clearPendingRec);

// ------------------------------------------------------------
// SEND
// ------------------------------------------------------------
function doneUploadUrls() {
    return state.uploads
        .filter(function (u) { return u.status === 'done' && u.serverUrl; })
        .map(function (u) { return u.serverUrl; });
}
function doSend(opts) {
    if (!state.current || !state.canReply) return;
    if (state.uploads.some(function (u) { return u.status === 'uploading'; })) {
        toast('Wait for image uploads to finish before sending.', 'warning');
        return;
    }
    var fd = new FormData();
    fd.append('csrf_token', CFG.csrf);
    fd.append('receiver_id', state.current.other);
    fd.append('listing_id', state.current.listing || 0);
    fd.append('listing_type', state.current.type || '');
    fd.append('body', opts.body || '');
    if (opts.imageUrls && opts.imageUrls.length) {
        fd.append('image_urls', JSON.stringify(opts.imageUrls));
    }
    if (opts.audio) {
        var fname = 'voice-note-' + Date.now() + '.' + opts.audio.ext;
        fd.append('audio', new File([opts.audio.blob], fname, { type: opts.audio.blob.type }), fname);
        fd.append('audio_duration', String(opts.audio.seconds || 0));
    }
    sendBtn.disabled = true;
    fetch(CFG.epSend, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            refreshSendState();
            if (data && data.success && data.message) {
                playSend();
                input.value = '';
                input.style.height = '42px';
                state.uploads.forEach(function (u) { try { URL.revokeObjectURL(u.objectUrl); } catch (e) {} });
                state.uploads = [];
                renderPending();
                clearPendingRec();
                applyMessages([data.message], false);
                messagesWrap.scrollTop = messagesWrap.scrollHeight;
                loadConversations();
            } else {
                toast((data && data.error) || 'Could not send message.', 'error');
            }
        })
        .catch(function () {
            refreshSendState();
            toast('Network error. Please try again.', 'error');
        });
}
composer.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = input.value.trim();
    var urls = doneUploadUrls();
    var hasError = state.uploads.some(function (u) { return u.status === 'error'; });
    if (hasError) {
        toast('Remove the failed image upload(s) before sending.', 'warning');
        return;
    }
    if (!body && !urls.length) return;
    doSend({ body: body, imageUrls: urls, audio: null });
});
// Enter = send, Shift+Enter = newline
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        composer.requestSubmit ? composer.requestSubmit() : composer.dispatchEvent(new Event('submit'));
    }
});
// Auto-resize textarea
input.addEventListener('input', function () {
    input.style.height = '42px';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
});

// ------------------------------------------------------------
// LIGHTBOX / BACK / SEARCH / POLLING
// ------------------------------------------------------------
lightboxClose.addEventListener('click', function () { lightbox.classList.remove('is-open'); });
lightbox.addEventListener('click', function (e) { if (e.target === lightbox) lightbox.classList.remove('is-open'); });
backBtn.addEventListener('click', function () {
    app.classList.remove('show-thread');
    try { window.history.replaceState(null, '', window.location.pathname); } catch (e) {}
});
searchInput.addEventListener('input', function () {
    state.search = searchInput.value.trim();
    renderList();
});
setInterval(function () { if (state.current) loadThread(false); }, CFG.pollThreadMs);
setInterval(loadConversations, CFG.pollListMs);
document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        loadConversations();
        if (state.current) loadThread(false);
    }
});
window.addEventListener('beforeunload', function () {
    state.uploads.forEach(function (u) { try { URL.revokeObjectURL(u.objectUrl); } catch (e) {} });
});

// ------------------------------------------------------------
// INIT
// ------------------------------------------------------------
loadConversations();
if (CFG.openOther > 0) {
    openConversation(CFG.openOther, CFG.openListing, CFG.openType);
}
})();
