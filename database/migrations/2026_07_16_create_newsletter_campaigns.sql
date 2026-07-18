-- =====================================================================
-- 2026_07_16_create_newsletter_campaigns.sql
-- ---------------------------------------------------------------------
-- Backs the new admin/newsletter.php composer: lets an admin write a
-- newsletter issue and send it to every active newsletter_subscribers
-- row. last_subscriber_id is a resume cursor — sending happens in
-- small batches (browser-driven, see api/admin/newsletter-send-batch.php)
-- so one very large list doesn't hit a PHP execution time limit; the
-- cursor lets a batch call pick up exactly where the last one left off
-- without re-scanning or double-sending.
-- =====================================================================

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    subject            VARCHAR(255) NOT NULL,
    body_html          MEDIUMTEXT NOT NULL,
    status             ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
    total_recipients   INT NOT NULL DEFAULT 0,
    sent_count         INT NOT NULL DEFAULT 0,
    failed_count       INT NOT NULL DEFAULT 0,
    last_subscriber_id INT NOT NULL DEFAULT 0,
    created_by         INT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at            TIMESTAMP NULL,
    KEY idx_campaign_status (status),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
