// /assets/js/chat.js
// KINAS GROUP — Rebuilt Messenger client (User <-> Agent, listing-bound threads)
// Pairs with assets/css/chat.css and api/messages/{conversations,thread,mark-read,send}.php

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
        pollThreadMs: 5000,
        pollListMs: 20000,
        maxImages: 4,
        maxImageBytes: 10 * 1024 * 1024,
        maxVoiceSeconds: 180
    };

    var state = {
        conversations: [],
        current: null,          // { other, listing, type }
        canReply: false,
        listing: null,
        lastId: 0,
        prevDate: null,
        msgEls: {},             // id -> node
        pendingImages: [],      // File[]
        pendingUrls: [],        // object URLs (cleanup)
        search: '',
        currentAudioStop: null, // stop fn of playing voice note
        rec: null,
        recChunks: [],
        recMime: '',
        recSeconds: 0,
        recTimer: null,
        recCancelled: false,
        markReadQueued: false
    };

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
            if (m === '>') return '&gt;';
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
    // BUILD UI SKELETON
    // ------------------------------------------------------------
    root.innerHTML =
        '<div class="chat-app" id="chatApp">' +
        '  <div class="chat-list-pane">' +
        '    <div class="chat-list-head">' +
        '      <div class="chat-list-title"><i class="fas fa-envelope"></i> Messages <span class="chat-list-count" id="chatListCount"></span></div>' +
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
        '      <button type="button" class="chat-mic-btn" id="chatMicBtn" title="Record voice note"><i class="fas fa-microphone"></i></button>' +
        '      <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message…"></textarea>' +
        '      <button type="submit" class="chat-send-btn" id="chatSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>' +
        '      <div class="chat-recording">' +
        '        <span class="chat-rec-dot"></span>' +
        '        <span class="chat-rec-time" id="chatRecTime">0:00</span>' +
        '        <span class="chat-rec-hint">Recording voice note… max 3:00</span>' +
        '        <button type="button" class="chat-rec-cancel" id="chatRecCancel" title="Discard recording"><i class="fas fa-trash"></i></button>' +
        '        <button type="button" class="chat-rec-send" id="chatRecSend" title="Send voice note"><i class="fas fa-paper-plane"></i></button>' +
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
        recTimeEl = qs('chatRecTime'), recCancel = qs('chatRecCancel'), recSend = qs('chatRecSend'),
        backBtn = qs('chatBackBtn'),
        lightbox = qs('chatLightbox'), lightboxImg = qs('chatLightboxImg'), lightboxClose = qs('chatLightboxClose');

    // ------------------------------------------------------------
    // CONVERSATION LIST
    // ------------------------------------------------------------
    function loadConversations() {
        fetch(CFG.epConversations, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                state.conversations = data.conversations || [];
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
            prev.innerHTML = esc(prevText) + ((c.unread_count || 0) > 0 ? ' <span class="chat-unread-count">' + (c.unread_count) + '</span>' : '');

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
                state.listing = conv.listing || null;

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

                // Composer visibility
                if (state.canReply) {
                    composer.style.display = 'flex';
                    composerNote.style.display = 'none';
                } else {
                    composer.style.display = 'none';
                    composerNote.style.display = 'block';
                    composerNote.textContent = 'Replies are only available within a conversation about an active product listing.';
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
                // Existing: only refresh read-receipt ticks.
                if (m.mine) updateTicks(id, m.is_read);
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

    function renderMessage(m) {
        var row = el('div', 'chat-msg ' + (m.mine ? 'mine' : 'theirs'));
        row.setAttribute('data-msg-id', m.id);

        var bubble = el('div', 'chat-bubble');

        if (m.is_viewing_request) {
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
            var grid = el('div', 'chat-msg-images' + (m.media_urls.length === 1 ? ' single' : ''));
            m.media_urls.forEach(function (u) {
                var img = el('img', 'chat-msg-img');
                img.src = u;
                img.loading = 'lazy';
                img.alt = 'Attached image';
                img.addEventListener('click', function () {
                    lightboxImg.src = u;
                    lightbox.classList.add('is-open');
                });
                grid.appendChild(img);
            });
            bubble.appendChild(grid);
        }

        if (m.message_type === 'audio' && m.media_urls && m.media_urls.length) {
            bubble.appendChild(voiceNode(m.media_urls[0], m.media_duration_sec));
        }

        var meta = el('div', 'chat-msg-meta');
        meta.innerHTML = '<span>' + esc(m.time_formatted || '') + '</span>' +
            (m.mine ? ' <span class="chat-ticks' + (m.is_read ? ' is-read' : '') + '">' + (m.is_read ? '✓✓' : '✓') + '</span>' : '');
        bubble.appendChild(meta);

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
    // PENDING IMAGES
    // ------------------------------------------------------------
    function renderPending() {
        state.pendingUrls.forEach(function (u) { try { URL.revokeObjectURL(u); } catch (e) {} });
        state.pendingUrls = [];
        pendingWrap.innerHTML = '';
        if (!state.pendingImages.length) { pendingWrap.classList.remove('has-items'); return; }
        pendingWrap.classList.add('has-items');
        state.pendingImages.forEach(function (f, idx) {
            var item = el('div', 'chat-pending-item');
            var img = document.createElement('img');
            var u = URL.createObjectURL(f);
            state.pendingUrls.push(u);
            img.src = u;
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.innerHTML = '<i class="fas fa-times"></i>';
            rm.title = 'Remove image';
            rm.addEventListener('click', function () {
                state.pendingImages.splice(idx, 1);
                renderPending();
            });
            item.appendChild(img); item.appendChild(rm);
            pendingWrap.appendChild(item);
        });
    }

    attachBtn.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
        var files = Array.prototype.slice.call(fileInput.files || []);
        files.forEach(function (f) {
            if (state.pendingImages.length >= CFG.maxImages) {
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
            state.pendingImages.push(f);
        });
        fileInput.value = '';
        renderPending();
    });

    // ------------------------------------------------------------
    // VOICE RECORDING
    // ------------------------------------------------------------
    function startRecording() {
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
            recTimeEl.textContent = '0:00';

            var rec = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
            state.rec = rec;

            rec.ondataavailable = function (e) { if (e.data && e.data.size > 0) state.recChunks.push(e.data); };
            rec.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                clearInterval(state.recTimer);
                composer.classList.remove('is-recording');
                micBtn.classList.remove('is-active');
                var secs = state.recSeconds;
                state.recSeconds = 0;
                state.rec = null;
                if (state.recCancelled) { state.recCancelled = false; state.recChunks = []; return; }
                if (!state.recChunks.length) return;
                var blob = new Blob(state.recChunks, { type: state.recMime || 'audio/webm' });
                state.recChunks = [];
                var ext = (state.recMime.indexOf('mp4') > -1) ? 'mp4' : ((state.recMime.indexOf('ogg') > -1) ? 'ogg' : 'webm');
                doSend({ body: input.value.trim(), images: [], audio: { blob: blob, ext: ext, seconds: secs } });
            };

            rec.start();
            composer.classList.add('is-recording');
            micBtn.classList.add('is-active');
            state.recTimer = setInterval(function () {
                state.recSeconds++;
                recTimeEl.textContent = fmtDur(state.recSeconds);
                if (state.recSeconds >= CFG.maxVoiceSeconds && state.rec && state.rec.state !== 'inactive') {
                    state.rec.stop(); // auto-send at 3:00
                }
            }, 1000);
        }).catch(function (err) {
            toast('Microphone unavailable' + (err && err.name ? ' (' + err.name + ')' : '') + '.', 'error');
        });
    }

    micBtn.addEventListener('click', startRecording);
    recCancel.addEventListener('click', function () {
        state.recCancelled = true;
        if (state.rec && state.rec.state !== 'inactive') state.rec.stop();
    });
    recSend.addEventListener('click', function () {
        if (state.rec && state.rec.state !== 'inactive') state.rec.stop();
    });

    // ------------------------------------------------------------
    // SEND
    // ------------------------------------------------------------
    function doSend(opts) {
        if (!state.current || !state.canReply) return;

        var fd = new FormData();
        fd.append('csrf_token', CFG.csrf);
        fd.append('receiver_id', state.current.other);
        fd.append('listing_id', state.current.listing || 0);
        fd.append('listing_type', state.current.type || '');
        fd.append('body', opts.body || '');

        (opts.images || []).forEach(function (f) { fd.append('images[]', f, f.name); });

        if (opts.audio) {
            var fname = 'voice-note-' + Date.now() + '.' + opts.audio.ext;
            fd.append('audio', new File([opts.audio.blob], fname, { type: opts.audio.blob.type }), fname);
            fd.append('audio_duration', String(opts.audio.seconds || 0));
        }

        sendBtn.disabled = true;
        fetch(CFG.epSend, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                sendBtn.disabled = false;
                if (data && data.success && data.message) {
                    input.value = '';
                    input.style.height = '42px';
                    state.pendingImages = [];
                    renderPending();
                    applyMessages([data.message], false);
                    messagesWrap.scrollTop = messagesWrap.scrollHeight;
                    loadConversations();
                } else {
                    toast((data && data.error) || 'Could not send message.', 'error');
                }
            })
            .catch(function () {
                sendBtn.disabled = false;
                toast('Network error. Please try again.', 'error');
            });
    }

    composer.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body && !state.pendingImages.length) return;
        doSend({ body: body, images: state.pendingImages.slice(), audio: null });
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
        state.pendingUrls.forEach(function (u) { try { URL.revokeObjectURL(u); } catch (e) {} });
    });

    // ------------------------------------------------------------
    // INIT
    // ------------------------------------------------------------
    loadConversations();
    if (CFG.openOther > 0) {
        openConversation(CFG.openOther, CFG.openListing, CFG.openType);
    }
})();
