-- Adds a unique public username (handle) to every account.
-- Legal name (`name`) stays private for KYC; `username` is the public identity.
-- Requires MySQL 8.0.4+ (REGEXP_REPLACE). Run BEFORE deploying the PHP changes.

ALTER TABLE users
  ADD COLUMN username VARCHAR(30) NULL AFTER email;

-- Backfill existing accounts with a unique generated handle:
-- sanitized first word of the legal name (max 20 chars) + user id  →  e.g. "john42"
UPDATE users
SET username = LOWER(CONCAT(
  LEFT(
    COALESCE(
      NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(TRIM(name), ' ', 1), '[^A-Za-z0-9]', ''), ''),
      'user'
    ),
    20
  ),
  id
))
WHERE username IS NULL OR username = '';

-- Enforce global uniqueness (default utf8 collations are case-insensitive,
-- so "John" and "john" collide correctly)
ALTER TABLE users
  ADD UNIQUE KEY uniq_users_username (username);
