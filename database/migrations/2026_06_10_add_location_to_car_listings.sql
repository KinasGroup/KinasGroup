-- =====================================================================
-- Migration: Add `location` column to car_listings
-- Date:      2026-06-10
-- Issue:     user/dashboard.php and user/saved-listings.php were joining
--            saved_listings → car_listings and selecting `cl.location`,
--            but car_listings only had city/state/country. This caused
--            a 500 on the user dashboard whenever a buyer with any
--            saved listing hit the page.
--
-- Fix:       Add a generated `location` column populated from
--            CONCAT_WS(', ', city, state) so existing reads keep working
--            without query changes. The property/marketplace queries
--            already alias their own location expressions.
--
-- Idempotent: re-running this migration is safe — the IF guard skips the
-- ALTER if `location` already exists.
-- =====================================================================

-- Bail out cleanly if the column is already present.
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'car_listings'
      AND COLUMN_NAME  = 'location'
);

-- Only run the ALTER when the column is missing.
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE car_listings ADD COLUMN location VARCHAR(255) GENERATED ALWAYS AS (TRIM(CONCAT_WS(0x2C20, city, state))) STORED',
    'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
