-- ============================================================
-- 2026_08_15_messaging_attachments_and_agent_inquiry.sql
--
-- Messaging upgrade:
-- 1. Adds support for video/document message types.
-- 2. Adds attachment metadata fields for uploaded files.
--
-- Run once against the live database.
-- ============================================================

ALTER TABLE `messages`
MODIFY COLUMN `message_type`
ENUM('text','image','audio','video','document')
NOT NULL
DEFAULT 'text';

ALTER TABLE `messages`
ADD COLUMN `media_name` VARCHAR(255) NULL DEFAULT NULL AFTER `media_duration_sec`,
ADD COLUMN `media_mime` VARCHAR(120) NULL DEFAULT NULL AFTER `media_name`,
ADD COLUMN `media_size` INT UNSIGNED NULL DEFAULT NULL AFTER `media_mime`;
