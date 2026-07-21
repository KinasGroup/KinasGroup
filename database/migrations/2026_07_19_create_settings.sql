-- =====================================================================
-- 2026_07_19_create_settings.sql
-- ---------------------------------------------------------------------
-- Simple key-value settings store. Currently only backs
-- admin/settings.php's social media links form. Both the read and
-- write side were already wrapped defensively (try/catch, "table might
-- not exist yet"), so this wasn't fatal — but the feature silently
-- failed every time an admin tried to save.
--
-- NOTE (flagged, not fixed here — a product decision, not a bug fix):
-- the live site footer (includes/je-components.php) does NOT read
-- from this table. It reads social links from a hardcoded
-- DIVISION_SOCIAL_MEDIA PHP constant instead. So even with this table
-- in place, saving links via this admin form will not change what
-- visitors see in the footer — the two were never wired together.
-- =====================================================================

CREATE TABLE IF NOT EXISTS settings (
    setting_key    VARCHAR(100) PRIMARY KEY,
    setting_value  TEXT NULL,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
