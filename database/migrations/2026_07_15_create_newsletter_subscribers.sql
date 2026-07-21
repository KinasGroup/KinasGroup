-- =====================================================================
-- 2026_07_15_create_newsletter_subscribers.sql
-- ---------------------------------------------------------------------
-- Backs the public newsletter signup form (footer widget, api/newsletter/
-- subscribe.php + unsubscribe.php) and the admin newsletter composer
-- (admin/newsletter.php, api/admin/newsletter-send-batch.php).
--
-- This table was referenced by five live PHP files but had no CREATE
-- TABLE statement anywhere in the schema or migrations — on a fresh
-- database the entire newsletter feature (including the public signup
-- form) would fail immediately with "Table 'newsletter_subscribers'
-- doesn't exist". Reconstructed here from the exact columns those
-- files query/insert/update.
--
-- Run this BEFORE 2026_07_16_create_newsletter_campaigns.sql, which
-- assumes this table already exists.
-- =====================================================================

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    email              VARCHAR(255) NOT NULL,
    status             ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
    source             VARCHAR(100) NULL COMMENT 'Where the signup came from, e.g. footer widget',
    ip_address         VARCHAR(45) NULL,
    unsubscribe_token  VARCHAR(64) NOT NULL,
    subscribed_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at    TIMESTAMP NULL,
    UNIQUE KEY uniq_email (email),
    KEY idx_status_id (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
