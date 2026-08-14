-- =====================================================================
-- 2026_08_14_add_kyc_name_match_columns.sql
--
-- Adds the name-match enforcement columns that the Didit KYC
-- integration now reads/writes.
--
-- Why:
--   The Option 1 KYC name-match rule requires us to store:
--     - the name read from the ID document,
--     - the registered account name we compared against,
--     - the match result (matched / mismatched / unreadable),
--     - the rejection reason (when mismatched or unreadable),
--     - the KYC provider + pass timestamp for audit.
--
--   Without these columns the verification page cannot show the
--   rejection reason, the status endpoint returns null nameMatch,
--   and the webhook silently drops the name-match data.
--
-- Safe to re-run: uses the same portable "add column if missing"
-- pattern already used in 2026_07_10_fix_didit_kyc_kyb_schema.sql.
-- =====================================================================

-- ─────────────────────────────────────────────────────────────
-- Helper procedure (re-create so this migration is standalone)
-- ─────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS kinas_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE kinas_add_column_if_missing(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_ddl    VARCHAR(1024)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ─────────────────────────────────────────────────────────────
-- didit_verifications: name-match audit columns
-- ─────────────────────────────────────────────────────────────
CALL kinas_add_column_if_missing(
    'didit_verifications',
    'expected_name',
    "VARCHAR(255) NULL COMMENT 'Registered account name at the time the session was created' AFTER vendor_data"
);

CALL kinas_add_column_if_missing(
    'didit_verifications',
    'document_name',
    "VARCHAR(255) NULL COMMENT 'Name read from the scanned ID document by Didit' AFTER expected_name"
);

CALL kinas_add_column_if_missing(
    'didit_verifications',
    'name_match',
    "ENUM('not_checked','matched','mismatched','unreadable') NULL DEFAULT 'not_checked' COMMENT 'Result of registered-name vs document-name comparison' AFTER document_name"
);

-- ─────────────────────────────────────────────────────────────
-- agent_profiles: KYC name-match + provider + pass timestamp
-- ─────────────────────────────────────────────────────────────
CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_provider',
    "VARCHAR(32) NULL DEFAULT NULL COMMENT 'didit | metamap | manual — which provider cleared the identity step' AFTER kyc_verification_id"
);

CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_passed_at',
    "TIMESTAMP NULL COMMENT 'When the identity step was finally cleared (KYC approved)' AFTER kyc_decision_at"
);

CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_document_name',
    "VARCHAR(255) NULL COMMENT 'Name read from the ID document (mirrored from didit_verifications.document_name)' AFTER kyc_passed_at"
);

CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_name_match',
    "ENUM('not_checked','matched','mismatched','unreadable') NOT NULL DEFAULT 'not_checked' COMMENT 'Result of registered-name vs document-name comparison' AFTER kyc_document_name"
);

CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_name_mismatch',
    "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 when the document name did not match the registered name' AFTER kyc_name_match"
);

CALL kinas_add_column_if_missing(
    'agent_profiles',
    'kyc_rejection_reason',
    "VARCHAR(500) NULL COMMENT 'Human-readable reason when KYC was rejected or held for review' AFTER kyc_name_mismatch"
);

-- ─────────────────────────────────────────────────────────────
-- Backfill: if an agent is already approved / kyc_passed, mark
-- the name-match column as 'matched' so the UI does not show a
-- confusing "not_checked" state for already-verified agents.
-- ─────────────────────────────────────────────────────────────
UPDATE agent_profiles
SET kyc_name_match = 'matched'
WHERE kyc_name_match = 'not_checked'
  AND verification_status IN ('approved', 'kyc_passed', 'documents_submitted');

-- ─────────────────────────────────────────────────────────────
-- Cleanup
-- ─────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS kinas_add_column_if_missing;
