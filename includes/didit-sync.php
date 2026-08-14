<?php
/**
 * KINAS GROUP — Didit on-demand status sync (self-heal)
 *
 * The webhook (api/webhooks/didit.php) is the fast path, but if a
 * delivery is missed/rejected, local state would stay stale forever
 * and the verification page would show "unverified" for an agent who
 * IS verified on Didit.
 *
 * These functions fetch the authoritative decision from Didit and
 * apply the same state transitions the webhook applies.
 *
 * OPTION 1 KYC NAME RULE:
 * - Didit approved + registered name matches ID document name => approved/kyc_passed
 * - Didit approved + ID document name does not match => rejected
 * - Didit approved + ID document name cannot be read => review_needed
 *
 * BUSINESS RULES:
 * - KYC approved => users.verified = 1 for identity verification.
 * - Business + KYC approved + KYB not approved => verification_status 'kyc_passed'.
 * - KYB approved while status is 'kyc_passed' => promote to full 'approved'.
 */

require_once __DIR__ . '/didit.php';

function didit_sync_table_exists(PDO $db, string $table): bool
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $st->execute([$table]);
        $cache[$table] = ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function didit_sync_column_exists(PDO $db, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    try {
        $st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $st->execute([$table, $column]);
        $cache[$key] = ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Sync KYC identity state for one agent from Didit's decision API.
 *
 * Safe to call on every page load:
 * - no-ops when state is already final,
 * - no-ops when Didit is disabled,
 * - no-ops when no KYC session exists,
 * - never throws.
 */
function didit_sync_kyc(PDO $db, int $userId): void
{
    try {
        if (!didit_sync_table_exists($db, 'didit_verifications')) {
            return;
        }

        $st = $db->prepare("
            SELECT verification_status
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $st->execute([$userId]);
        $status = (string)($st->fetchColumn() ?: 'pending');

        /*
         * Final states are not touched by the sync.
         *
         * rejected is intentionally final here. If an agent was rejected
         * because of a name mismatch, they must start a fresh KYC attempt
         * from the verification page. Starting a new attempt resets the
         * local state to in_progress.
         */
        if (in_array($status, ['approved', 'rejected', 'expired', 'suspended'], true)) {
            return;
        }

        $sv = $db->prepare("
            SELECT session_id
            FROM didit_verifications
            WHERE user_id = ?
              AND session_type = 'kyc'
            ORDER BY id DESC
            LIMIT 1
        ");
        $sv->execute([$userId]);
        $sessionId = (string)($sv->fetchColumn() ?: '');

        if ($sessionId === '') {
            return;
        }

        $didit = new DiditService();

        if (!$didit->isEnabled()) {
            return;
        }

        $fetched = $didit->getDecision($sessionId);

        if (empty($fetched['success'])) {
            return;
        }

        $rawStatus = (string)($fetched['status'] ?? '');
        $mapped    = DiditService::mapStatus($rawStatus);
        $decision  = is_array($fetched['decision'] ?? null) ? $fetched['decision'] : [];
        $now       = date('Y-m-d H:i:s');

        /*
         * Non-final remote state: mirror it locally so the page shows progress.
         */
        if (!in_array($mapped, ['approved', 'rejected', 'expired'], true)) {
            try {
                $db->prepare("
                    UPDATE didit_verifications
                    SET status = ?,
                        didit_status = ?,
                        updated_at = NOW()
                    WHERE session_id = ?
                ")->execute([$mapped, $rawStatus, $sessionId]);
            } catch (Throwable $e) {
                error_log('didit_sync_kyc non-final didit_verifications update error: ' . $e->getMessage());
            }

            if (in_array($mapped, ['in_progress', 'review_needed'], true) && $mapped !== $status) {
                $db->prepare("
                    UPDATE agent_profiles
                    SET verification_status = ?
                    WHERE user_id = ?
                ")->execute([$mapped, $userId]);
            }

            return;
        }

        /*
         * OPTION 1 name-match enforcement.
         *
         * Only applies when Didit would otherwise approve KYC.
         */
        $documentName = null;
        $nameMatch    = null;
        $mismatch     = 0;
        $reason       = null;
        $registered   = '';

        if ($mapped === 'approved') {
            $ur = $db->prepare("
                SELECT name
                FROM users
                WHERE id = ?
            ");
            $ur->execute([$userId]);
            $registered = trim((string)($ur->fetchColumn() ?: ''));

            try {
                $documentName = DiditService::extractDocumentName($decision);
            } catch (Throwable $e) {
                $documentName = null;
            }

            if (is_string($documentName)) {
                $documentName = trim(preg_replace('/\s+/', ' ', $documentName));

                if ($documentName === '') {
                    $documentName = null;
                }
            }

            if ($registered === '') {
                $nameMatch = 'unreadable';
                $mapped    = 'review_needed';
                $reason    = 'Registered account name is missing. Manual review required.';
            } elseif ($documentName === null) {
                $nameMatch = 'unreadable';
                $mapped    = 'review_needed';
                $reason    = 'ID document name could not be read. Manual review required.';
            } else {
                $ok = false;

                try {
                    $ok = (bool)DiditService::namesLikelyMatch($registered, $documentName);
                } catch (Throwable $e) {
                    $ok = false;
                }

                if ($ok) {
                    $nameMatch = 'matched';
                } else {
                    $nameMatch = 'mismatched';
                    $mismatch  = 1;
                    $mapped    = 'rejected';
                    $reason    = 'ID document name does not correspond with the registered account name.';

                    error_log(
                        "Didit KYC sync name mismatch: user_id=$userId "
                        . "registered=\"$registered\" "
                        . "document=\"$documentName\" "
                        . "session=$sessionId"
                    );
                }
            }
        }

        $isDecision = in_array($mapped, ['approved', 'rejected'], true);
        $isTerminal = $isDecision || $mapped === 'expired';

        /*
         * Update didit_verifications row.
         */
        $decisionPayload = json_encode(
            $decision,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $sets = [
            'status = ?',
            'didit_status = ?',
            'last_event_id = COALESCE(last_event_id, ?)',
            'completed_at = COALESCE(completed_at, ?)',
            'updated_at = NOW()',
        ];

        $params = [
            $mapped,
            $rawStatus,
            'ondemand-sync',
            $isTerminal ? $now : null,
        ];

        if (didit_sync_column_exists($db, 'didit_verifications', 'decision_payload')) {
            $sets[] = 'decision_payload = ?';
            $params[] = $decisionPayload !== false ? $decisionPayload : null;
        }

        if (didit_sync_column_exists($db, 'didit_verifications', 'document_name')) {
            $sets[] = 'document_name = ?';
            $params[] = $documentName;
        }

        if (didit_sync_column_exists($db, 'didit_verifications', 'name_match') && $nameMatch !== null) {
            $sets[] = 'name_match = ?';
            $params[] = $nameMatch;
        }

        if (didit_sync_column_exists($db, 'didit_verifications', 'expected_name')) {
            $sets[] = 'expected_name = COALESCE(?, expected_name)';
            $params[] = $registered !== '' ? $registered : null;
        }

        $params[] = $sessionId;

        $db->prepare("
            UPDATE didit_verifications
            SET " . implode(', ', $sets) . "
            WHERE session_id = ?
        ")->execute($params);

        /*
         * Agent profile transition.
         */
        $pr = $db->prepare("
            SELECT company_name, kyb_status
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $pr->execute([$userId]);
        $p = $pr->fetch(PDO::FETCH_ASSOC) ?: [];

        $isBusiness  = trim((string)($p['company_name'] ?? '')) !== '';
        $kybApproved = (($p['kyb_status'] ?? '') === 'approved');

        $store = $mapped;

        if ($mapped === 'approved' && $isBusiness && !$kybApproved) {
            $store = 'kyc_passed';
        }

        $sets = [
            'verification_status = ?',
        ];

        $params = [
            $store,
        ];

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_decision_at')) {
            $sets[] = 'kyc_decision_at = CASE WHEN ? THEN ? ELSE kyc_decision_at END';
            $params[] = $isDecision ? 1 : 0;
            $params[] = $now;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_submitted_at')) {
            $sets[] = 'kyc_submitted_at = COALESCE(kyc_submitted_at, ?)';
            $params[] = $now;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_document_name')) {
            $sets[] = 'kyc_document_name = COALESCE(?, kyc_document_name)';
            $params[] = $documentName;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_name_match') && $nameMatch !== null) {
            $sets[] = 'kyc_name_match = ?';
            $params[] = $nameMatch;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_name_mismatch')) {
            $sets[] = 'kyc_name_mismatch = ?';
            $params[] = $mismatch;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_rejection_reason')) {
            $sets[] = 'kyc_rejection_reason = ?';
            $params[] = $reason;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_passed_at') && $mapped === 'approved') {
            $sets[] = 'kyc_passed_at = COALESCE(kyc_passed_at, ?)';
            $params[] = $now;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyc_provider')) {
            $sets[] = "kyc_provider = 'didit'";
        }

        $params[] = $userId;

        $db->prepare("
            UPDATE agent_profiles
            SET " . implode(', ', $sets) . "
            WHERE user_id = ?
        ")->execute($params);

        /*
         * User badge/state.
         *
         * KYC approved means the person's identity has been verified.
         * For business accounts, full account approval still requires KYB,
         * but the identity step itself is complete.
         */
        if ($mapped === 'approved') {
            $db->prepare("
                UPDATE users
                SET verified = 1,
                    status = 'active'
                WHERE id = ?
            ")->execute([$userId]);
        } elseif ($mapped === 'rejected') {
            $db->prepare("
                UPDATE users
                SET verified = 0
                WHERE id = ?
            ")->execute([$userId]);
        }

        /*
         * If KYB was already approved and identity just cleared,
         * promote to full approval.
         */
        if ($store === 'kyc_passed' && $kybApproved) {
            $db->prepare("
                UPDATE agent_profiles
                SET verification_status = 'approved'
                WHERE user_id = ?
            ")->execute([$userId]);

            $db->prepare("
                UPDATE users
                SET verified = 1,
                    status = 'active'
                WHERE id = ?
            ")->execute([$userId]);
        }

        if (class_exists('Security')) {
            Security::logActivity(
                $userId,
                'kyc_synced',
                "On-demand Didit KYC sync: $rawStatus -> $mapped"
            );
        }
    } catch (Throwable $e) {
        error_log('didit_sync_kyc error: ' . $e->getMessage());
    }
}

/**
 * Sync KYB business state for one agent from Didit's decision API.
 *
 * Same safety guarantees as didit_sync_kyc(). Never throws.
 */
function didit_sync_kyb(PDO $db, int $userId): void
{
    try {
        if (!didit_sync_table_exists($db, 'didit_verifications')) {
            return;
        }

        $st = $db->prepare("
            SELECT kyb_status
            FROM agent_profiles
            WHERE user_id = ?
        ");
        $st->execute([$userId]);
        $kyb = (string)($st->fetchColumn() ?: 'not_started');

        if (in_array($kyb, ['approved', 'rejected', 'expired'], true)) {
            return;
        }

        $sv = $db->prepare("
            SELECT session_id
            FROM didit_verifications
            WHERE user_id = ?
              AND session_type = 'kyb'
            ORDER BY id DESC
            LIMIT 1
        ");
        $sv->execute([$userId]);
        $sessionId = (string)($sv->fetchColumn() ?: '');

        if ($sessionId === '') {
            return;
        }

        $didit = new DiditService();

        if (!$didit->isEnabled()) {
            return;
        }

        $fetched = $didit->getDecision($sessionId);

        if (empty($fetched['success'])) {
            return;
        }

        $rawStatus = (string)($fetched['status'] ?? '');
        $mapped    = DiditService::mapStatus($rawStatus);
        $decision  = is_array($fetched['decision'] ?? null) ? $fetched['decision'] : [];
        $now       = date('Y-m-d H:i:s');

        /*
         * Non-final remote state: mirror it locally.
         */
        if (!in_array($mapped, ['approved', 'rejected', 'expired'], true)) {
            try {
                $db->prepare("
                    UPDATE didit_verifications
                    SET status = ?,
                        didit_status = ?,
                        updated_at = NOW()
                    WHERE session_id = ?
                ")->execute([$mapped, $rawStatus, $sessionId]);
            } catch (Throwable $e) {
                error_log('didit_sync_kyb non-final didit_verifications update error: ' . $e->getMessage());
            }

            if (in_array($mapped, ['in_progress', 'review_needed'], true) && $mapped !== $kyb) {
                $db->prepare("
                    UPDATE agent_profiles
                    SET kyb_status = ?
                    WHERE user_id = ?
                ")->execute([$mapped, $userId]);
            }

            return;
        }

        $isDecision = in_array($mapped, ['approved', 'rejected'], true);
        $isTerminal = $isDecision || $mapped === 'expired';

        $registrySnapshot = null;

        if (!empty($decision['kyb_registry'])) {
            $registrySnapshot = json_encode(
                $decision['kyb_registry'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }

        /*
         * Update agent_profiles KYB state.
         */
        $sets = [
            'kyb_status = ?',
        ];

        $params = [
            $mapped,
        ];

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyb_decision_at')) {
            $sets[] = 'kyb_decision_at = CASE WHEN ? THEN ? ELSE kyb_decision_at END';
            $params[] = $isDecision ? 1 : 0;
            $params[] = $now;
        }

        if (didit_sync_column_exists($db, 'agent_profiles', 'kyb_submitted_at')) {
            $sets[] = 'kyb_submitted_at = COALESCE(kyb_submitted_at, ?)';
            $params[] = $now;
        }

        if ($registrySnapshot !== null && didit_sync_column_exists($db, 'agent_profiles', 'kyb_registry_snapshot')) {
            $sets[] = 'kyb_registry_snapshot = COALESCE(?, kyb_registry_snapshot)';
            $params[] = $registrySnapshot;
        }

        $params[] = $userId;

        $db->prepare("
            UPDATE agent_profiles
            SET " . implode(', ', $sets) . "
            WHERE user_id = ?
        ")->execute($params);

        /*
         * Update didit_verifications row.
         */
        $decisionPayload = json_encode(
            $decision,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        $dvSets = [
            'status = ?',
            'didit_status = ?',
            'completed_at = COALESCE(completed_at, ?)',
            'updated_at = NOW()',
        ];

        $dvParams = [
            $mapped,
            $rawStatus,
            $isTerminal ? $now : null,
        ];

        if (didit_sync_column_exists($db, 'didit_verifications', 'decision_payload')) {
            $dvSets[] = 'decision_payload = ?';
            $dvParams[] = $decisionPayload !== false ? $decisionPayload : null;
        }

        $dvParams[] = $sessionId;

        $db->prepare("
            UPDATE didit_verifications
            SET " . implode(', ', $dvSets) . "
            WHERE session_id = ?
        ")->execute($dvParams);

        /*
         * KYB cleared while identity was already 'kyc_passed'
         * => full approval.
         */
        if ($mapped === 'approved') {
            $kr = $db->prepare("
                SELECT verification_status
                FROM agent_profiles
                WHERE user_id = ?
            ");
            $kr->execute([$userId]);

            if ((string)$kr->fetchColumn() === 'kyc_passed') {
                $db->prepare("
                    UPDATE agent_profiles
                    SET verification_status = 'approved'
                    WHERE user_id = ?
                ")->execute([$userId]);

                $db->prepare("
                    UPDATE users
                    SET verified = 1,
                        status = 'active'
                    WHERE id = ?
                ")->execute([$userId]);
            }
        }

        if (class_exists('Security')) {
            Security::logActivity(
                $userId,
                'kyb_synced',
                "On-demand Didit KYB sync: $rawStatus -> $mapped"
            );
        }
    } catch (Throwable $e) {
        error_log('didit_sync_kyb error: ' . $e->getMessage());
    }
}
