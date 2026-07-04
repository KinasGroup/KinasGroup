<?php
/**
 * KINAS GROUP — Agent Approvals (Live DB)
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../includes/notify.php';
SessionManager::requireAdmin();

$db  = Database::getInstance()->getConnection();
$msg = '';

// ── Handle approve / reject POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = ['type'=>'error','text'=>'Please refresh the page and try again.'];
    } else {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($uid && in_array($action, ['approve','reject','request_info'])) {
            if ($action === 'approve') {
                // Two-stage KYC: identity (MetaMap) + business (this approval covers both)
                $db->prepare("UPDATE users SET status='active', verified=1 WHERE id=?")->execute([$uid]);
                $db->prepare("UPDATE agent_profiles
                              SET verification_status='approved',
                                  business_doc_reviewed_by=?,
                                  business_doc_reviewed_at=NOW(),
                                  kyc_decision_at=NOW()
                              WHERE user_id=?")
                   ->execute([$_SESSION['user_id'], $uid]);
                $db->prepare("UPDATE business_documents
                              SET status='approved', reviewed_by=?, reviewed_at=NOW()
                              WHERE user_id=? AND status='pending'")
                   ->execute([$_SESSION['user_id'], $uid]);
                Security::logActivity($_SESSION['user_id'], 'agent_approved', "Agent user_id=$uid approved");
                $msg = ['type'=>'success','text'=>'Agent approved and activated. SMS notification sent.'];

                // SMS notify
                $stU = $db->prepare("SELECT name, phone, phone_verified_at FROM users WHERE id = ?");
                $stU->execute([$uid]);
                $u = $stU->fetch(PDO::FETCH_ASSOC);
                if ($u && !empty($u['phone']) && !empty($u['phone_verified_at']) && class_exists('Notify')) {
                    Notify::sms($u['phone'], "Congratulations {$u['name']}! KINAS GROUP has approved your account. You can now create listings.", 'KYC_DECISION');
                }
            } elseif ($action === 'reject') {
                $reason = trim($_POST['reason'] ?? 'Application did not meet requirements.');
                $db->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$uid]);
                $db->prepare("UPDATE agent_profiles
                              SET verification_status='rejected',
                                  business_doc_reviewed_by=?,
                                  business_doc_reviewed_at=NOW(),
                                  business_doc_notes=?,
                                  kyc_decision_at=NOW()
                              WHERE user_id=?")
                   ->execute([$_SESSION['user_id'], $reason, $uid]);
                $db->prepare("UPDATE business_documents
                              SET status='rejected', admin_notes=?, reviewed_by=?, reviewed_at=NOW()
                              WHERE user_id=? AND status='pending'")
                   ->execute([$reason, $_SESSION['user_id'], $uid]);
                Security::logActivity($_SESSION['user_id'], 'agent_rejected', "Agent user_id=$uid rejected: $reason");
                $msg = ['type'=>'error','text'=>'Agent rejected. SMS notification sent.'];

                $stU = $db->prepare("SELECT name, phone, phone_verified_at FROM users WHERE id = ?");
                $stU->execute([$uid]);
                $u = $stU->fetch(PDO::FETCH_ASSOC);
                if ($u && !empty($u['phone']) && !empty($u['phone_verified_at']) && class_exists('Notify')) {
                    Notify::sms($u['phone'], "Hi {$u['name']}, your KINAS GROUP verification was not approved. Reason: {$reason}. Please re-submit at kinas-group.com/agent/verification.php", 'KYC_DECISION');
                }
            } else {
                Security::logActivity($_SESSION['user_id'], 'agent_info_requested', "More info requested from user_id=$uid");
                $msg = ['type'=>'info','text'=>'Info request noted.'];
            }
        }
    }
}

// ── Stats ─────────────────────────────────────────────────────
$pendingCount   = (int)$db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status IN ('pending','phone_verified','kyc_passed','documents_submitted','in_progress','review_needed')")->fetchColumn();
$approvedMonth  = (int)$db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status='approved' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
$rejectedCount  = (int)$db->query("SELECT COUNT(*) FROM agent_profiles WHERE verification_status='rejected'")->fetchColumn();

// ── Fetch pending agents with KYC docs ───────────────────────
$agents = $db->query("
    SELECT u.id, u.name, u.email, u.phone, u.created_at,
           ap.division, ap.verification_status, ap.company_name, ap.license_number, ap.bio, ap.website, ap.kyc_submitted_at,
           ap.kyc_provider, ap.kyc_verification_id,
           ap.kyb_status, ap.kyb_verification_id, ap.kyb_registry_snapshot,
           mv.mati_status, mv.completed_at AS mv_completed,
           dv.didit_status, dv.completed_at AS dv_completed
    FROM users u
    JOIN agent_profiles ap ON ap.user_id = u.id
    LEFT JOIN metamap_verifications mv ON mv.user_id = u.id
    LEFT JOIN didit_verifications dv ON dv.user_id = u.id AND dv.session_type = 'kyc'
    WHERE ap.verification_status IN ('pending','phone_verified','kyc_passed','documents_submitted','in_progress','review_needed')
    ORDER BY COALESCE(ap.kyc_submitted_at, u.created_at) ASC
")->fetchAll();

$csrf = Security::generateCSRFToken();

$headerDepth = '../';
require_once __DIR__ . '/../templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Agent Approvals - KINAS GROUP Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/james-edition.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#F5F7FA}
        .admin-layout{display:flex;min-height:100vh}
        .admin-main{flex:1;padding:30px;background:#F5F7FA}
        .page-header{margin-bottom:28px}
        .page-header h1{font-family:'Prata',serif;font-size:28px;color:#0A0A0A;margin-bottom:6px}
        .page-header p{color:#666;font-size:14px}
        .flash{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-weight:500;font-size:14px}
        .flash.success{background:#E8F5E9;color:#2E7D32;border:1px solid #A7F3D0}
        .flash.error{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA}
        .flash.info{background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:28px}
        .stat-card{background:white;border-radius:14px;padding:20px;text-align:center;border:1.5px solid #C6A43F;transition:all .3s}
        .stat-card:hover{border-color:#C6A43F;box-shadow:0 8px 24px rgba(198,164,63,0.15);transform:translateY(-3px)}
        .stat-number{font-size:32px;font-weight:700;color:#C6A43F;font-family:'Prata',serif}
        .stat-label{color:#666;font-size:13px;margin-top:4px}
        .approval-card{background:white;border-radius:18px;border:1px solid #E0E0E0;overflow:hidden;margin-bottom:24px;transition:all .3s}
        .approval-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.08)}
        .card-header{padding:18px 24px;background:#F8F8F8;border-bottom:1px solid #E0E0E0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
        .card-header h3{font-family:'Prata',serif;font-size:18px;color:#0A0A0A}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;background:#FFF3E0;color:#F57C00}
        .card-body{padding:24px}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .info-section h4{font-size:13px;font-weight:600;color:#C6A43F;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px}
        .info-section p{margin-bottom:8px;font-size:13px;color:#333;display:flex;gap:8px}
        .info-section p strong{color:#0A0A0A;min-width:110px;flex-shrink:0}
        .doc-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:12px}
        .doc-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:1px solid #E0E0E0;border-radius:10px;background:#F8F8F8;font-size:12px;color:#333;transition:all .3s}
        .doc-chip i{color:#C6A43F}
        .doc-chip a{color:#C6A43F;text-decoration:none;margin-left:4px}
        .doc-chip.missing{color:#DC2626;border-color:#FECACA;background:#FEF2F2}
        .card-actions{padding:18px 24px;background:#F8F8F8;border-top:1px solid #E0E0E0;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .btn-approve{background:#2E7D32;color:white;border:none;padding:10px 22px;border-radius:8px;font-weight:600;cursor:pointer;transition:all .3s}
        .btn-approve:hover{background:#1B5E20;transform:translateY(-2px)}
        .btn-reject{background:#DC2626;color:white;border:none;padding:10px 22px;border-radius:8px;font-weight:600;cursor:pointer;transition:all .3s}
        .btn-reject:hover{background:#B91C1C;transform:translateY(-2px)}
        .btn-info{background:#555;color:white;border:none;padding:10px 22px;border-radius:8px;font-weight:600;cursor:pointer;transition:all .3s}
        .btn-info:hover{background:#444}
        .empty-state{text-align:center;padding:60px 20px;color:#999}
        .empty-state i{font-size:3rem;margin-bottom:16px;display:block;color:#E0E0E0}
        .empty-state h3{font-family:'Prata',serif;margin-bottom:8px;color:#333}
        @media(max-width:768px){.admin-main{padding:20px}.info-grid{grid-template-columns:1fr}.card-header{flex-direction:column;align-items:flex-start}}
    </style>

</head>
<body>
<?php include __DIR__ . '/../includes/partials/header.php'; ?>
<div class="je-dash-shell">
<?php include __DIR__ . "/../includes/partials/admin-sidebar.php"; ?>
<main class="je-dash-main">
    <div class="page-header">
        <h1><i class="fas fa-user-check" style="color:#C6A43F;margin-right:10px"></i>Agent Approval Queue</h1>
        <p>Review and approve agent KYC submissions before they go live</p>
    </div>

    <?php if ($msg): ?>
    <div class="flash <?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $pendingCount ?></div><div class="stat-label">Pending Review</div></div>
        <div class="stat-card"><div class="stat-number"><?= $approvedMonth ?></div><div class="stat-label">Approved This Month</div></div>
        <div class="stat-card"><div class="stat-number"><?= $rejectedCount ?></div><div class="stat-label">Rejected</div></div>
    </div>

    <?php if (empty($agents)): ?>
    <div class="approval-card">
        <div class="empty-state">
            <i class="fas fa-check-double"></i>
            <h3>All caught up!</h3>
            <p>No agents waiting for approval. New submissions will appear here automatically.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($agents as $agent):
        // Fetch business documents for this agent (the new KYC table)
        $docs = $db->prepare("SELECT * FROM business_documents WHERE user_id = ? ORDER BY id DESC");
        $docs->execute([$agent['id']]);
        $docs = $docs->fetchAll();

        // Fetch phone verification status
        $ph = $db->prepare("SELECT phone, phone_verified_at FROM users WHERE id = ?");
        $ph->execute([$agent['id']]);
        $ph = $ph->fetch(PDO::FETCH_ASSOC);
    ?>
    <div class="approval-card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="color:#C6A43F;margin-right:8px"></i><?= htmlspecialchars($agent['name']) ?></h3>
            <span class="status-badge"><i class="fas fa-clock"></i> <?= !empty($agent['kyc_submitted_at']) ? 'Submitted ' . date('M j, Y', strtotime($agent['kyc_submitted_at'])) : 'Applied ' . date('M j, Y', strtotime($agent['created_at'])) ?></span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-section">
                    <h4>Personal Information</h4>
                    <p><strong>Email:</strong> <?= htmlspecialchars($agent['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($agent['phone'] ?? '—') ?></p>
                    <p><strong>Division:</strong> <?= htmlspecialchars($agent['division'] ?? '—') ?></p>
                    <p><strong>License #:</strong> <?= htmlspecialchars($agent['license_number'] ?? '—') ?></p>
                </div>
                <div class="info-section">
                    <h4>Business Information</h4>
                    <p><strong>Company:</strong> <?= htmlspecialchars($agent['company_name'] ?? '—') ?></p>
                    <p><strong>Website:</strong> <?= htmlspecialchars($agent['website'] ?? '—') ?></p>
                    <?php if ($agent['bio']): ?>
                    <p><strong>Bio:</strong> <?= htmlspecialchars(substr($agent['bio'],0,120)) ?>...</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($agent['kyc_provider'])): ?>
            <div class="info-section" style="margin-top:20px">
                <h4>KYC Source</h4>
                <p><strong>Provider:</strong> <?= htmlspecialchars(ucfirst($agent['kyc_provider'])) ?></p>
                <?php if (!empty($agent['kyc_verification_id'])): ?>
                <p><strong>Verification ID:</strong> <code style="font-size:12px;"><?= htmlspecialchars($agent['kyc_verification_id']) ?></code></p>
                <?php endif; ?>
                <?php if (!empty($agent['mati_status'])): ?>
                <p><strong>MetaMap status:</strong> <?= htmlspecialchars($agent['mati_status']) ?></p>
                <?php endif; ?>
                <?php if (!empty($agent['didit_status'])): ?>
                <p><strong>Didit status:</strong> <?= htmlspecialchars($agent['didit_status']) ?></p>
                <?php endif; ?>
                <p class="doc-meta" style="font-size:12px; color:#888; margin-top:8px;">
                    KYC is handled by a third-party provider. KINAS GROUP does not store ID images.
                </p>
            </div>
            <?php endif; ?>
            <div class="info-section" style="margin-top:20px">
                <h4>Phone Verification</h4>
                <?php if (!empty($ph['phone_verified_at'])): ?>
                    <p style="color:#1B5E20;"><i class="fas fa-check-circle"></i> Verified <strong><?= htmlspecialchars($ph['phone'] ?? '') ?></strong> on <?= date('M j, Y', strtotime($ph['phone_verified_at'])) ?></p>
                <?php else: ?>
                    <p style="color:#BF360C;"><i class="fas fa-exclamation-circle"></i> Not verified — agent hasn't completed phone OTP</p>
                <?php endif; ?>
            </div>

            <div class="info-section" style="margin-top:20px">
                <h4>Identity (<?= htmlspecialchars(ucfirst($agent['kyc_provider'] ?? 'not started')) ?>)</h4>
                <?php if ($agent['kyc_provider'] === 'metamap' && !empty($agent['kyc_verification_id'])): ?>
                    <p><strong>Verification ID:</strong> <code style="font-size:12px;"><?= htmlspecialchars($agent['kyc_verification_id']) ?></code></p>
                    <?php if (!empty($agent['mati_status'])): ?>
                    <p><strong>MetaMap status:</strong> <?= htmlspecialchars($agent['mati_status']) ?></p>
                    <?php endif; ?>
                <?php elseif ($agent['kyc_provider'] === 'didit' && !empty($agent['kyc_verification_id'])): ?>
                    <p><strong>Session ID:</strong> <code style="font-size:12px;"><?= htmlspecialchars($agent['kyc_verification_id']) ?></code></p>
                    <?php if (!empty($agent['didit_status'])): ?>
                    <p><strong>Didit status:</strong> <?= htmlspecialchars($agent['didit_status']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color:#888;">Not started yet.</p>
                <?php endif; ?>
            </div>

            <div class="info-section" style="margin-top:20px">
                <h4>Business Verification (Didit KYB)</h4>
                <?php if (!empty($agent['kyb_verification_id'])): ?>
                    <p><strong>Session ID:</strong> <code style="font-size:12px;"><?= htmlspecialchars($agent['kyb_verification_id']) ?></code></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $agent['kyb_status'] ?? 'not_started'))) ?></p>
                    <?php
                        $registry = !empty($agent['kyb_registry_snapshot']) ? json_decode($agent['kyb_registry_snapshot'], true) : null;
                    ?>
                    <?php if ($registry): ?>
                        <p><strong>Registered name:</strong> <?= htmlspecialchars($registry['legal_name'] ?? '—') ?></p>
                        <p><strong>Registration #:</strong> <?= htmlspecialchars($registry['registration_number'] ?? '—') ?></p>
                        <?php if (!empty($registry['company_status'])): ?><p><strong>Registry status:</strong> <?= htmlspecialchars($registry['company_status']) ?></p><?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color:#888;">Not started — agent may still submit manual documents below instead.</p>
                <?php endif; ?>
            </div>

            <div class="info-section" style="margin-top:20px">
                <h4>Business Documents (manual upload)</h4>
                <?php if (!empty($agent['cac_number']) || !empty($agent['tin']) || !empty($agent['company_legal_name'])): ?>
                <p><strong>Legal name:</strong> <?= htmlspecialchars($agent['company_legal_name'] ?? '—') ?></p>
                <p><strong>CAC #:</strong> <?= htmlspecialchars($agent['cac_number'] ?? '—') ?></p>
                <p><strong>TIN:</strong> <?= htmlspecialchars($agent['tin'] ?? '—') ?></p>
                <?php endif; ?>

                <div class="doc-row" style="margin-top:10px;">
                    <?php if (empty($docs)): ?>
                    <div class="doc-chip missing"><i class="fas fa-exclamation-triangle"></i> No documents uploaded yet</div>
                    <?php else: ?>
                    <?php foreach ($docs as $doc):
                        $docLabel = ucwords(str_replace('_', ' ', $doc['document_type'] ?? 'Document'));
                        $isApproved = $doc['status'] === 'approved';
                        $isRejected = $doc['status'] === 'rejected';
                    ?>
                    <div class="doc-chip" style="flex-direction:column; align-items:flex-start; padding:12px 14px; gap:6px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-file-alt"></i>
                            <span style="font-weight:600;"><?= htmlspecialchars($docLabel) ?></span>
                            <?php if ($isApproved): ?><span style="color:#1B5E20; font-weight:700; font-size:10px;">✓ APPROVED</span>
                            <?php elseif ($isRejected): ?><span style="color:#B71C1C; font-weight:700; font-size:10px;">✗ REJECTED</span>
                            <?php else: ?><span style="color:#BF360C; font-weight:700; font-size:10px;">PENDING</span><?php endif; ?>
                        </div>
                        <div style="font-size:11px; color:#888;">
                            Uploaded <?= date('M j, Y g:i A', strtotime($doc['created_at'])) ?>
                        </div>
                        <div style="display:flex; gap:6px; margin-top:4px;">
                            <?php if (!empty($doc['document_url'])): ?>
                            <a href="<?= htmlspecialchars($doc['document_url']) ?>" target="_blank" rel="noopener" class="btn-approve" style="padding:5px 10px; font-size:11px; text-decoration:none;"><i class="fas fa-external-link-alt"></i> View</a>
                            <?php endif; ?>
                            <?php if ($doc['status'] === 'pending'): ?>
                            <form method="POST" action="api/admin/review-business-doc.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-approve" style="padding:5px 10px; font-size:11px;"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" action="api/admin/review-business-doc.php" style="display:inline;" data-kinas-confirm="Reject this document? This action will be logged." data-kinas-title="Reject Document" data-kinas-label="Reject" data-kinas-variant="warning" data-kinas-icon="fa-times-circle">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="notes" value="Document did not meet requirements">
                                <button type="submit" class="btn-reject" style="padding:5px 10px; font-size:11px;"><i class="fas fa-times"></i> Reject</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-actions">
            <form method="POST" style="display:contents">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="user_id" value="<?= $agent['id'] ?>">
                <button type="submit" name="action" value="approve" class="btn-approve" <?= empty($docs) ? 'disabled title="No business docs uploaded yet"' : '' ?>><i class="fas fa-check"></i> Approve Agent</button>
                <button type="submit" name="action" value="reject" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                <button type="submit" name="action" value="request_info" class="btn-info"><i class="fas fa-envelope"></i> Request Info</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

</main>
</div>

<?php require_once __DIR__ . "/../templates/footer.php"; ?>
