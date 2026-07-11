-- =====================================================================
-- 2026_07_10_fix_didit_kyc_kyb_schema.sql
-- ---------------------------------------------------------------------
-- The Didit KYC/KYB integration (api/agent/didit-*.php,
-- api/webhooks/didit.php) was fully coded against a schema that never
-- actually existed:
--   1. didit_verifications table was never created at all.
--   2. agent_profiles.verification_status ENUM didn't include
--      'in_progress' / 'review_needed' / 'expired' — values that
--      didit-kyc-start.php and the webhook handler write directly, so
--      starting or completing a KYC session would fail outright.
--   3. agent_profiles was missing the four kyb_* columns the KYB start
--      endpoint, status endpoint, and webhook handler all read/write.
-- =====================================================================

CREATE TABLE IF NOT EXISTS didit_verifications (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    user_id          INT NOT NULL,
    session_type     ENUM('kyc','kyb') NOT NULL,
    session_id       VARCHAR(100) NOT NULL,
    session_number   VARCHAR(50) NULL,
    workflow_id      VARCHAR(100) NULL,
    vendor_data      VARCHAR(100) NOT NULL COMMENT 'kyc:{user_id} or kyb:{user_id} — stable per user+type so ON DUPLICATE KEY UPDATE re-uses an in-flight session',
    status           VARCHAR(32) NOT NULL DEFAULT 'created' COMMENT 'internal mapped status: created/in_progress/review_needed/approved/rejected/expired',
    didit_status     VARCHAR(64) NULL COMMENT 'raw status string from Didit, e.g. "Not Started", "Approved"',
    decision_payload TEXT NULL COMMENT 'JSON snapshot of the full decision from Didit',
    last_event_id    VARCHAR(100) NULL COMMENT 'webhook event_id, for dedup on retries/fan-out',
    completed_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_vendor_data (vendor_data),
    UNIQUE KEY uniq_session_id (session_id),
    KEY idx_user_type (user_id, session_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Portable "add column if missing" / "widen enum if needed" helpers —
-- see database/migrations/2026_07_09_create_orders_and_order_items.sql
-- for why this pattern is used instead of "ADD COLUMN IF NOT EXISTS"
-- (requires MySQL 8.0.29+, which not every server here has).
DROP PROCEDURE IF EXISTS kinas_add_column_if_missing;

DELIMITER $$
CREATE PROCEDURE kinas_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_ddl VARCHAR(512)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_ddl);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL kinas_add_column_if_missing('agent_profiles', 'kyb_status', "kyb_status ENUM('not_started','in_progress','review_needed','approved','rejected','expired') NOT NULL DEFAULT 'not_started' AFTER kyc_passed_at");
CALL kinas_add_column_if_missing('agent_profiles', 'kyb_verification_id', 'kyb_verification_id VARCHAR(64) NULL COMMENT \'Didit KYB session id\' AFTER kyb_status');
CALL kinas_add_column_if_missing('agent_profiles', 'kyb_submitted_at', 'kyb_submitted_at TIMESTAMP NULL AFTER kyb_verification_id');
CALL kinas_add_column_if_missing('agent_profiles', 'kyb_decision_at', 'kyb_decision_at TIMESTAMP NULL AFTER kyb_submitted_at');
CALL kinas_add_column_if_missing('agent_profiles', 'kyb_registry_snapshot', 'kyb_registry_snapshot TEXT NULL AFTER kyb_decision_at');

DROP PROCEDURE IF EXISTS kinas_add_column_if_missing;

-- Widen verification_status to include the values the Didit integration
-- actually writes ('in_progress', 'review_needed', 'expired'). MySQL
-- doesn't support "ALTER ... MODIFY COLUMN IF DIFFERENT", so this just
-- re-issues the MODIFY unconditionally — safe to run more than once.
ALTER TABLE agent_profiles
    MODIFY COLUMN verification_status ENUM(
        'pending',             -- registered, no KYC yet
        'phone_verified',      -- phone OTP confirmed
        'in_progress',         -- Didit KYC session started, awaiting completion
        'kyc_passed',          -- Didit/MetaMap approved the person
        'review_needed',       -- Didit flagged for manual review
        'documents_submitted', -- CAC uploaded, awaiting admin
        'approved',            -- admin approved → can list
        'rejected',            -- admin rejected
        'expired',             -- previous session expired, needs restart
        'suspended'            -- post-approval suspension
    ) DEFAULT 'pending';
