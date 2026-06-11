-- =====================================================================
-- 2026_06_11_add_is_super_agent_to_agent_profiles.sql
-- ---------------------------------------------------------------------
-- Adds the is_super_agent flag to agent_profiles. The seed
-- listing@kinas-group.com is marked as a super agent (can list across
-- all 4 divisions). All other agents default to 0 (restricted to the
-- division they chose at registration). Enforced server-side in
-- api/listings/create.php.
-- =====================================================================

ALTER TABLE agent_profiles
    ADD COLUMN is_super_agent TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = can list across all 4 divisions; 0 = locked to their own division'
        AFTER verification_status;

-- Promote the seeded super agent (idempotent — looks up by email so it
-- is safe to re-run after the seed accounts are inserted).
UPDATE agent_profiles ap
JOIN users u ON u.id = ap.user_id
SET ap.is_super_agent = 1
WHERE u.email = 'listing@kinas-group.com'
  AND ap.is_super_agent = 0;
