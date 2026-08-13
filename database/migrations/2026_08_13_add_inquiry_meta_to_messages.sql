-- ============================================================
-- 2026_08_13_add_inquiry_meta_to_messages.sql
-- Messaging revamp: structured inquiry metadata (Gmail-style
-- formal header on first message) + wider media_url for
-- multi-image JSON arrays.
-- Run once against the live database.
-- ============================================================

ALTER TABLE `messages`
  ADD COLUMN `inquiry_meta` TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `body`;

ALTER TABLE `messages`
  MODIFY COLUMN `media_url` VARCHAR(1000) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
