// ============================================================
// KINAS BUILD: 2026.08.15.05
// FILE: assets/js/chat.js
//
// Rebuilt messenger client.
//
// Includes:
// - No microphone / voice recording button.
// - + attachment menu: Photo, Video, Audio, Document.
// - Document restriction: doc, docx, pdf, ppt, pptx only.
// - Circular upload progress counter.
// - Formal inquiry header restored from inquiry_meta.
// - Download ability for uploaded items.
// - Send/receive sounds.
// - Listing-bound messenger behaviour.
//
// Cache-busting note:
// This file contains a build stamp for identification only.
// The actual cache buster must be applied in user/messages.php
// and agent/messages.php using ?v=... on the script URL.
// ============================================================

(function () {
    'use strict';

    window.__kinasChatBuild = '2026.08.15.05';
    window.__kinasChatBoot = false;

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

        pollThreadMs: 5000,
        pollListMs: 20000,

        maxImages: 4,
        maxImageBytes: 10 * 1024 * 1024,
        maxVideoBytes: 25 * 1024 * 1024,
        maxAudioBytes: 10 * 1024 * 1024,
        maxDocBytes: 20 * 1024 * 1024
    };

    var state = {
        conversations: [],
        current: null,
        canReply: false,
        listing: null,
        lastId: 0,
        prevDate: null,
        msgEls: {},

        pendingImages: [],
        pendingFile: null,

        search: '',
        currentAudioStop: null,
        markReadQueued: false,
        sending: false,

        soundEnabled: true,
        lastSoundAt: 0
    };

    function qs(id) {
        return document.getElementById(id);
    }

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

    function money(n) {
        return '₦' + Number(n || 0).toLocaleString('en-NG');
    }

    function formatBytes(bytes) {
        bytes = Number(bytes || 0);
        if (!bytes) return '';

        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;

        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }

        return bytes.toFixed(1) + ' ' + units[i];
    }

    function fileNameFromUrl(url, fallback) {
        try {
            var parts = String(url || '').split('/');
            var last = parts[parts.length - 1] || '';
            last = last.split('?')[0];

            if (last) {
                return decodeURIComponent(last);
            }
        } catch (e) {}

        return fallback || 'download';
    }

    function formatTimestamp(s) {
        if (!s) return '';

        var d = new Date(String(s).replace(' ', 'T'));

        if (isNaN(d.getTime())) {
            d = new Date(s);
        }

        if (isNaN(d.getTime())) {
            return String(s);
        }

        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }) + ', ' + d.toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function toast(msg, type) {
        if (typeof window.kinasToast === 'function') {
            window.kinasToast(msg, type || 'info');
            return;
        }

        if (typeof window.showSuccessBanner === 'function') {
            window.showSuccessBanner(msg, type === 'error');
            return;
        }

        console.warn('[chat]', msg);
    }

    function roleBadgeClass(role) {
        if (role === 'agent') return 'role-agent';
        if (role === 'admin') return 'role-admin';
        if (role === 'business') return 'role-business';
        return 'role-user';
    }

    function fileIconForKind(kind) {
        if (kind === 'video') return '<i class="fas fa-video"></i>';
        if (kind === 'audio') return '<i class="fas fa-music"></i>';
        if (kind === 'document') return '<i class="fas fa-file-alt"></i>';
        return '<i class="fas fa-image"></i>';
    }

    function stopCurrentAudio() {
        if (state.currentAudioStop) {
            try {
                state.currentAudioStop();
            } catch (e) {}
            state.currentAudioStop = null;
        }
    }

    // ------------------------------------------------------------
    // SOUND ENGINE
    // ------------------------------------------------------------

    var audioCtx = null;

    function ensureAudioContext() {
        if (!audioCtx) {
            try {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (Ctx) audioCtx = new Ctx();
            } catch (e) {
                audioCtx = null;
            }
        }

        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(function () {});
        }

        return audioCtx;
    }

    document.addEventListener('click', ensureAudioContext);
    document.addEventListener('keydown', ensureAudioContext);

    function playTone(steps, totalDuration) {
        if (!state.soundEnabled) return;

        var ctx = ensureAudioContext();
        if (!ctx) return;

        var now = Date.now();
        if (now - state.lastSoundAt < 250) return;

        state.lastSoundAt = now;

        try {
            var startAt = ctx.currentTime;

            steps.forEach(function (step) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.type = 'sine';
                osc.frequency.value = step.f;

                var t = startAt + (step.t || 0);

                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.08, t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + Math.max(0.06, totalDuration - (step.t || 0)));

                osc.start(t);
                osc.stop(t + totalDuration + 0.02);
            });
        } catch (e) {}
    }

    function playSendSound() {
        playTone([
            { f: 880, t: 0 },
            { f: 1174.66, t: 0.09 }
        ], 0.18);
    }

    function playReceiveSound() {
        playTone([
            { f: 659.25, t: 0 },
            { f: 880, t: 0.12 }
        ], 0.26);
    }

    // ------------------------------------------------------------
    // UI SHELL
    // ------------------------------------------------------------

    root.innerHTML =
        '<div class="chat-app" id="chatApp">' +
            '<div class="chat-list-pane">' +
                '<div class="chat-list-head">' +
                    '<div class="chat-list-title"><i class="fas fa-envelope"></i> Messages <span class="chat-list-count" id="chatListCount"></span></div>' +
                '</div>' +
                '<div class="chat-search"><input type="text" id="chatSearch" placeholder="Search conversations…" autocomplete="off"></div>' +
                '<div class="chat-list" id="chatList"></div>' +
            '</div>' +

            '<div class="chat-thread-pane">' +
                '<div class="chat-thread-head" id="chatThreadHead" style="display:none">' +
                    '<button type="button" class="chat-back-btn" id="chatBackBtn" title="Back to list"><i class="fas fa-arrow-left"></i></button>' +
                    '<div class="chat-thread-avatar" id="chatThreadAvatar">?</div>' +
                    '<div class="chat-thread-info">' +
                        '<div class="chat-thread-name" id="chatThreadName"></div>' +
                        '<div class="chat-thread-sub" id="chatThreadSub"></div>' +
                    '</div>' +
                    '<a class="chat-listing-ctx" id="chatListingCtx" href="#" target="_blank" rel="noopener" style="display:none">' +
                        '<img id="chatListingThumb" alt="">' +
                        '<div class="chat-listing-ctx-text">' +
                            '<div class="chat-listing-ctx-title" id="chatListingTitle"></div>' +
                            '<div class="chat-listing-ctx-price" id="chatListingPrice"></div>' +
                        '</div>' +
                    '</a>' +
                '</div>' +

                '<div class="chat-messages" id="chatMessages">' +
                    '<div class="chat-empty" id="chatEmpty">' +
                        '<i class="fas fa-comments"></i>' +
                        '<h3>Select a conversation</h3>' +
                        '<p>Choose a conversation from the list to view messages.</p>' +
                    '</div>' +
                '</div>' +

                '<div class="chat-pending-media" id="chatPendingMedia"></div>' +
                '<div class="chat-composer-note" id="chatComposerNote" style="display:none"></div>' +

                '<form class="chat-composer" id="chatComposer" style="display:none">' +
                    '<button type="button" class="chat-attach-btn" id="chatAttachBtn" title="Attach"><i class="fas fa-plus"></i></button>' +

                    '<div class="chat-attach-menu" id="chatAttachMenu">' +
                        '<button type="button" data-kind="image"><i class="fas fa-image"></i> Photo</button>' +
                        '<button type="button" data-kind="video"><i class="fas fa-video"></i> Video</button>' +
                        '<button type="button" data-kind="audio"><i class="fas fa-music"></i> Audio</button>' +
                        '<button type="button" data-kind="document"><i class="fas fa-file-alt"></i> Document</button>' +
                    '</div>' +

                    '<input type="file" id="chatFileImage" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple hidden>' +
                    '<input type="file" id="chatFileVideo" accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime" hidden>' +
                    '<input type="file" id="chatFileAudio" accept=".mp3,.wav,.m4a,.ogg,.aac,audio/*" hidden>' +
                    '<input type="file" id="chatFileDoc" accept=".doc,.docx,.pdf,.ppt,.pptx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" hidden>' +

                    '<textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message…"></textarea>' +
                    '<button type="submit" class="chat-send-btn" id="chatSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>' +
                '</form>' +
            '</div>' +
        '</div>' +

        '<div class="chat-lightbox" id="chatLightbox">' +
            '<div class="chat-lightbox-toolbar">' +
                '<a href="#" id="chatLightboxDownload" class="chat-lightbox-btn" target="_blank" rel="noopener" download>' +
                    '<i class="fas fa-download"></i> Download' +
                '</a>' +
                '<button type="button" class="chat-lightbox-btn" id="chatLightboxClose" title="Close">✕</button>' +
            '</div>' +
            '<img id="chatLightboxImg" src="" alt="">' +
        '</div>';

    var app = qs('chatApp'),
        listWrap = qs('chatList'),
        listCount = qs('chatListCount'),
        searchInput = qs('chatSearch'),
        messagesWrap = qs('chatMessages'),
        threadHead = qs('chatThreadHead'),
        threadAvatar = qs('chatThreadAvatar'),
        threadName = qs('chatThreadName'),
        threadSub = qs('chatThreadSub'),
        listingCtx = qs('chatListingCtx'),
        listingThumb = qs('chatListingThumb'),
        listingTitle = qs('chatListingTitle'),
        listingPrice = qs('chatListingPrice'),
        composer = qs('chatComposer'),
        composerNote = qs('chatComposerNote'),
        input = qs('chatInput'),
        sendBtn = qs('chatSendBtn'),
        attachBtn = qs('chatAttachBtn'),
        attachMenu = qs('chatAttachMenu'),
        fileImage = qs('chatFileImage'),
        fileVideo = qs('chatFileVideo'),
        fileAudio = qs('chatFileAudio'),
        fileDoc = qs('chatFileDoc'),
        pendingWrap = qs('chatPendingMedia'),
        backBtn = qs('chatBackBtn'),
        lightbox = qs('chatLightbox'),
        lightboxImg = qs('chatLightboxImg'),
        lightboxClose = qs('chatLightboxClose'),
        lightboxDownload = qs('chatLightboxDownload');

    // ------------------------------------------------------------
    // Upload progress ring helpers
    // ------------------------------------------------------------

    function progressRingHtml() {
        return '<div class="chat-progress-overlay" style="display:none">' +
            '<svg class="chat-progress-ring" viewBox="0 0 36 36">' +
                '<circle class="chat-progress-bg" cx="18" cy="18" r="16"></circle>' +
                '<circle class="chat-progress-fg" cx="18" cy="18" r="16"></circle>' +
            '</svg>' +
            '<span class="chat-progress-label">0%</span>' +
        '</div>';
    }

    function setRingProgress(overlay, pct) {
        if (!overlay) return;

        var fg = overlay.querySelector('.chat-progress-fg');
        var label = overlay.querySelector('.chat-progress-label');

        var circumference = 100.53;
        var offset = circumference * (1 - (pct / 100));

        if (fg) {
            fg.style.strokeDasharray = String(circumference);
            fg.style.strokeDashoffset = String(offset);
        }

        if (label) {
            label.textContent = pct + '%';
        }
    }

    function setPendingUploading(isUploading) {
        var overlays = document.querySelectorAll('.chat-progress-overlay');

        overlays.forEach(function (overlay) {
            overlay.style.display = isUploading ? 'flex' : 'none';

            if (isUploading) {
                setRingProgress(overlay, 0);
            }
        });
    }

    function updatePendingProgress(pct) {
        pct = Math.max(0, Math.min(100, Math.round(pct)));

        var overlays = document.querySelectorAll('.chat-progress-overlay');

        overlays.forEach(function (overlay) {
            setRingProgress(overlay, pct);
        });
    }

    // ------------------------------------------------------------
    // Conversation list
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

        state.conversations.forEach(function (c) {
            n += (c.unread_count || 0);
        });

        return n;
    }

    function renderList() {
        var unread = totalUnread();

        listCount.textContent = unread > 0
            ? (unread + ' unread')
            : (state.conversations.length + ' conversations');

        var q = state.search.toLowerCase();
        listWrap.innerHTML = '';

        var shown = 0;

        state.conversations.forEach(function (c) {
            var hay = ((c.other_name || '') + ' ' + (c.last_preview || '') + ' ' + (c.listing_title || '')).toLowerCase();

            if (q && hay.indexOf(q) === -1) return;

            shown++;

            var active = state.current &&
                state.current.other === c.other_user_id &&
                state.current.listing === c.listing_id;

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

            top.appendChild(name);
            top.appendChild(time);

            var prev = el('div', 'chat-conv-preview');
            prev.innerHTML = esc((c.last_sender_is_me ? 'You: ' : '') + (c.last_preview || 'No messages yet')) +
                ((c.unread_count || 0) > 0 ? ' <span class="chat-unread-count">' + c.unread_count + '</span>' : '');

            body.appendChild(top);
            body.appendChild(prev);

            item.appendChild(av);
            item.appendChild(body);

            function open() {
                openConversation(c.other_user_id, c.listing_id, c.listing_type);
            }

            item.addEventListener('click', open);
            item.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') open();
            });

            listWrap.appendChild(item);
        });

        if (shown === 0) {
            var empty = el('div', 'chat-list-empty');
            empty.innerHTML = '<i class="fas fa-inbox"></i>' + (q ? 'No conversations match.' : 'No messages yet.');
            listWrap.appendChild(empty);
        }
    }

    function openConversation(otherId, listingId, listingType) {
        state.current = {
            other: otherId,
            listing: listingId,
            type: listingType || ''
        };

        state.lastId = 0;
        state.prevDate = null;
        state.msgEls = {};
        messagesWrap.innerHTML = '';

        threadHead.style.display = 'flex';
        app.classList.add('show-thread');

        try {
            window.history.replaceState(null, '', window.location.pathname + '?user=' + otherId + '&listing=' + (listingId || 0));
        } catch (e) {}

        loadThread(true);
        loadConversations();
    }

    // ------------------------------------------------------------
    // Thread
    // ------------------------------------------------------------

    function loadThread(initial) {
        if (!state.current) return;

        fetch(CFG.epThread + '?other_user=' + state.current.other + '&listing=' + (state.current.listing || 0) + '&since_id=0', {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success || !state.current) return;

                var conv = data.conversation || {};
                state.canReply = !!conv.can_reply;
                state.listing = conv.listing || null;

                var otherName = conv.other_name || 'Unknown';

                threadAvatar.textContent = otherName.charAt(0).toUpperCase();

                threadName.innerHTML = esc(otherName) +
                    ' <span class="chat-role-badge ' + roleBadgeClass(conv.other_role) + '">' + esc(conv.other_role || 'user') + '</span>';

                threadSub.textContent = conv.other_role === 'agent'
                    ? 'Agent'
                    : (conv.other_role === 'admin' ? 'Admin' : 'Customer');

                if (state.listing && state.listing.listing_id) {
                    listingCtx.style.display = 'flex';
                    listingCtx.href = state.listing.listing_url || '#';

                    listingTitle.textContent = state.listing.listing_title || 'Listing #' + state.listing.listing_id;
                    listingPrice.textContent = state.listing.listing_price ? money(state.listing.listing_price) : '';

                    if (state.listing.listing_thumb) {
                        listingThumb.src = state.listing.listing_thumb;
                        listingThumb.style.display = 'block';
                    } else {
                        listingThumb.style.display = 'none';
                    }
                } else {
                    listingCtx.style.display = 'none';
                }

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
                if (m.mine) {
                    updateTicks(id, m.is_read);
                } else {
                    state.msgEls[id].classList.toggle('is-unread', !m.is_read);
                }
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

        if (incoming && document.hasFocus()) {
            queueMarkRead();
        }

        if (appended > 0 && incoming && !initial) {
            playReceiveSound();
        }
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
    // Inquiry header
    // ------------------------------------------------------------

    function renderInquiryCard(m) {
        var meta = m.inquiry_meta || null;
        if (!meta) return null;

        var card = el('div', 'chat-inquiry-card');

        var title = 'Formal Inquiry';

        if (m.is_viewing_request || meta.inquiry_type === 'viewing') {
            title = 'Viewing Request';
        }

        var timestamp = formatTimestamp(meta.sent_at || m.created_at || '');

        var html =
            '<div class="chat-inquiry-head">' +
                '<div class="chat-inquiry-title"><i class="fas fa-envelope-open-text"></i> ' + esc(title) + '</div>' +
                '<div class="chat-inquiry-time">' + esc(timestamp) + '</div>' +
            '</div>' +
            '<div class="chat-inquiry-meta">';

        if (meta.name) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-user"></i> Name</span><span class="chat-inquiry-value">' + esc(meta.name) + '</span></div>';
        }

        if (meta.email) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-envelope"></i> Email</span><span class="chat-inquiry-value"><a href="mailto:' + esc(meta.email) + '">' + esc(meta.email) + '</a></span></div>';
        }

        if (meta.phone) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-phone"></i> Phone</span><span class="chat-inquiry-value">' + esc(meta.phone) + '</span></div>';
        }

        if (meta.preferred_date || m.preferred_date) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-calendar"></i> Preferred Date</span><span class="chat-inquiry-value">' + esc(meta.preferred_date || m.preferred_date) + '</span></div>';
        }

        if (meta.preferred_time || m.preferred_time) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-clock"></i> Preferred Time</span><span class="chat-inquiry-value">' + esc(meta.preferred_time || m.preferred_time) + '</span></div>';
        }

        if (meta.subject) {
            html += '<div class="chat-inquiry-row"><span class="chat-inquiry-label"><i class="fas fa-heading"></i> Subject</span><span class="chat-inquiry-value">' + esc(meta.subject) + '</span></div>';
        }

        html += '</div>';

        card.innerHTML = html;

        return card;
    }

    // ------------------------------------------------------------
    // Media renderers + download actions
    // ------------------------------------------------------------

    function downloadAnchor(url, filename, label) {
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        a.className = 'chat-media-download';

        if (filename) {
            a.setAttribute('download', filename);
        }

        a.innerHTML = '<i class="fas fa-download"></i> ' + esc(label || 'Download');

        return a;
    }

    function openLightbox(url) {
        lightboxImg.src = url;

        if (lightboxDownload) {
            lightboxDownload.href = url;
            lightboxDownload.setAttribute('download', fileNameFromUrl(url, 'image'));
        }

        lightbox.classList.add('is-open');
    }

    function renderMessage(m) {
        var row = el('div', 'chat-msg ' + (m.mine ? 'mine' : 'theirs'));
        row.setAttribute('data-msg-id', m.id);

        if (!m.mine && !m.is_read) row.classList.add('is-unread');

        var bubble = el('div', 'chat-bubble');

        var inquiryCard = renderInquiryCard(m);
        if (inquiryCard) {
            bubble.appendChild(inquiryCard);
        }

        if (m.is_viewing_request && !m.inquiry_meta) {
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

        var urls = m.media_urls || [];

        // Images
        if (m.message_type === 'image' && urls.length) {
            var grid = el('div', 'chat-msg-images' + (urls.length === 1 ? ' single' : ''));

            urls.forEach(function (u) {
                var img = el('img', 'chat-msg-img');
                img.src = u;
                img.loading = 'lazy';
                img.alt = 'Attached image';

                img.addEventListener('click', function () {
                    openLightbox(u);
                });

                grid.appendChild(img);
            });

            bubble.appendChild(grid);

            var imageActions = el('div', 'chat-media-actions');
            imageActions.appendChild(downloadAnchor(urls[0], fileNameFromUrl(urls[0], 'image'), 'Download'));
            bubble.appendChild(imageActions);
        }

        // Video
        if (m.message_type === 'video' && urls.length) {
            var videoWrap = el('div', 'chat-video-wrap');

            var video = document.createElement('video');
            video.className = 'chat-msg-video';
            video.src = urls[0];
            video.controls = true;
            video.preload = 'metadata';

            var videoActions = el('div', 'chat-media-actions');
            videoActions.appendChild(downloadAnchor(urls[0], m.media_name || fileNameFromUrl(urls[0], 'video'), 'Download'));

            videoWrap.appendChild(video);
            videoWrap.appendChild(videoActions);

            bubble.appendChild(videoWrap);
        }

        // Document
        if (m.message_type === 'document' && urls.length) {
            var docCard = el('div', 'chat-document-card');

            var docIcon = el('div', 'chat-file-icon');
            docIcon.innerHTML = '<i class="fas fa-file-alt"></i>';

            var docText = document.createElement('div');

            var docName = el('div', 'chat-file-name');
            docName.textContent = m.media_name || fileNameFromUrl(urls[0], 'Document');

            var docMeta = el('div', 'chat-file-meta');
            docMeta.textContent = formatBytes(m.media_size);

            docText.appendChild(docName);
            docText.appendChild(docMeta);

            var docActions = el('div', 'chat-file-actions');

            var openLink = document.createElement('a');
            openLink.href = urls[0];
            openLink.target = '_blank';
            openLink.rel = 'noopener';
            openLink.className = 'chat-file-action';
            openLink.innerHTML = '<i class="fas fa-eye"></i> View';

            var docDownload = downloadAnchor(urls[0], m.media_name || fileNameFromUrl(urls[0], 'document'), 'Download');
            docDownload.className = 'chat-file-action';

            docActions.appendChild(openLink);
            docActions.appendChild(docDownload);

            docCard.appendChild(docIcon);
            docCard.appendChild(docText);
            docCard.appendChild(docActions);

            bubble.appendChild(docCard);
        }

        // Audio — uploaded audio files and legacy voice notes
        if (m.message_type === 'audio' && urls.length) {
            var audioWrap = el('div', 'chat-audio-wrap');

            var audio = document.createElement('audio');
            audio.controls = true;
            audio.src = urls[0];
            audio.preload = 'metadata';

            var audioActions = el('div', 'chat-media-actions');
            audioActions.appendChild(downloadAnchor(urls[0], m.media_name || fileNameFromUrl(urls[0], 'audio'), 'Download'));

            audioWrap.appendChild(audio);
            audioWrap.appendChild(audioActions);

            bubble.appendChild(audioWrap);
        }

        var metaRow = el('div', 'chat-msg-meta');
        metaRow.innerHTML = '<span>' + esc(m.time_formatted || '') + '</span>' +
            (m.mine ? ' <span class="chat-ticks' + (m.is_read ? ' is-read' : '') + '">' + (m.is_read ? '✓✓' : '✓') + '</span>' : '');

        bubble.appendChild(metaRow);
        row.appendChild(bubble);

        return row;
    }

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

            fetch(CFG.epMarkRead, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .catch(function () {});
        }, 400);
    }

    // ------------------------------------------------------------
    // Pending attachments
    // ------------------------------------------------------------

    function revokePendingUrls() {
        state.pendingImages.forEach(function (item) {
            try {
                URL.revokeObjectURL(item.url);
            } catch (e) {}
        });

        if (state.pendingFile && state.pendingFile.url) {
            try {
                URL.revokeObjectURL(state.pendingFile.url);
            } catch (e) {}
        }
    }

    function clearPending() {
        revokePendingUrls();

        state.pendingImages = [];
        state.pendingFile = null;

        renderPending();
    }

    function renderPending() {
        pendingWrap.innerHTML = '';

        if (!state.pendingImages.length && !state.pendingFile) {
            pendingWrap.classList.remove('has-items');
            return;
        }

        pendingWrap.classList.add('has-items');

        state.pendingImages.forEach(function (item, idx) {
            var pendingItem = el('div', 'chat-pending-item');

            var img = document.createElement('img');
            img.src = item.url;
            img.alt = 'Pending image';

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.innerHTML = '<i class="fas fa-times"></i>';
            rm.title = 'Remove image';

            rm.addEventListener('click', function () {
                if (state.sending) return;

                try {
                    URL.revokeObjectURL(item.url);
                } catch (e) {}

                state.pendingImages.splice(idx, 1);
                renderPending();
            });

            pendingItem.appendChild(img);
            pendingItem.appendChild(rm);

            var ringWrap = document.createElement('div');
            ringWrap.innerHTML = progressRingHtml();
            pendingItem.appendChild(ringWrap.firstChild);

            pendingWrap.appendChild(pendingItem);
        });

        if (state.pendingFile) {
            var fileItem = el('div', 'chat-pending-file');

            var icon = el('div', 'chat-file-icon');
            icon.innerHTML = fileIconForKind(state.pendingFile.kind);

            var textBox = document.createElement('div');
            textBox.className = 'chat-file-text';

            var name = el('div', 'chat-file-name');
            name.textContent = state.pendingFile.file.name;

            var meta = el('div', 'chat-file-meta');
            meta.textContent = formatBytes(state.pendingFile.file.size);

            textBox.appendChild(name);
            textBox.appendChild(meta);

            var progressWrap = el('div', 'chat-file-progress');
            progressWrap.innerHTML = progressRingHtml();

            var rmFile = document.createElement('button');
            rmFile.type = 'button';
            rmFile.className = 'chat-file-remove';
            rmFile.innerHTML = '<i class="fas fa-times"></i>';
            rmFile.title = 'Remove file';

            rmFile.addEventListener('click', function () {
                if (state.sending) return;

                if (state.pendingFile && state.pendingFile.url) {
                    try {
                        URL.revokeObjectURL(state.pendingFile.url);
                    } catch (e) {}
                }

                state.pendingFile = null;
                renderPending();
            });

            fileItem.appendChild(icon);
            fileItem.appendChild(textBox);
            fileItem.appendChild(progressWrap.firstChild);
            fileItem.appendChild(rmFile);

            pendingWrap.appendChild(fileItem);
        }
    }

    // ------------------------------------------------------------
    // Attachment menu
    // ------------------------------------------------------------

    function toggleAttachMenu(force) {
        if (typeof force === 'boolean') {
            attachMenu.classList.toggle('is-open', force);
        } else {
            attachMenu.classList.toggle('is-open');
        }
    }

    attachBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleAttachMenu();
    });

    document.addEventListener('click', function (e) {
        if (!attachMenu.contains(e.target) && e.target !== attachBtn) {
            attachMenu.classList.remove('is-open');
        }
    });

    Array.prototype.forEach.call(attachMenu.querySelectorAll('button'), function (btn) {
        btn.addEventListener('click', function () {
            var kind = btn.getAttribute('data-kind');
            toggleAttachMenu(false);

            if (state.sending) return;

            if (kind === 'image') fileImage.click();
            if (kind === 'video') fileVideo.click();
            if (kind === 'audio') fileAudio.click();
            if (kind === 'document') fileDoc.click();
        });
    });

    fileImage.addEventListener('change', function () {
        if (state.sending) return;

        if (state.pendingFile) {
            toast('Remove the selected file before adding photos.', 'warning');
            this.value = '';
            return;
        }

        var files = Array.prototype.slice.call(this.files || []);

        files.forEach(function (f) {
            if (state.pendingImages.length >= CFG.maxImages) {
                toast('Max ' + CFG.maxImages + ' images.', 'error');
                return;
            }

            var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (allowed.indexOf(f.type) === -1) {
                toast('Only JPG, PNG, WEBP or GIF images.', 'error');
                return;
            }

            if (f.size > CFG.maxImageBytes) {
                toast('Image too large. Max 10MB.', 'error');
                return;
            }

            state.pendingImages.push({
                file: f,
                url: URL.createObjectURL(f),
                progress: 0
            });
        });

        this.value = '';
        renderPending();
    });

    function selectSingleFile(input, kind, maxBytes, allowedExts) {
        if (state.sending) return;

        if (state.pendingImages.length) {
            toast('Remove pending photos before adding another attachment.', 'warning');
            input.value = '';
            return;
        }

        if (state.pendingFile) {
            toast('Only one attachment is allowed.', 'warning');
            input.value = '';
            return;
        }

        var f = input.files && input.files[0];
        if (!f) return;

        var ext = (f.name.split('.').pop() || '').toLowerCase();

        if (allowedExts.indexOf(ext) === -1) {
            toast('Unsupported file type.', 'error');
            input.value = '';
            return;
        }

        if (f.size > maxBytes) {
            toast('File too large.', 'error');
            input.value = '';
            return;
        }

        state.pendingFile = {
            file: f,
            kind: kind,
            url: URL.createObjectURL(f),
            progress: 0
        };

        renderPending();
        input.value = '';
    }

    fileVideo.addEventListener('change', function () {
        selectSingleFile(this, 'video', CFG.maxVideoBytes, ['mp4', 'webm', 'mov']);
    });

    fileAudio.addEventListener('change', function () {
        selectSingleFile(this, 'audio', CFG.maxAudioBytes, ['mp3', 'wav', 'm4a', 'ogg', 'aac']);
    });

    fileDoc.addEventListener('change', function () {
        selectSingleFile(this, 'document', CFG.maxDocBytes, ['doc', 'docx', 'pdf', 'ppt', 'pptx']);
    });

    // ------------------------------------------------------------
    // Send with circular upload progress
    // ------------------------------------------------------------

    function doSend(opts) {
        if (!state.current || !state.canReply || state.sending) return;

        state.sending = true;
        sendBtn.disabled = true;

        setPendingUploading(true);
        updatePendingProgress(0);

        var fd = new FormData();

        fd.append('csrf_token', CFG.csrf);
        fd.append('receiver_id', state.current.other);
        fd.append('listing_id', state.current.listing || 0);
        fd.append('listing_type', state.current.type || '');
        fd.append('body', opts.body || '');

        (opts.images || []).forEach(function (item) {
            fd.append('images[]', item.file, item.file.name);
        });

        if (opts.attachment) {
            fd.append('attachment', opts.attachment.file, opts.attachment.file.name);
            fd.append('attachment_kind', opts.attachment.kind);
        }

        var xhr = new XMLHttpRequest();

        xhr.open('POST', CFG.epSend);
        xhr.withCredentials = true;

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                updatePendingProgress(pct);
            }
        };

        xhr.onload = function () {
            state.sending = false;
            sendBtn.disabled = false;

            setPendingUploading(false);

            var data = null;

            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                toast('Unexpected server response.', 'error');
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300 && data && data.success && data.message) {
                playSendSound();

                input.value = '';
                input.style.height = '40px';

                clearPending();

                applyMessages([data.message], false);
                messagesWrap.scrollTop = messagesWrap.scrollHeight;

                loadConversations();
            } else {
                toast((data && data.error) || 'Could not send.', 'error');
            }
        };

        xhr.onerror = function () {
            state.sending = false;
            sendBtn.disabled = false;

            setPendingUploading(false);

            toast('Network error.', 'error');
        };

        xhr.send(fd);
    }

    composer.addEventListener('submit', function (e) {
        e.preventDefault();

        if (state.sending) return;

        var body = input.value.trim();

        if (state.pendingFile) {
            doSend({
                body: body,
                images: [],
                attachment: state.pendingFile
            });
            return;
        }

        if (state.pendingImages.length) {
            doSend({
                body: body,
                images: state.pendingImages.slice(),
                attachment: null
            });
            return;
        }

        if (!body) return;

        doSend({
            body: body,
            images: [],
            attachment: null
        });
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            composer.requestSubmit ? composer.requestSubmit() : composer.dispatchEvent(new Event('submit'));
        }
    });

    input.addEventListener('input', function () {
        input.style.height = '40px';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });

    // ------------------------------------------------------------
    // Lightbox / navigation
    // ------------------------------------------------------------

    lightboxClose.addEventListener('click', function () {
        lightbox.classList.remove('is-open');
    });

    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) lightbox.classList.remove('is-open');
    });

    backBtn.addEventListener('click', function () {
        app.classList.remove('show-thread');

        try {
            window.history.replaceState(null, '', window.location.pathname);
        } catch (e) {}
    });

    searchInput.addEventListener('input', function () {
        state.search = searchInput.value.trim();
        renderList();
    });

    setInterval(function () {
        if (state.current) loadThread(false);
    }, CFG.pollThreadMs);

    setInterval(loadConversations, CFG.pollListMs);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            loadConversations();
            if (state.current) loadThread(false);
        }
    });

    window.addEventListener('beforeunload', function () {
        revokePendingUrls();
    });

    // ------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------

    loadConversations();

    if (CFG.openOther > 0) {
        openConversation(CFG.openOther, CFG.openListing, CFG.openType);
    }

    window.__kinasChatBoot = true;
    window.__kinasChatJsLoaded = true;
})();
