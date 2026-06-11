-- =====================================================================
-- 2026_06_12_add_deleted_to_users_status_enum.sql
-- ---------------------------------------------------------------------
-- Adds 'deleted' to the users.status ENUM so admin/listing pages
-- can offer a soft "Delete" action (replaces the old hard Ban).
--
-- Why:
--   admin/user-management.php and admin/agent-management.php are
--   being updated to expose a Delete button alongside Suspend. Hard
--   DELETE FROM users is unsafe (FKs from car_listings, property_
--   listings, solar_listings, marketplace_listings, messages,
--   inquiries, saved_listings, transactions, activity_logs, etc.
--   all reference users.id). The pattern already used for listings
--   (api/admin/remove-listing.php -> UPDATE status='removed') is
--   to soft-delete via a status flag.
--
--   We extend the existing users.status ENUM the same way: add
--   'deleted' so any code that does WHERE status='active' (login,
--   dashboards, listings joins) automatically excludes deleted users.
--
-- Safe to re-run: MODIFY COLUMN ... ENUM(...) is idempotent when
-- the target ENUM is the same.
-- =====================================================================

ALTER TABLE users
    MODIFY COLUMN status ENUM('active','suspended','banned','pending','deleted')
        DEFAULT 'active'
        COMMENT 'deleted = soft-deleted by an admin; excluded from login + listings joins';
