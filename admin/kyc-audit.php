<?php
/**
 * KINAS GROUP — Retroactive KYC identity audit.
 *
 * One-off tool: re-checks every agent whose KYC was already approved
 * BEFORE the identity cross-check fix existed, against the raw Didit
 * decision payload already stored for their session. Surfaces any
 * account where the registered name doesn't reasonably match the name
 * on the ID document that was actually scanned — the same class of bug
 * reported for one account, but this checks all of them.
 *
 * This does not change anything automatically. It only reports —
 * suspending/re-reviewing a flagged account is a manual decision from
 * here (use the existing Suspend Agent action in admin/agents.php).
 */
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/didit.php';

SessionManager::requireAdmin();

$db = Database::getInstance()->getConnection();

$rows = $db->query("
    SELECT u.id AS user_id, u.name AS registered_name, u.email, u.status AS account_status,
           ap.verification_status, ap.kyc_document_name, ap.kyc_name_mismatch,
           dv.decision_payload, dv.completed_at
    FROM agent_profiles ap
    JOIN users u ON u.id = ap.user_id
    JOIN didit_verifications dv ON dv.user_id = u.id AND dv.session_type = 'kyc'
    WHERE ap.verification_status IN ('approved', 'kyc_passed')
      AND ap.kyc_provider = 'didit'
      AND dv.decision_payload IS NOT NULL
    ORDER BY dv.completed_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$flagged = [];
$clean = [];
$unreadable = [];

foreach ($rows as $row) {
    $decision = json_decode($row['decision_payload'], true);
    if (!is_array($decision)) {
        $unreadable[] = $row;
        continue;
    }

    $documentName = DiditService::extractDocumentName($decision);
    if ($documentName === null) {
        $unreadable[] = $row;
        continue;
    }

    $matches = DiditService::namesLikelyMatch($row['registered_name'], $documentName);
    $row['document_name'] = $documentName;

    if (!$matches) {
        $flagged[] = $row;
    } else {
        $clean[] = $row;
    }
}

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<style>
.je-dash-shell { max-width: 100% !important; overflow-x: hidden !important; }
.ka-wrap { max-width: 1100px; }
.ka-card { background: #fff; border: 1px solid #E0E0E0; border-radius: 14px; padding: 24px; margin-bottom: 20px; }
.ka-card h1 { font-family: 'Prata', serif; font-size: 22px; color: #0A0A0A; margin: 0 0 8px; }
.ka-summary { display: flex; gap: 16px; margin: 16px 0 24px; flex-wrap: wrap; }
.ka-stat { flex: 1; min-width: 140px; background: #faf9f6; border-radius: 10px; padding: 16px; text-align: center; }
.ka-stat .num { font-size: 26px; font-weight: 700; }
.ka-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.ka-stat.danger .num { color: #C62828; }
.ka-row { padding: 16px 0; border-bottom: 1px solid #F0F0F0; }
.ka-row:last-child { border-bottom: none; }
.ka-row.flagged { background: #FFF5F5; margin: 0 -24px; padding: 16px 24px; }
.ka-name { font-weight: 700; font-size: 14px; }
.ka-meta { font-size: 12px; color: #717171; margin-top: 4px; }
.ka-compare { font-size: 13px; margin-top: 8px; }
.ka-compare .vs { color: #C62828; font-weight: 600; }
.ka-empty { text-align: center; padding: 24px; color: #999; font-size: 13px; }
.ka-action { display: inline-block; margin-top: 8px; font-size: 12px; color: #C6A43F; font-weight: 600; text-decoration: none; }
</style>

<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="ka-wrap">
        <div class="ka-card">
            <h1>KYC Identity Audit</h1>
            <p style="font-size:13px;color:#717171;">Retroactive check of already-approved agents against the ID document Didit actually scanned, using the same identity cross-check now applied to new verifications going forward.</p>

            <div class="ka-summary">
                <div class="ka-stat danger">
                    <div class="num"><?= count($flagged) ?></div>
                    <div class="label">Mismatches Found</div>
                </div>
                <div class="ka-stat">
                    <div class="num"><?= count($clean) ?></div>
                    <div class="label">Confirmed Matching</div>
                </div>
                <div class="ka-stat">
                    <div class="num"><?= count($unreadable) ?></div>
                    <div class="label">Couldn't Re-check (no name on file)</div>
                </div>
            </div>

            <?php if (!empty($flagged)): ?>
                <h3 style="color:#C62828;font-size:15px;">⚠ Needs Review</h3>
                <?php foreach ($flagged as $r): ?>
                <div class="ka-row flagged">
                    <div class="ka-name"><?= htmlspecialchars($r['registered_name']) ?> <span style="font-weight:400;color:#888;font-size:12px;">(<?= htmlspecialchars($r['email']) ?>)</span></div>
                    <div class="ka-compare">
                        Registered as <strong><?= htmlspecialchars($r['registered_name']) ?></strong>
                        <span class="vs">≠</span>
                        ID document reads <strong><?= htmlspecialchars($r['document_name']) ?></strong>
                    </div>
                    <div class="ka-meta">
                        Account status: <?= htmlspecialchars($r['account_status']) ?> · KYC approved <?= $r['completed_at'] ? date('M j, Y', strtotime($r['completed_at'])) : '—' ?>
                    </div>
                    <a class="ka-action" href="/admin/user-management.php?search=<?= urlencode($r['email']) ?>">Review this account →</a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ka-empty">No mismatches found among currently-approved agents.</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($unreadable)): ?>
        <div class="ka-card">
            <h3 style="font-size:15px;margin-top:0;">Couldn't Re-check (<?= count($unreadable) ?>)</h3>
            <p style="font-size:12px;color:#888;">Older sessions whose stored decision payload doesn't include an extractable document name (predates this data being saved, or Didit's response for that session omitted it). Not flagged as a mismatch — just unverifiable retroactively.</p>
            <?php foreach ($unreadable as $r): ?>
                <div class="ka-row"><?= htmlspecialchars($r['registered_name']) ?> — <?= htmlspecialchars($r['email']) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
