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
-- =====================================================================

ALTER TABLE solar_listings
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        COMMENT 'Auto-maintained by MySQL on every row update'
        AFTER created_at;
