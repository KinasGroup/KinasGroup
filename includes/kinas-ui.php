<?php
/**
 * KINAS GLOBAL UI PARTIAL — kinas-ui.php
 * Include just before </body> on pages that do NOT use templates/footer.php.
 * Provides: kinasConfirm(), kinasToast(), and the data-kinas-confirm interceptor.
 * The same system is also injected via templates/footer.php for all standard pages.
 */
?>
<style>
/* ── Confirm Modal ────────────────────────────────────────── */
#kinasConfirmOverlay {
    display: none; position: fixed; inset: 0; z-index: 99999;
    background: rgba(10,10,10,0.72); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
    animation: kinasOverlayIn 0.2s ease;
}
#kinasConfirmOverlay.active { display: flex; }
@keyframes kinasOverlayIn { from { opacity:0; } to { opacity:1; } }

#kinasConfirmBox {
    background: #fff; border-radius: 16px; padding: 0;
    width: min(440px, 92vw); overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,0.28), 0 0 0 1px rgba(0,0,0,0.06);
    animation: kinasBoxIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes kinasBoxIn {
    from { opacity:0; transform: scale(0.88) translateY(16px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}
#kinasConfirmHead {
    padding: 28px 28px 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex; align-items: flex-start; gap: 16px;
}
#kinasConfirmIconWrap {
    width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(220,38,38,0.1); font-size: 20px; color: #DC2626;
}
#kinasConfirmIconWrap.is-warning { background: rgba(245,158,11,0.12); color: #D97706; }
#kinasConfirmIconWrap.is-gold    { background: rgba(198,164,63,0.12); color: #C6A43F; }
#kinasConfirmTitleWrap { flex: 1; }
#kinasConfirmTitle {
    font-family: 'Prata', Georgia, serif;
    font-size: 18px; font-weight: 400; color: #0A0A0A;
    margin: 0 0 4px; line-height: 1.3;
}
#kinasConfirmSubtitle {
    font-size: 13px; color: #777; margin: 0; line-height: 1.5;
    font-family: 'Inter', sans-serif;
}
#kinasConfirmMsg {
    padding: 18px 28px 0;
    font-size: 14px; color: #444; line-height: 1.65;
    font-family: 'Inter', sans-serif;
    background: #fafafa; margin: 0;
}
#kinasConfirmWarningBadge {
    display: none; margin: 16px 28px 0;
    background: #FEF9EC; border: 1px solid #F5E6B0;
    border-radius: 8px; padding: 10px 14px;
    font-size: 12px; color: #92660A;
    font-family: 'Inter', sans-serif;
    align-items: center; gap: 8px;
}
#kinasConfirmWarningBadge.visible { display: flex; }
#kinasConfirmWarningBadge i { flex-shrink: 0; }
#kinasConfirmActions {
    display: flex; gap: 10px; padding: 20px 28px 24px;
    background: #fafafa; justify-content: flex-end;
}
#kinasConfirmCancel {
    padding: 10px 22px; border-radius: 999px; border: 1.5px solid #e0e0e0;
    background: #fff; color: #555; font-size: 13px; font-weight: 600;
    font-family: 'Inter', sans-serif; letter-spacing: 0.3px;
    cursor: pointer; transition: all 0.2s; text-transform: uppercase;
}
#kinasConfirmCancel:hover { border-color: #aaa; color: #222; }
#kinasConfirmProceed {
    padding: 10px 22px; border-radius: 999px; border: none;
    background: #DC2626; color: #fff; font-size: 13px; font-weight: 600;
    font-family: 'Inter', sans-serif; letter-spacing: 0.3px;
    cursor: pointer; transition: all 0.2s; text-transform: uppercase;
    display: flex; align-items: center; gap: 7px;
}
#kinasConfirmProceed:hover { background: #b91c1c; transform: translateY(-1px); }
#kinasConfirmProceed:active { transform: translateY(0); }
#kinasConfirmProceed.is-warning { background: #D97706; }
#kinasConfirmProceed.is-warning:hover { background: #b45309; }
#kinasConfirmProceed.is-gold { background: #C6A43F; color: #0A0A0A; }
#kinasConfirmProceed.is-gold:hover { background: #A8882E; }

/* ── Toast Notifications ──────────────────────────────────── */
#kinasToastContainer {
    position: fixed; bottom: 28px; right: 28px; z-index: 100000;
    display: flex; flex-direction: column; gap: 10px;
    pointer-events: none;
}
.kinas-toast {
    min-width: 280px; max-width: 380px;
    background: #1A1A1A; color: #fff; border-radius: 12px;
    padding: 14px 18px; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.22);
    font-family: 'Inter', sans-serif; font-size: 13.5px; line-height: 1.45;
    pointer-events: all; position: relative;
    animation: kinasToastIn 0.3s cubic-bezier(0.34,1.4,0.64,1);
    border-left: 3px solid #C6A43F;
}
.kinas-toast.is-error   { border-left-color: #EF4444; }
.kinas-toast.is-success { border-left-color: #22C55E; }
.kinas-toast.is-info    { border-left-color: #3B82F6; }
.kinas-toast i { font-size: 15px; flex-shrink: 0; color: #C6A43F; }
.kinas-toast.is-error   i { color: #EF4444; }
.kinas-toast.is-success i { color: #22C55E; }
.kinas-toast.is-info    i { color: #3B82F6; }
.kinas-toast-progress {
    position: absolute; bottom: 0; left: 0; height: 2px;
    background: rgba(255,255,255,0.25); border-radius: 0 0 12px 12px;
    animation: kinasToastProgress var(--toast-dur, 5s) linear forwards;
}
@keyframes kinasToastIn {
    from { opacity:0; transform: translateX(60px); }
    to   { opacity:1; transform: translateX(0); }
}
@keyframes kinasToastOut {
    from { opacity:1; transform: translateX(0); max-height:100px; margin-bottom:10px; }
    to   { opacity:0; transform: translateX(60px); max-height:0;   margin-bottom:0;  }
}
@keyframes kinasToastProgress { from { width:100%; } to { width:0%; } }

@media (max-width: 480px) {
    #kinasConfirmBox { width: 94vw; }
    #kinasConfirmHead { padding: 22px 20px 16px; }
    #kinasConfirmMsg { padding: 14px 20px 0; }
    #kinasConfirmActions { padding: 16px 20px 20px; flex-direction: column-reverse; }
    #kinasConfirmCancel, #kinasConfirmProceed { width: 100%; justify-content: center; padding: 13px 22px; }
    #kinasToastContainer { left: 14px; right: 14px; bottom: 20px; }
    .kinas-toast { min-width: unset; max-width: unset; }
}
</style>

<div id="kinasConfirmOverlay" role="dialog" aria-modal="true" aria-labelledby="kinasConfirmTitle">
    <div id="kinasConfirmBox">
        <div id="kinasConfirmHead">
            <div id="kinasConfirmIconWrap"><i class="fas fa-trash-alt"></i></div>
            <div id="kinasConfirmTitleWrap">
                <h3 id="kinasConfirmTitle">Confirm Action</h3>
                <p id="kinasConfirmSubtitle">This action cannot be undone.</p>
            </div>
        </div>
        <p id="kinasConfirmMsg"></p>
        <div id="kinasConfirmWarningBadge">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="kinasConfirmWarningText"></span>
        </div>
        <div id="kinasConfirmActions">
            <button id="kinasConfirmCancel" type="button">Cancel</button>
            <button id="kinasConfirmProceed" type="button">
                <i class="fas fa-trash-alt"></i>
                <span>Delete</span>
            </button>
        </div>
    </div>
</div>

<div id="kinasToastContainer" aria-live="polite"></div>

<script>
window.kinasConfirm = function(message, onConfirm, opts) {
    opts = opts || {};
    var variant  = opts.variant  || 'danger';
    var title    = opts.title    || 'Confirm Deletion';
    var subtitle = opts.subtitle || 'This action cannot be undone.';
    var label    = opts.confirm  || 'Delete';
    var icon     = opts.icon     || 'fa-trash-alt';

    var overlay     = document.getElementById('kinasConfirmOverlay');
    var iconWrap    = document.getElementById('kinasConfirmIconWrap');
    var badge       = document.getElementById('kinasConfirmWarningBadge');
    var badgeTxt    = document.getElementById('kinasConfirmWarningText');
    var proceedBtn  = document.getElementById('kinasConfirmProceed');
    var proceedIcon = proceedBtn.querySelector('i');
    var proceedLbl  = proceedBtn.querySelector('span');

    document.getElementById('kinasConfirmTitle').textContent    = title;
    document.getElementById('kinasConfirmSubtitle').textContent = subtitle;
    document.getElementById('kinasConfirmMsg').textContent      = message;

    iconWrap.className   = 'is-' + variant;
    iconWrap.innerHTML   = '<i class="fas ' + icon + '"></i>';
    proceedBtn.className = 'is-' + variant;
    proceedIcon.className = 'fas ' + icon;
    proceedLbl.textContent = label;

    if (opts.warning) { badgeTxt.textContent = opts.warning; badge.classList.add('visible'); }
    else              { badge.classList.remove('visible'); }

    overlay.classList.add('active');
    document.getElementById('kinasConfirmCancel').focus();

    function close() {
        overlay.classList.remove('active');
        overlay.removeEventListener('click', outsideClick);
        document.removeEventListener('keydown', escKey);
    }
    function outsideClick(e) { if (e.target === overlay) close(); }
    function escKey(e)       { if (e.key === 'Escape') close(); }

    overlay.addEventListener('click', outsideClick);
    document.addEventListener('keydown', escKey);

    document.getElementById('kinasConfirmCancel').onclick = close;
    proceedBtn.onclick = function() { close(); if (onConfirm) onConfirm(); };
};

window.kinasToast = function(message, type, duration) {
    type = type || 'error';
    duration = duration || 5000;
    var container = document.getElementById('kinasToastContainer');
    var iconMap = { error:'fa-exclamation-circle', success:'fa-check-circle', info:'fa-info-circle', warning:'fa-exclamation-triangle' };
    var toast = document.createElement('div');
    toast.className = 'kinas-toast is-' + type;
    toast.style.setProperty('--toast-dur', (duration / 1000) + 's');
    toast.innerHTML = '<i class="fas ' + (iconMap[type] || iconMap.error) + '"></i><span>' + message + '</span><div class="kinas-toast-progress"></div>';
    container.appendChild(toast);
    var timer = setTimeout(function() {
        toast.style.animation = 'kinasToastOut 0.35s ease forwards';
        setTimeout(function() { toast.remove(); }, 340);
    }, duration);
    toast.addEventListener('click', function() {
        clearTimeout(timer);
        toast.style.animation = 'kinasToastOut 0.35s ease forwards';
        setTimeout(function() { toast.remove(); }, 340);
    });
};

document.addEventListener('submit', function(e) {
    var form = e.target;
    var msg = form.dataset.kinasConfirm;
    if (!msg) return;
    e.preventDefault();
    kinasConfirm(msg, function() {
        form.removeAttribute('data-kinas-confirm');
        form.submit();
    }, {
        variant: form.dataset.kinasVariant || 'danger',
        confirm: form.dataset.kinasLabel   || 'Delete',
        title:   form.dataset.kinasTitle   || 'Confirm Deletion',
        icon:    form.dataset.kinasIcon    || 'fa-trash-alt',
        warning: form.dataset.kinasWarning || ''
    });
}, true);

document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-kinas-confirm]');
    if (!el || el.tagName === 'FORM') return;
    var href = el.getAttribute('href') || el.dataset.href;
    if (!href) return;
    e.preventDefault();
    kinasConfirm(el.dataset.kinasConfirm, function() { window.location.href = href; }, {
        variant: el.dataset.kinasVariant || 'danger',
        confirm: el.dataset.kinasLabel   || 'Delete',
        title:   el.dataset.kinasTitle   || 'Confirm Deletion',
        icon:    el.dataset.kinasIcon    || 'fa-trash-alt',
        warning: el.dataset.kinasWarning || ''
    });
}, true);
</script>
