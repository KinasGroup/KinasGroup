-- ============================================================
-- KINAS GROUP — Account patch (READY TO RUN)
-- ============================================================

-- Use the database first
USE kinas_group;

-- ── 1. Admin account update ──
UPDATE users
SET
    password          = '$2y$10$A4Ho5xnCOTDIU9tIOaJ83.rSg8cJAMMEBqz1ZOADn.GNbodrHy9ty',
    name              = 'Kinas Admin',
    phone             = '+2348107576042',
    role              = 'admin',
    division          = NULL,
    verified          = 1,
    status            = 'active',
    email_verified_at = NOW(),
    updated_at        = NOW()
WHERE email = 'admin@kinas-group.com';

-- ── 2. Super agent account ──
INSERT INTO users
    (name, email, password, phone, role, division, status, verified,
     email_verified_at, created_at, updated_at)
VALUES
    ('Kinas Listing Agent', 'listing@kinas-group.com',
     '$2y$10$OUJed.n918HYMI84/xmcI.3shUP3P4Eq.cKKtrh5vi8siTLgIfQdm',
     '+2348107576042', 'agent', 'automobile', 'active', 1,
     NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    password          = VALUES(password),
    role              = 'agent',
    division          = 'automobile',
    verified          = 1,
    status            = 'active',
    email_verified_at = NOW(),
    updated_at        = NOW();

-- ── 3. Agent profile for super agent ──
INSERT INTO agent_profiles
    (user_id, division, company_name, verification_status,
     kyc_passed_at, created_at, updated_at)
SELECT
    id, 'automobile', 'KINAS GROUP', 'approved', NOW(), NOW(), NOW()
FROM users
WHERE email = 'listing@kinas-group.com'
ON DUPLICATE KEY UPDATE
    verification_status = 'approved',
    kyc_passed_at       = NOW(),
    updated_at          = NOW();
