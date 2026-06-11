-- =====================================================================
-- 2026_06_11_add_kyc_timestamps_to_agent_profiles.sql
-- ---------------------------------------------------------------------
-- Adds kyc_submitted_at and kyc_decision_at to agent_profiles.
--
-- Why:
--   admin/agent-approvals.php, agent/verification.php,
--   api/agent/kyc-status.php and api/webhooks/metamap.php all
--   read / write these columns, but they were never in
--   fresh_schema.sql. On any database that ran the older
--   schema, the agent approval page blew up with:
--       Unknown column 'ap.kyc_submitted_at' in 'field list'
--   and the webhook silently failed when it tried to UPDATE
--   them.
--
-- Safe to re-run: uses the IF NOT EXISTS pattern via
-- INFORMATION_SCHEMA so MySQL skips columns that are already
-- present.
-- =====================================================================

ALTER TABLE agent_profiles
    ADD COLUMN IF NOT EXISTS kyc_submitted_at TIMESTAMP NULL
        COMMENT 'When the agent submitted their KYC application (MetaMap + business docs)'
        AFTER kyc_verification_id,
    ADD COLUMN IF NOT EXISTS kyc_decision_at  TIMESTAMP NULL
        COMMENT 'When the admin (or MetaMap auto-decision) made the final approve/reject call'
        AFTER kyc_submitted_at;

-- Backfill: if an agent is already past the 'kyc_passed' / 'documents_submitted'
-- / 'approved' / 'rejected' / 'suspended' stages, treat kyc_submitted_at as
-- their profile.created_at (best available signal — we don't know the exact
-- moment they hit "submit"). For the decision timestamp, fall back to
-- business_doc_reviewed_at (the admin's "approved/rejected" action) or the
-- profile updated_at. This avoids a NULL sea in the admin queue.
UPDATE agent_profiles
   SET kyc_submitted_at = COALESCE(kyc_submitted_at, created_at)
 WHERE verification_status IN ('phone_verified','kyc_passed','documents_submitted','approved','rejected','suspended')
   AND kyc_submitted_at IS NULL;

UPDATE agent_profiles
   SET kyc_decision_at = COALESCE(kyc_decision_at, business_doc_reviewed_at, updated_at)
 WHERE verification_status IN ('approved','rejected','suspended')
   AND kyc_decision_at IS NULL;
