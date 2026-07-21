-- =====================================================================
-- 2026_07_20_add_duplicate_account_detection.sql
-- ---------------------------------------------------------------------
-- Adds the columns needed to detect (not block — this flags for admin
-- review, since shared IPs/phones can be legitimate: family members,
-- shared offices, NAT'd mobile networks) accounts that share a phone
-- number or registration IP with an existing account. Promised in the
-- original proposal (Security & Verification: "duplicate account
-- detection") but had no supporting implementation anywhere.
-- =====================================================================

ALTER TABLE users
    ADD COLUMN registration_ip      VARCHAR(45)  NULL AFTER phone,
    ADD COLUMN duplicate_flag_reason VARCHAR(255) NULL AFTER registration_ip,
    ADD INDEX idx_registration_ip (registration_ip),
    ADD INDEX idx_phone (phone);
