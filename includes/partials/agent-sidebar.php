<?php
/**
 * Agent Sidebar - Navigation for agent dashboard
 *
 * AMENDED: Added real-time unread message badge with 15-second polling.
 * Badge style matches the header notification system (red circle, pulse).
 * Since je_render_sidebar() generates the HTML, we inject the badge via JS.
 */
require_once __DIR__ . '/../../includes/je-sidebar.php';
require_once __DIR__ . '/../../includes/public-identity.php';

$currentPage = basename($_SERVER['PHP_SELF']);

je_render_sidebar('agent', $currentPage, 2);
?>

<style>
/* Sidebar notification badge — matches header style */
.je-sidebar-msg-badge {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    background: #dc3545;
    color: #ffffff;
    border-radius: 50%;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    text-align: center;
    line-height: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    font-family: 'Inter', Arial, sans-serif;
    pointer-events: none;
    display: none;
}
.je-sidebar-msg-badge.show {
    display: inline-block !important;
    animation: agentSidebarBadgePulse 0.5s ease-in-out 2;
}
@keyframes agentSidebarBadgePulse {
    0% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-50%) scale(1.3); }
    100% { transform: translateY(-50%) scale(1); }
}
</style>

<script>
// ============================================================
// AGENT SIDEBAR NOTIFICATION — 15-second polling (matches header)
// ============================================================
(function() {
    'use strict';

    var CONFIG = {
        refreshInterval: 15000,
        apiEndpoint: '/api/messages/unread-count.php'
    };

    var timeout = null;
    var lastCount = -1;
    var badgeEl = null;

    // Find the Messages link in the rendered sidebar and inject a badge
    function initSidebarBadge() {
        var links = document.querySelectorAll('.je-dash-sidebar a, .je-dash-nav a, aside a');
        for (var i = 0; i < links.length; i++) {
            var href = links[i].getAttribute('href') || '';
            var text = (links[i].textContent || '').trim().toLowerCase();
            if (href.indexOf('/agent/messages.php') !== -1 || text === 'messages') {
                links[i].style.position = 'relative';
                badgeEl = document.createElement('span');
                badgeEl.className = 'je-sidebar-msg-badge';
                badgeEl.id = 'agentSidebarMsgBadge';
                badgeEl.textContent = '0';
                links[i].appendChild(badgeEl);
                break;
            }
        }
    }

    function updateAgentSidebarBadge() {
        fetch(CONFIG.apiEndpoint, {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(response) {
            if (response.status === 401 || response.status === 403) return;
            if (!response.ok) throw new Error('Failed to fetch');
            return response.json();
        })
        .then(function(data) {
            if (data && data.success) {
                var count = data.unread_count || 0;
                if (badgeEl) {
                    if (count > 0) {
                        badgeEl.textContent = count > 99 ? '99+' : count;
                        badgeEl.classList.add('show');
                        badgeEl.style.display = 'inline-block';
                    } else {
                        badgeEl.classList.remove('show');
                        badgeEl.style.display = 'none';
                    }
                }
                lastCount = count;
            }
        })
        .catch(function() {});
    }

    function startPolling() {
        initSidebarBadge();
        updateAgentSidebarBadge();
        timeout = setInterval(updateAgentSidebarBadge, CONFIG.refreshInterval);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        // Small delay to ensure je_render_sidebar() HTML is fully in DOM
        setTimeout(startPolling, 100);
    }

    window.addEventListener('beforeunload', function() {
        if (timeout) { clearInterval(timeout); timeout = null; }
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) { updateAgentSidebarBadge(); }
    });
})();
</script>
