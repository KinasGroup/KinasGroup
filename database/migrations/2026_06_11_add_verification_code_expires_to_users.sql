-- =====================================================================
-- 2026_06_11_add_verification_code_expires_to_users.sql
-- ---------------------------------------------------------------------
-- Adds verification_code_expires to users. The verify-email flow now
-- rejects codes whose expiry has passed (the email body already promises
-- "this link will expire in 24 hours" — the server was never enforcing
-- it, which produced "Verification Failed — link is expired" right
-- after registration for users whose lookup query happened to fail for
-- other reasons).
-- =====================================================================

ALTER TABLE users
    ADD COLUMN verification_code_expires TIMESTAMP NULL
        COMMENT 'When verification_code becomes invalid (NULL = no expiry / already used)'
        AFTER verification_code;

-- Backfill: any existing verification_code that is still set is treated
-- as expired (better to force the user to re-request than to silently
-- accept a stale code). The /api/auth/resend-verification.php endpoint
-- will issue a fresh one with a proper 24h expiry.
UPDATE users
   SET verification_code_expires = '2000-01-01 00:00:00'
 WHERE verification_code IS NOT NULL
   AND email_verified_at IS NULL;
