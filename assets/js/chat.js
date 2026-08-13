// /assets/js/chat.js — KINAS GROUP Messenger (v6 — AUTHORITATIVE)
// Any future messenger change must start from THIS file.
window.__kinasChatJsLoaded = true;
(function () {
'use strict';
var root = document.getElementById('chatRoot');
if (!root) return;

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
    pollThreadMs: 5000, pollListMs: 20000,
    maxImages: 4, maxImageBytes: 10 * 1024 * 1024, maxVoiceSeconds: 180
};
var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
var state = {
    conversations: [], current: null, canReply: false, closed: false, listing: null,
    lastId: 0, prevDate: null, msgEls: {}, uploads: [], upSeq: 0, search: '',
    currentAudioStop: null, rec: null, recChunks: [], recMime: '', recSeconds: 0,
    recTimer: null, recCancelled: false, recHold: false, recStopMode: 'send',
    recTouchStartX: 0, recTouchCancelled: false, lastTouchStart: 0,
    markReadQueued: false, prevUnreadTotal: null, muted: false
};
try { state.muted = localStorage.getItem('kinas_chat_muted') === '1'; } catch (e) {}

function qs(id) { return document.getElementById(id); }
function el(tag, cls) { var n = document.createElement(tag); if (cls) n.className = cls; return n; }
function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
        if (m === '&') return '&amp;'; if (m === '<') return '&lt;';
        if (m === '>') return '&gt;'; if (m === '"') return '&quot;'; return '&#39;';
    });
}
function money(n) { return '₦' + Number(n || 0).toLocaleString('en-NG'); }
function fmtDur(sec) { sec = Math.max(0, Math.round(sec || 0)); var m = Math.floor(sec / 60), s = sec % 60; return m + ':' + (s < 10 ? '0' : '') + s; }
function fmtGmailDate(dt) {
    var d = new Date(dt); if (!dt || isNaN(d.getTime())) return '';
    var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var h = d.getHours(); var ampm = h >= 12 ? 'PM' : 'AM'; h = h % 12; if (h === 0) h = 12;
    var min = d.getMinutes();
    return days[d.getDay()] + ', ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ', ' + h + ':' + (min < 10 ? '0' : '') + min + ' ' + ampm;
}
function toast(msg, type) {
    if (typeof window.kinasToast === 'function') { window.kinasToast(msg, type || 'info'); return; }
    if (typeof window.showSuccessBanner === 'function') { window.showSuccessBanner(msg, type === 'error'); return; }
    console.warn('[chat]', msg);
}
function note(msg, isErr) {
    if (!composerNote) return;
    composerNote.textContent = msg;
    composerNote.style.display = 'block';
    composerNote.classList.toggle('is-closed', !!isErr);
}
function clearNote() { if (composerNote) composerNote.style.display = 'none'; }
function roleBadgeClass(r) { return r === 'agent' ? 'role-agent' : r === 'admin' ? 'role-admin' : r === 'business' ? 'role-business' : 'role-user'; }
function barsFor(seed, count) {
    var h = 0, i, out = [];
    for (i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
    for (i = 0; i < count; i++) { h = (h * 1103515245 + 12345) | 0; out.push(6 + (Math.abs(h >> 16) % 19)); }
    return out;
}
function stopCurrentAudio() { if (state.currentAudioStop) { try { state.currentAudioStop(); } catch (e) {} state.currentAudioStop = null; } }

var AC = null;
function audioCtx() {
    var C = window.AudioContext || window.webkitAudioContext; if (!C) return null;
    if (!AC) AC = new C(); if (AC.state === 'suspended') { AC.resume().catch(function () {}); }
    return AC;
}
function tone(f, delay, dur, peak) {
    var ctx = audioCtx(); if (!ctx) return;
    try {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.type = 'sine'; o.frequency.value = f;
        var t0 = ctx.currentTime + delay;
        g.gain.setValueAtTime(0.0001, t0);
        g.gain.exponentialRampToValueAtTime(peak || 0.07, t0 + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        o.connect(g); g.connect(ctx.destination); o.start(t0); o.stop(t0 + dur + 0.05);
    } catch (e) {}
}
function playSend() { if (!state.muted) { tone(880, 0, 0.12, 0.06); tone(1320, 0.09, 0.14, 0.05); } }
function playReceive() { if (!state.muted) { tone(660, 0, 0.12, 0.07); tone(520, 0.10, 0.16, 0.06); } }

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
'      <div class="chat-thread-info"><div class="chat-thread-name" id="chatThreadName"></div><div class="chat-thread-sub" id="chatThreadSub"></div></div>' +
'      <a class="chat-listing-ctx" id="chatListingCtx" href="#" target="_blank" rel="noopener" style="display:none">' +
'        <img id="chatListingThumb" alt="">' +
'        <div class="chat-listing-ctx-text"><div class="chat-listing-ctx-title" id="chatListingTitle"></div><div class="chat-listing-ctx-price" id="chatListingPrice"></div></div>' +
'      </a>' +
'    </div>' +
'    <div class="chat-messages" id="chatMessages"><div class="chat-empty" id="chatEmpty"><i class="fas fa-comments"></i><h3>Select a conversation</h3><p>Choose a conversation from the list to view messages.</p></div></div>' +
'    <div class="chat-pending-media" id="chatPendingMedia"></div>' +
'    <div class="chat-composer-note" id="chatComposerNote" style="display:none"></div>' +
'    <form class="chat-composer" id="chatComposer" style="display:none">' +
'      <button type="button" class="chat-attach-btn" id="chatAttachBtn" title="Attach images (max 4, 10MB each)"><i class="far fa-image"></i></button>' +
'      <input type="file" id="chatFileInput" accept="image/jpeg,image/png,image/webp" multiple hidden>' +
'      <button type="button" class="chat-mic-btn" id="chatMicBtn" title="Record a voice note"><i class="fas fa-microphone"></i></button>' +
'      <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message…"></textarea>' +
'      <button type="submit" class="chat-send-btn" id="chatSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>' +
'      <div class="chat-recording"><span class="chat-rec-dot"></span><span class="chat-rec-time" id="chatRecTime">0:00</span><span class="chat-rec-hint" id="chatRecHint">Recording…</span>' +
'        <button type="button" class="chat-rec-cancel" id="chatRecCancel" title="Discard recording"><i class="fas fa-trash"></i></button>' +
'        <button type="button" class="chat-rec-send" id="chatRecSend" title="Stop and send voice note"><i class="fas fa-paper-plane"></i></button></div>' +
'    </form>' +
'  </div>' +
'</div>' +
'<div class="chat-lightbox" id="chatLightbox"><img id="chatLightboxImg" src="" alt=""><button type="button" class="chat-lightbox-close" id="chatLightboxClose" title="Close">✕</button></div>';

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
backBtn = qs('chatBackBtn'), soundBtn = qs('chatSoundBtn'),
lightbox = qs('chatLightbox'), lightboxImg = qs('chatLightboxImg'), lightboxClose = qs('chatLightboxClose');

window.__kinasChatBoot = true;

function updateSoundBtn() {
    soundBtn.innerHTML = state.muted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-up"></i>';
    soundBtn.title = state.muted ? 'Message sounds off — click to enable' : 'Message sounds on — click to mute';
    soundBtn.classList.toggle('is-muted', state.muted);
}
soundBtn.addEventListener('click', function () {
    state.muted = !state.muted;
    try { localStorage.setItem('kinas_chat_muted', state.muted ? '1' : '0'); } catch (e) {}
    updateSoundBtn(); if (!state.muted) playSend();
});
updateSoundBtn();

function loadConversations() {
    fetch(CFG.epConversations, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.success) return;
        state.conversations = data.conversations || [];
        var t = 0; state.conversations.forEach(function (c) { t += (c.unread_count || 0); });
        if (state.prevUnreadTotal !== null && t > state.prevUnreadTotal && !state.current) playReceive();
        state.prevUnreadTotal = t;
        renderList();
    }).catch(function () {});
}
function renderList() {
    var unread = 0; state.conversations.forEach(function (c) { unread += (c.unread_count || 0); });
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
        item.setAttribute('role', 'button'); item.setAttribute('tabindex', '0');
        var av = el('div', 'chat-avatar' + ((c.unread_count || 0) > 0 ? ' is-unread' : ''));
        av.textContent = (c.other_name || '?').charAt(0).toUpperCase();
        var body = el('div', 'chat-conv-body');
        var top = el('div', 'chat-conv-top');
        var name = el('div', 'chat-conv-name');
        name.innerHTML = esc(c.other_name || 'Unknown') + ' <span class="chat-role-badge ' + roleBadgeClass(c.other_role) + '">' + esc(c.other_role || 'user') + '</span>';
        var time = el('div', 'chat-time'); time.textContent = c.last_time_formatted || '';
        top.appendChild(name); top.appendChild(time);
        var prev = el('div', 'chat-conv-preview');
        prev.innerHTML = esc((c.last_sender_is_me ? 'You: ' : '') + (c.last_preview || 'No messages yet')) +
            ((c.unread_count || 0) > 0 ? ' <span class="chat-unread-count">' + c.unread_count + '</span>' : '');
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
function openConversation(otherId, listingId, listingType) {
    state.current = { other: otherId, listing: listingId, type: listingType || '' };
    state.lastId = 0; state.prevDate = null; state.msgEls = {};
    messagesWrap.innerHTML = '';
    threadHead.style.display = 'flex';
    app.classList.add('show-thread');
    try { window.history.replaceState(null, '', window.location.pathname + '?user=' + otherId + '&listing=' + (listingId || 0)); } catch (e) {}
    loadThread(true); loadConversations();
}
function loadThread(initial) {
    if (!state.current) return;
    fetch(CFG.epThread + '?other_user=' + state.current.other + '&listing=' + (state.current.listing || 0) + '&since_id=0', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.success || !state.current) return;
        var conv = data.conversation || {};
        state.canReply = !!conv.can_reply; state.closed = !!conv.closed; state.listing = conv.listing || null;
        var otherName = conv.other_name || 'Unknown';
        threadAvatar.textContent = otherName.charAt(0).toUpperCase();
        threadName.innerHTML = esc(otherName) + ' <span class="chat-role-badge ' + roleBadgeClass(conv.other_role) + '">' + esc(conv.other_role || 'user') + '</span>';
        threadSub.textContent = conv.other_role === 'agent' ? 'Agent' : conv.other_role === 'admin' ? 'Admin' : 'Customer';
        if (state.listing && state.listing.listing_id) {
            listingCtx.style.display = 'flex'; listingCtx.href = state.listing.listing_url || '#';
            listingTitle.textContent = state.listing.listing_title || 'Listing #' + state.listing.listing_id;
            listingPrice.textContent = state.listing.listing_price ? money(state.listing.listing_price) : '';
            if (state.listing.listing_thumb) { listingThumb.src = state.listing.listing_thumb; listingThumb.style.display = 'block'; } else { listingThumb.style.display = 'none'; }
        } else { listingCtx.style.display = 'none'; }
        if (state.canReply) { composer.style.display = 'flex'; if (!composerNote.textContent) composerNote.style.display = 'none'; }
        else {
            composer.style.display = 'none'; composerNote.style.display = 'block';
            if (state.closed) { composerNote.textContent = 'This listing has been delisted — messaging is now closed. History remains readable.'; composerNote.classList.add('is-closed'); }
            else { composerNote.textContent = 'Replies are only available within a conversation about an active product listing.'; composerNote.classList.remove('is-closed'); }
        }
        applyMessages(data.messages || [], initial);
    }).catch(function () {});
}
function nearBottom() { return (messagesWrap.scrollHeight - messagesWrap.scrollTop - messagesWrap.clientHeight) < 90; }
function applyMessages(messages, initial) {
    if (initial) { messagesWrap.innerHTML = ''; state.msgEls = {}; state.prevDate = null; }
    var wasNear = nearBottom(), appended = 0, incoming = false;
    messages.forEach(function (m) {
        var id = m.id;
        if (state.msgEls[id]) {
            if (m.mine) updateTicks(id, m.is_read);
            else state.msgEls[id].classList.toggle('is-unread', !m.is_read);
            return;
        }
        if (state.prevDate !== m.date_label) {
            var div = el('div', 'chat-date-divider'); div.innerHTML = '<span>' + esc(m.date_label || '') + '</span>';
            messagesWrap.appendChild(div); state.prevDate = m.date_label;
        }
        var node = renderMessage(m);
        messagesWrap.appendChild(node); state.msgEls[id] = node;
        if (id > state.lastId) state.lastId = id;
        appended++; if (!m.mine) incoming = true;
    });
    if (messages.length === 0 && initial) {
        var empty = el('div', 'chat-empty');
        empty.innerHTML = '<i class="fas fa-comment-dots"></i><h3>No messages yet</h3><p>Start the conversation below.</p>';
        messagesWrap.appendChild(empty);
    }
    if (initial || appended > 0) { if (initial || wasNear || document.hasFocus()) messagesWrap.scrollTop = messagesWrap.scrollHeight; }
    if (!initial && appended > 0 && incoming && document.hasFocus()) playReceive();
    if (incoming && document.hasFocus()) queueMarkRead();
}
function updateTicks(id, isRead) {
    var node = state.msgEls[id]; if (!node) return;
    var t = node.querySelector('.chat-ticks'); if (!t) return;
    t.textContent = isRead ? '✓✓' : '✓'; t.classList.toggle('is-read', !!isRead);
}
function renderMessage(m) {
    var row = el('div', 'chat-msg ' + (m.mine ? 'mine' : 'theirs'));
    row.setAttribute('data-msg-id', m.id);
    if (!m.mine && !m.is_read) row.classList.add('is-unread');
    var bubble = el('div', 'chat-bubble');
    if (m.inquiry_meta) {
        var meta = m.inquiry_meta;
        var card = el('div', 'chat-inquiry');
        var head = el('div', 'chat-inquiry-head');
        head.innerHTML = '<i class="fas fa-envelope-open-text"></i> ' + (m.is_viewing_request ? 'Viewing Request' : 'New Inquiry');
        card.appendChild(head);
        var grid = el('div', 'chat-inquiry-grid');
        var addRow = function (label, value) {
            if (!value) return;
            var r = el('div', 'chat-inquiry-row');
            r.innerHTML = '<span class="k">' + esc(label) + '</span><span class="v">' + esc(value) + '</span>';
            grid.appendChild(r);
        };
        addRow('From', meta.name); addRow('Email', meta.email); addRow('Phone', meta.phone);
        addRow('Listing', meta.listing_title);
        if (meta.preferred_date) addRow('Preferred', meta.preferred_date + (meta.preferred_time ? ' at ' + meta.preferred_time : ''));
        addRow('Sent', fmtGmailDate(m.created_at));
        card.appendChild(grid); bubble.appendChild(card);
    } else if (m.is_viewing_request) {
        var vb = el('div', 'chat-viewing-badge'); vb.innerHTML = '<i class="fas fa-calendar-check"></i> Viewing Request'; bubble.appendChild(vb);
        if (m.preferred_date) { var vd = el('div', 'chat-viewing-details'); vd.textContent = '📅 ' + m.preferred_date + (m.preferred_time ? ' at ' + m.preferred_time : ''); bubble.appendChild(vd); }
    }
    if (m.body) { var text = el('p', 'text'); text.textContent = m.body; bubble.appendChild(text); }
    if (m.message_type === 'image' && m.media_urls && m.media_urls.length) {
        var g2 = el('div', 'chat-msg-images' + (m.media_urls.length === 1 ? ' single' : ''));
        m.media_urls.forEach(function (u) {
            var img = el('img', 'chat-msg-img'); img.src = u; img.loading = 'lazy'; img.alt = 'Attached image';
            img.addEventListener('click', function () { lightboxImg.src = u; lightbox.classList.add('is-open'); });
            g2.appendChild(img);
        });
        bubble.appendChild(g2);
    }
    if (m.message_type === 'audio' && m.media_urls && m.media_urls.length) bubble.appendChild(voiceNode(m.media_urls[0], m.media_duration_sec));
    var meta2 = el('div', 'chat-msg-meta');
    meta2.innerHTML = '<span>' + esc(m.time_formatted || '') + '</span>' + (m.mine ? ' <span class="chat-ticks' + (m.is_read ? ' is-read' : '') + '">' + (m.is_read ? '✓✓' : '✓') + '</span>' : '');
    bubble.appendChild(meta2); row.appendChild(bubble);
    return row;
}
function voiceNode(url, seconds) {
    var wrap = el('div', 'chat-voice');
    var play = el('button', 'chat-voice-play'); play.type = 'button'; play.innerHTML = '<i class="fas fa-play"></i>';
    var bars = el('div', 'chat-voice-bars');
    barsFor(url, 27).forEach(function (h) { var s = document.createElement('span'); s.style.height = h + 'px'; bars.appendChild(s); });
    var time = el('span', 'chat-voice-time'); time.textContent = fmtDur(seconds);
    wrap.appendChild(play); wrap.appendChild(bars); wrap.appendChild(time);
    var audio = null, playing = false;
    function stop() {
        if (audio) { audio.pause(); audio = null; }
        playing = false; play.innerHTML = '<i class="fas fa-play"></i>';
        var spans = bars.querySelectorAll('span');
        for (var i = 0; i < spans.length; i++) spans[i].classList.remove('played');
        time.textContent = fmtDur(seconds);
        if (state.currentAudioStop === stop) state.currentAudioStop = null;
    }
    play.addEventListener('click', function () {
        if (playing) { stop(); return; }
        stopCurrentAudio(); audio = new Audio(url); playing = true; state.currentAudioStop = stop;
        play.innerHTML = '<i class="fas fa-pause"></i>';
        audio.addEventListener('timeupdate', function () {
            var d = audio.duration || seconds || 0; var ratio = d ? (audio.currentTime / d) : 0;
            var spans = bars.querySelectorAll('span'); var n = Math.floor(ratio * spans.length);
            for (var i = 0; i < spans.length; i++) spans[i].classList.toggle('played', i < n);
            time.textContent = fmtDur(audio.currentTime);
        });
        audio.addEventListener('ended', stop);
        audio.play().catch(function () { toast('Could not play voice note.', 'error'); stop(); });
    });
    return wrap;
}
function queueMarkRead() {
    if (state.markReadQueued || !state.current) return;
    state.markReadQueued = true;
    setTimeout(function () {
        state.markReadQueued = false; if (!state.current) return;
        var fd = new FormData();
        fd.append('csrf_token', CFG.csrf); fd.append('other_user', state.current.other); fd.append('listing', state.current.listing || 0);
        fetch(CFG.epMarkRead, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () {});
    }, 400);
}
// ── v6: pending uploads — thumbnail ALWAYS shows the real image ──
function overlayStyle(status) {
    if (status === 'uploading') return 'rgba(10,10,10,0.25)';
    if (status === 'done') return 'rgba(27,94,32,0.25)';
    return 'rgba(183,28,28,0.45)';
}
function refreshSendState() { sendBtn.disabled = state.uploads.some(function (u) { return u.status === 'uploading'; }); }
function ringMarkup(pct) {
    return '<svg class="chat-up-ring" viewBox="0 0 36 36"><circle class="ring-bg" cx="18" cy="18" r="15.5"/><circle class="ring-fg" cx="18" cy="18" r="15.5" pathLength="100" style="stroke-dashoffset:' + (100 - pct) + '"/></svg><span class="chat-up-pct">' + pct + '%</span>';
}
function renderPending() {
    pendingWrap.innerHTML = '';
    if (!state.uploads.length) { pendingWrap.classList.remove('has-items'); refreshSendState(); return; }
    pendingWrap.classList.add('has-items');
    state.uploads.forEach(function (u) {
        var item = el('div', 'chat-pending-item is-' + u.status);
        item.setAttribute('data-up-id', String(u.id));
        var img = document.createElement('img');
        img.alt = 'Attached image preview';
        // v6: once uploaded, show the ACTUAL uploaded file; before that, the local preview.
        img.src = (u.status === 'done' && u.serverUrl) ? u.serverUrl : u.objectUrl;
        img.onerror = (function (theImg, entry) {
            return function () {
                if (theImg.dataset.kinasRetried === '1') { theImg.style.display = 'none'; return; }
                theImg.dataset.kinasRetried = '1';
                theImg.src = URL.createObjectURL(entry.file);
            };
        })(img, u);
        item.appendChild(img);
        var overlay = el('div', 'chat-up-overlay');
        overlay.style.background = overlayStyle(u.status);
        overlay.innerHTML = u.status === 'uploading' ? ringMarkup(u.progress)
            : (u.status === 'done' ? '<i class="fas fa-check chat-up-status"></i>' : '<i class="fas fa-exclamation chat-up-status"></i>');
        item.appendChild(overlay);
        var rm = document.createElement('button'); rm.type = 'button'; rm.innerHTML = '<i class="fas fa-times"></i>'; rm.title = 'Remove image';
        rm.addEventListener('click', function () {
            if (u.xhr && u.status === 'uploading') { try { u.xhr.abort(); } catch (e) {} }
            try { URL.revokeObjectURL(u.objectUrl); } catch (e) {}
            state.uploads = state.uploads.filter(function (x) { return x.id !== u.id; });
            renderPending();
        });
        item.appendChild(rm); pendingWrap.appendChild(item);
    });
    refreshSendState();
}
function updatePendingItem(u) {
    var item = pendingWrap.querySelector('[data-up-id="' + u.id + '"]');
    if (!item) { renderPending(); return; }
    item.className = 'chat-pending-item is-' + u.status;
    var ov = item.querySelector('.chat-up-overlay');
    if (ov) {
        ov.style.background = overlayStyle(u.status);
        if (u.status === 'uploading') {
            var fg = ov.querySelector('.ring-fg'); if (fg) fg.style.strokeDashoffset = String(100 - u.progress);
            var pct = ov.querySelector('.chat-up-pct'); if (pct) pct.textContent = u.progress + '%';
        } else {
            ov.innerHTML = u.status === 'done' ? '<i class="fas fa-check chat-up-status"></i>' : '<i class="fas fa-exclamation chat-up-status"></i>';
        }
    }
    // v6: swap the thumbnail to the real uploaded image on completion.
    if (u.status === 'done' && u.serverUrl) {
        var img = item.querySelector('img');
        if (img && img.src !== u.serverUrl) img.src = u.serverUrl;
    }
}
function startUpload(u) {
    var fd = new FormData(); fd.append('csrf_token', CFG.csrf); fd.append('file', u.file, u.file.name);
    var xhr = new XMLHttpRequest(); u.xhr = xhr;
    xhr.open('POST', CFG.epUpload); xhr.withCredentials = true;
    xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) { u.progress = Math.min(99, Math.round((e.loaded / e.total) * 100)); updatePendingItem(u); }
    };
    xhr.onload = function () {
        var data = null; try { data = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 200 && data && data.success && data.url) { u.status = 'done'; u.serverUrl = data.url; u.progress = 100; }
        else { u.status = 'error'; note('Image upload failed: ' + ((data && data.error) || 'server error ' + xhr.status), true); }
        updatePendingItem(u); refreshSendState();
    };
    xhr.onerror = function () { u.status = 'error'; updatePendingItem(u); refreshSendState(); note('Image upload failed (network).', true); };
    xhr.send(fd);
}
attachBtn.addEventListener('click', function () { fileInput.click(); });
fileInput.addEventListener('change', function () {
    Array.prototype.slice.call(fileInput.files || []).forEach(function (f) {
        if (state.uploads.length >= CFG.maxImages) { toast('Maximum ' + CFG.maxImages + ' images per message.', 'error'); return; }
        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) { toast('Only JPG, PNG or WEBP images are allowed.', 'error'); return; }
        if (f.size > CFG.maxImageBytes) { toast('Image "' + f.name + '" is larger than 10MB.', 'error'); return; }
        state.upSeq++;
        var u = { id: state.upSeq, file: f, objectUrl: URL.createObjectURL(f), serverUrl: null, progress: 0, status: 'uploading', xhr: null };
        state.uploads.push(u); startUpload(u);
    });
    fileInput.value = ''; renderPending();
});
// ── Voice recording ──
function failRec(msg) {
    composer.classList.remove('is-recording'); micBtn.classList.remove('is-active');
    clearInterval(state.recTimer);
    if (state.rec) { try { state.rec.stop(); } catch (e) {} state.rec = null; }
    note(msg, true);
}
function micBlockedDiagnostics(fallback) {
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'microphone' }).then(function (p) {
            if (p.state === 'denied') {
                failRec('Microphone is BLOCKED by your browser settings. Fix: tap the padlock icon next to the website address → Site settings / Permissions → Microphone → set to Allow → reload this page → try again.');
            } else {
                failRec('Microphone is BLOCKED at the server level (Permissions-Policy header). The browser never even shows a prompt. The site\'s nginx/CDN security headers must allow "microphone" — send this screenshot to support.');
            }
        }).catch(function () { failRec(fallback); });
    } else {
        failRec(fallback);
    }
}
function startRecording(holdMode) {
    if (state.rec) return;
    clearNote();
    composer.classList.add('is-recording'); micBtn.classList.add('is-active');
    recTimeEl.textContent = '0:00';
    recHint.textContent = 'Requesting microphone…';
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
        failRec('Voice notes require a modern browser over HTTPS (microphone API missing).');
        return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
        var mime = '';
        var cands = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        for (var i = 0; i < cands.length; i++) { if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(cands[i])) { mime = cands[i]; break; } }
        state.recChunks = []; state.recMime = mime; state.recCancelled = false; state.recSeconds = 0;
        state.recHold = !!holdMode; state.recStopMode = 'send';
        recTimeEl.textContent = '0:00';
        recHint.textContent = holdMode ? 'Recording… release to send, slide ← to cancel' : 'Recording… click send when done, or trash to discard';
        var rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
        state.rec = rec;
        rec.ondataavailable = function (e) { if (e.data && e.data.size > 0) state.recChunks.push(e.data); };
        rec.onstop = function () {
            stream.getTracks().forEach(function (t) { t.stop(); });
            clearInterval(state.recTimer);
            composer.classList.remove('is-recording'); micBtn.classList.remove('is-active');
            var secs = state.recSeconds, mode = state.recStopMode, cancelled = state.recCancelled;
            state.recSeconds = 0; state.rec = null; state.recCancelled = false;
            if (cancelled || mode === 'cancel' || !state.recChunks.length) { state.recChunks = []; return; }
            var blob = new Blob(state.recChunks, { type: state.recMime || 'audio/webm' });
            state.recChunks = [];
            var ext = (state.recMime.indexOf('mp4') > -1) ? 'mp4' : ((state.recMime.indexOf('ogg') > -1) ? 'ogg' : 'webm');
            doSend({ body: input.value.trim(), imageUrls: [], audio: { blob: blob, ext: ext, seconds: secs } });
        };
        rec.start();
        state.recTimer = setInterval(function () {
            state.recSeconds++; recTimeEl.textContent = fmtDur(state.recSeconds);
            if (state.recSeconds >= CFG.maxVoiceSeconds && state.rec && state.rec.state !== 'inactive') stopRecording('send');
        }, 1000);
    }).catch(function (err) {
        var name = (err && err.name) || '';
        if (name === 'NotAllowedError' || name === 'SecurityError') {
            micBlockedDiagnostics('Microphone unavailable. Allow microphone permission in your browser\'s site settings, reload, and try again.');
        } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
            failRec('No microphone was found on this device.');
        } else if (name === 'NotReadableError') {
            failRec('Your microphone is busy — another app may be using it. Close it and try again.');
        } else {
            failRec('Microphone unavailable' + (name ? ' (' + name + ')' : '') + '. Check browser permissions (HTTPS required).');
        }
    });
}
function stopRecording(mode) {
    state.recStopMode = mode;
    if (mode === 'cancel') state.recCancelled = true;
    if (state.rec && state.rec.state !== 'inactive') state.rec.stop();
}
micBtn.addEventListener('touchstart', function (e) {
    if (state.rec) return;
    e.preventDefault();
    state.lastTouchStart = Date.now();
    state.recTouchStartX = e.touches[0].clientX; state.recTouchCancelled = false;
    startRecording(true);
}, { passive: false });
micBtn.addEventListener('touchmove', function (e) {
    if (!state.rec || !state.recHold) return;
    var dx = e.touches[0].clientX - (state.recTouchStartX || 0);
    if (dx < -60 && !state.recTouchCancelled) { state.recTouchCancelled = true; recHint.textContent = 'Release to CANCEL'; composer.classList.add('is-cancel-arm'); }
    else if (dx >= -60 && state.recTouchCancelled) { state.recTouchCancelled = false; recHint.textContent = 'Recording… release to send, slide ← to cancel'; composer.classList.remove('is-cancel-arm'); }
}, { passive: true });
micBtn.addEventListener('touchend', function (e) {
    if (!state.rec || !state.recHold) return;
    e.preventDefault(); composer.classList.remove('is-cancel-arm');
    if (state.recTouchCancelled) stopRecording('cancel'); else stopRecording('send');
}, { passive: false });
micBtn.addEventListener('click', function () {
    if (Date.now() - state.lastTouchStart < 700) return;
    if (state.rec) return;
    startRecording(false);
});
recCancel.addEventListener('click', function () { stopRecording('cancel'); });
recSend.addEventListener('click', function () { stopRecording('send'); });
// ── Send ─
function doneUploadUrls() {
    return state.uploads.filter(function (u) { return u.status === 'done' && u.serverUrl; }).map(function (u) { return u.serverUrl; });
}
function doSend(opts) {
    if (!state.current || !state.canReply) return;
    if (state.uploads.some(function (u) { return u.status === 'uploading'; })) { note('Wait for image uploads to finish before sending.', true); return; }
    var fd = new FormData();
    fd.append('csrf_token', CFG.csrf);
    fd.append('receiver_id', state.current.other);
    fd.append('listing_id', state.current.listing || 0);
    fd.append('listing_type', state.current.type || '');
    fd.append('body', opts.body || '');
    if (opts.imageUrls && opts.imageUrls.length) fd.append('image_urls', JSON.stringify(opts.imageUrls));
    if (opts.audio) {
        var fname = 'voice-note-' + Date.now() + '.' + opts.audio.ext;
        fd.append('audio', new File([opts.audio.blob], fname, { type: opts.audio.blob.type }), fname);
        fd.append('audio_duration', String(opts.audio.seconds || 0));
    }
    sendBtn.disabled = true;
    fetch(CFG.epSend, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); })
        .then(function (res) {
            refreshSendState();
            var data = res.data || {};
            if (res.ok && data.success && data.message) {
                clearNote(); playSend();
                input.value = ''; input.style.height = '42px';
                state.uploads.forEach(function (u) { try { URL.revokeObjectURL(u.objectUrl); } catch (e) {} });
                state.uploads = []; renderPending();
                applyMessages([data.message], false);
                messagesWrap.scrollTop = messagesWrap.scrollHeight;
                loadConversations();
            } else {
                note('Send failed: ' + (data.error || ('server error ' + res.status)), true);
                toast(data.error || 'Could not send message.', 'error');
            }
        })
        .catch(function () {
            refreshSendState();
            note('Send failed: network error. Check your connection and try again.', true);
        });
}
composer.addEventListener('submit', function (e) {
    e.preventDefault();
    var body = input.value.trim();
    var urls = doneUploadUrls();
    if (state.uploads.some(function (u) { return u.status === 'error'; })) { note('Remove the failed image upload(s) before sending.', true); return; }
    if (!body && !urls.length) { note('Type a message or attach an image first.', true); return; }
    doSend({ body: body, imageUrls: urls, audio: null });
});
input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); composer.requestSubmit ? composer.requestSubmit() : composer.dispatchEvent(new Event('submit')); }
});
input.addEventListener('input', function () { input.style.height = '42px'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });
lightboxClose.addEventListener('click', function () { lightbox.classList.remove('is-open'); });
lightbox.addEventListener('click', function (e) { if (e.target === lightbox) lightbox.classList.remove('is-open'); });
backBtn.addEventListener('click', function () { app.classList.remove('show-thread'); try { window.history.replaceState(null, '', window.location.pathname); } catch (e) {} });
searchInput.addEventListener('input', function () { state.search = searchInput.value.trim(); renderList(); });
setInterval(function () { if (state.current) loadThread(false); }, CFG.pollThreadMs);
setInterval(loadConversations, CFG.pollListMs);
document.addEventListener('visibilitychange', function () { if (!document.hidden) { loadConversations(); if (state.current) loadThread(false); } });
window.addEventListener('beforeunload', function () { state.uploads.forEach(function (u) { try { URL.revokeObjectURL(u.objectUrl); } catch (e) {} }); });

loadConversations();
if (CFG.openOther > 0) openConversation(CFG.openOther, CFG.openListing, CFG.openType);
})();
