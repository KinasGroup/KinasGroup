<?php
// Authenticated, per-session content — never cache this page.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();
$csrf = Security::generateCSRFToken();

$activeSubscriberCount = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'")->fetchColumn();

$campaigns = $db->query(
    "SELECT c.*, u.name AS created_by_name
     FROM newsletter_campaigns c
     LEFT JOIN users u ON u.id = c.created_by
     ORDER BY c.created_at DESC
     LIMIT 25"
)->fetchAll(PDO::FETCH_ASSOC);

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.nl-wrap { max-width: 900px; }
.nl-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 24px; }
.nl-card h2 { font-family: 'Prata', serif; font-size: 18px; color: #0A0A0A; margin-bottom: 4px; }
.nl-card .nl-sub { color: #666; font-size: 13px; margin-bottom: 18px; }
.nl-field { margin-bottom: 16px; }
.nl-field label { display: block; font-weight: 600; font-size: 13px; color: #333; margin-bottom: 6px; }
.nl-field input[type="text"], .nl-field textarea {
    width: 100%; padding: 12px 14px; border: 1px solid #DDD; border-radius: 8px;
    font-family: inherit; font-size: 14px; box-sizing: border-box; resize: vertical;
}
.nl-field textarea { min-height: 220px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; }
.nl-hint { font-size: 12px; color: #999; margin-top: 4px; }
.nl-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.nl-btn { padding: 12px 24px; border-radius: 40px; font-weight: 600; font-size: 14px; cursor: pointer; border: none; transition: all .2s; }
.nl-btn-primary { background: #C6A43F; color: #0A0A0A; }
.nl-btn-primary:hover { background: #A8882E; }
.nl-btn-outline { background: transparent; color: #0A0A0A; border: 1.5px solid #0A0A0A; }
.nl-btn-outline:hover { background: #0A0A0A; color: #fff; }
.nl-btn:disabled { opacity: .5; cursor: not-allowed; }
.nl-recipients { font-size: 13px; color: #666; }
.nl-recipients strong { color: #C6A43F; }
.nl-progress-wrap { display: none; margin-top: 18px; }
.nl-progress-bar-bg { background: #EEE; border-radius: 999px; height: 10px; overflow: hidden; }
.nl-progress-bar { background: #C6A43F; height: 100%; width: 0%; transition: width .3s ease; }
.nl-progress-text { font-size: 13px; color: #666; margin-top: 8px; }
.nl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.nl-table th { text-align: left; padding: 10px 12px; background: #F8F8F8; color: #666; font-weight: 600; border-bottom: 1px solid #E0E0E0; }
.nl-table td { padding: 12px; border-bottom: 1px solid #F0F0F0; vertical-align: top; }
.nl-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.nl-badge.draft { background: #EEE; color: #666; }
.nl-badge.sending { background: #FFF3CD; color: #856404; }
.nl-badge.sent { background: #D4EDDA; color: #155724; }
.nl-badge.failed { background: #F8D7DA; color: #721C24; }
.nl-empty { text-align: center; padding: 40px 20px; color: #999; font-size: 14px; }

@media (prefers-color-scheme: dark) {
    .nl-card, .nl-card *, .nl-table, .nl-table * { background-color: #ffffff !important; color: #0A0A0A !important; }
    .nl-card .nl-sub, .nl-hint, .nl-recipients, .nl-progress-text { color: #666666 !important; }
    .nl-field input, .nl-field textarea { background-color: #ffffff !important; color: #0A0A0A !important; border-color: #dddddd !important; }
    .nl-table th { background-color: #F8F8F8 !important; color: #666666 !important; }
}
</style>

<div class="je-dash-shell" style="max-width:100%;overflow-x:hidden;">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main" style="overflow-x:hidden;width:100%;max-width:100%;padding:24px;">

<div class="nl-wrap">
    <div class="page-header" style="margin-bottom:24px;">
        <h1 style="font-family:'Prata',serif;font-size:26px;color:#0A0A0A;"><i class="fas fa-paper-plane" style="color:#C6A43F;margin-right:10px;"></i>Newsletter</h1>
        <p style="color:#666;font-size:14px;">Compose and send an issue to everyone subscribed on the site.</p>
    </div>

    <div class="nl-card">
        <h2>Compose</h2>
        <p class="nl-sub">Write your newsletter below. Every email automatically gets the KINAS GROUP branded wrapper and a personal unsubscribe link — you don't need to add either.</p>

        <div id="nlFormError" style="display:none; background:#FEF2F2; color:#B71C1C; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px;"></div>
        <div id="nlFormSuccess" style="display:none; background:#E8F5E9; color:#2E7D32; padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:13px;"></div>

        <div class="nl-field">
            <label for="nlSubject">Subject line</label>
            <input type="text" id="nlSubject" placeholder="e.g. New listings just added this week">
        </div>

        <div class="nl-field">
            <label for="nlBody">Message (HTML)</label>
            <textarea id="nlBody" placeholder="&lt;p&gt;Hi there,&lt;/p&gt;&lt;p&gt;Here's what's new...&lt;/p&gt;"></textarea>
            <p class="nl-hint">Basic HTML is fine — paragraphs, links, bold text, images. It'll be wrapped in the same styled template as your other emails.</p>
        </div>

        <div class="nl-actions">
            <button type="button" class="nl-btn nl-btn-primary" id="nlSendBtn">
                <i class="fas fa-paper-plane"></i> Send to <span id="nlRecipientCountLabel"><?= number_format($activeSubscriberCount) ?></span> subscriber<?= $activeSubscriberCount === 1 ? '' : 's' ?>
            </button>
            <button type="button" class="nl-btn nl-btn-outline" id="nlDraftBtn">Save as draft</button>
            <span class="nl-recipients"><strong><?= number_format($activeSubscriberCount) ?></strong> active subscriber<?= $activeSubscriberCount === 1 ? '' : 's' ?> will receive this</span>
        </div>

        <div class="nl-progress-wrap" id="nlProgressWrap">
            <div class="nl-progress-bar-bg"><div class="nl-progress-bar" id="nlProgressBar"></div></div>
            <div class="nl-progress-text" id="nlProgressText">Sending…</div>
        </div>
    </div>

    <div class="nl-card">
        <h2>Past campaigns</h2>
        <p class="nl-sub">The 25 most recent newsletters sent or drafted from this page.</p>

        <?php if (empty($campaigns)): ?>
            <div class="nl-empty"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;color:#DDD;"></i>No newsletters yet — compose your first one above.</div>
        <?php else: ?>
        <table class="nl-table">
            <thead>
                <tr><th>Subject</th><th>Status</th><th>Sent</th><th>Failed</th><th>By</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['subject']) ?></td>
                    <td><span class="nl-badge <?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars(ucfirst($c['status'])) ?></span></td>
                    <td><?= number_format((int)$c['sent_count']) ?> / <?= number_format((int)$c['total_recipients']) ?></td>
                    <td><?= (int)$c['failed_count'] > 0 ? number_format((int)$c['failed_count']) : '—' ?></td>
                    <td><?= htmlspecialchars($c['created_by_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars(date('M j, Y g:ia', strtotime($c['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</main>
</div>

<script>
(function() {
    const csrfToken = <?= json_encode($csrf) ?>;
    const sendBtn = document.getElementById('nlSendBtn');
    const draftBtn = document.getElementById('nlDraftBtn');
    const subjectEl = document.getElementById('nlSubject');
    const bodyEl = document.getElementById('nlBody');
    const errorEl = document.getElementById('nlFormError');
    const successEl = document.getElementById('nlFormSuccess');
    const progressWrap = document.getElementById('nlProgressWrap');
    const progressBar = document.getElementById('nlProgressBar');
    const progressText = document.getElementById('nlProgressText');

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        successEl.style.display = 'none';
    }
    function showSuccess(msg) {
        successEl.textContent = msg;
        successEl.style.display = 'block';
        errorEl.style.display = 'none';
    }
    function clearMessages() {
        errorEl.style.display = 'none';
        successEl.style.display = 'none';
    }

    function validate() {
        if (!subjectEl.value.trim()) { showError('Please enter a subject line.'); return false; }
        if (!bodyEl.value.trim()) { showError('Please write a message.'); return false; }
        return true;
    }

    draftBtn.addEventListener('click', async function() {
        if (!validate()) return;
        clearMessages();
        draftBtn.disabled = true;
        try {
            const res = await fetch('/api/admin/newsletter-create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subject: subjectEl.value.trim(), body_html: bodyEl.value.trim(), action: 'draft', csrf_token: csrfToken })
            });
            const data = await res.json();
            if (data.success) {
                showSuccess('Draft saved. Reloading…');
                setTimeout(() => window.location.reload(), 900);
            } else {
                showError(data.error || 'Could not save the draft.');
            }
        } catch (e) {
            showError('Network error. Please try again.');
        } finally {
            draftBtn.disabled = false;
        }
    });

    sendBtn.addEventListener('click', async function() {
        if (!validate()) return;
        const total = parseInt(document.getElementById('nlRecipientCountLabel').textContent.replace(/,/g, ''), 10) || 0;
        if (total === 0) {
            showError('There are no active subscribers to send to yet.');
            return;
        }
        if (!confirm(`Send this newsletter to ${total.toLocaleString()} subscriber${total === 1 ? '' : 's'}? This can't be undone.`)) {
            return;
        }

        clearMessages();
        sendBtn.disabled = true;
        draftBtn.disabled = true;

        try {
            const createRes = await fetch('/api/admin/newsletter-create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subject: subjectEl.value.trim(), body_html: bodyEl.value.trim(), action: 'send', csrf_token: csrfToken })
            });
            const createData = await createRes.json();
            if (!createData.success) {
                showError(createData.error || 'Could not start the send.');
                sendBtn.disabled = false;
                draftBtn.disabled = false;
                return;
            }

            const campaignId = createData.campaign_id;
            progressWrap.style.display = 'block';
            await sendNextBatch(campaignId);

        } catch (e) {
            showError('Network error. Please try again — already-sent subscribers will not be emailed twice.');
            sendBtn.disabled = false;
            draftBtn.disabled = false;
        }
    });

    async function sendNextBatch(campaignId) {
        try {
            const res = await fetch('/api/admin/newsletter-send-batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ campaign_id: campaignId, csrf_token: csrfToken })
            });
            const data = await res.json();

            if (!data.success) {
                showError(data.error || 'Something went wrong while sending.');
                sendBtn.disabled = false;
                draftBtn.disabled = false;
                return;
            }

            const pct = data.total_recipients > 0 ? Math.round((data.sent_count / data.total_recipients) * 100) : 100;
            progressBar.style.width = pct + '%';
            progressText.textContent = `Sent ${data.sent_count.toLocaleString()} of ${data.total_recipients.toLocaleString()}` + (data.failed_count > 0 ? ` (${data.failed_count} failed)` : '') + '…';

            if (data.done) {
                progressText.textContent = `Done — sent to ${data.sent_count.toLocaleString()} of ${data.total_recipients.toLocaleString()} subscribers` + (data.failed_count > 0 ? `, ${data.failed_count} failed` : '') + '.';
                showSuccess('Newsletter sent! Reloading…');
                setTimeout(() => window.location.reload(), 1500);
                return;
            }

            // Keep going — small delay between batches so we don't hammer
            // the email provider's rate limit.
            setTimeout(() => sendNextBatch(campaignId), 400);

        } catch (e) {
            showError('Network error mid-send. Reload the page and click Send again — already-sent subscribers will not be emailed twice.');
            sendBtn.disabled = false;
            draftBtn.disabled = false;
        }
    }
})();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
