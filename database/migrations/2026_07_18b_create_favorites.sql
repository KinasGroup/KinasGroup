-- =====================================================================
-- 2026_07_18b_create_favorites.sql
-- ---------------------------------------------------------------------
-- Backs the save/unsave heart icon on every listing card sitewide
-- (templates/listing-card.php), api/listings/favorite.php, and the
-- dedicated user/saved-listings.php page. Referenced across 6 files but
-- never had a CREATE TABLE. Note: the schema's existing saved_listings
-- table is a separate, effectively-unused table (only ever read by two
-- dashboard stat counters, never written to) — those counters were
-- pointed at this table instead of being duplicated further; see
-- user/profile.php.
-- =====================================================================

CREATE TABLE IF NOT EXISTS favorites (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    user_id       INT NOT NULL,
    listing_id    INT NOT NULL,
    listing_type  ENUM('car','property','solar','marketplace') NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_listing (user_id, listing_id, listing_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
