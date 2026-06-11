-- =====================================================================
-- 2026_06_11_add_updated_at_to_solar_listings.sql
-- ---------------------------------------------------------------------
-- Adds updated_at to solar_listings.
--
-- Why:
--   The other three listing tables (car_listings, property_listings,
--   marketplace_listings) all have:
--       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
--                  ON UPDATE CURRENT_TIMESTAMP
--   but solar_listings was missing it. admin/flagged-listings.php
--   unions flagged rows across all four tables and sorts/groups by
--   updated_at, so the moment you had a flagged solar listing the
--   page crashed with:
--       Unknown column 's.updated_at' in 'field list'
--   and the "flagged this week" stats card blew up too.
--
-- This brings solar_listings in line with its siblings. The
-- ON UPDATE clause means MySQL auto-maintains it whenever a row
-- changes, so existing code that updates other fields gets the
-- right value "for free".
--
-- Safe to re-run: guards the ALTER with an INFORMATION_SCHEMA
-- check. (MySQL 8 does NOT support `ADD COLUMN IF NOT EXISTS` —
--  only MariaDB does — so we use the portable pattern below.)
-- =====================================================================

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'solar_listings'
       AND COLUMN_NAME  = 'updated_at'
);
SET @stmt := IF(@col = 0,
    'ALTER TABLE solar_listings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT ''Auto-maintained by MySQL on every row update'' AFTER created_at',
    'SELECT 1'
);
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
