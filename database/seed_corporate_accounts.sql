-- =====================================================================
-- KINAS GROUP — Corporate accounts seed
-- =====================================================================
-- Run AFTER fresh_schema.sql. Creates the company's own super accounts
-- so you don't have to manually verify through MetaMap/Termii:
--
--   SUPER ADMIN  →  admin@kinasgroup.com          / SuperAdmin@2026
--   SUPER AGENT  →  listings@kinasgroup.com       / SuperAgent@2026
--
-- The super agent is pre-verified (verification_status = 'approved',
-- phone_verified_at set, MetaMap bypassed, business docs marked as
-- "internal") and can post listings in ALL 4 divisions.
--
-- This script is idempotent: re-running it just updates the records
-- rather than creating duplicates. Safe to run multiple times.
-- =====================================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────
-- 1. SUPER ADMIN
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO users (
    name, email, password, phone, role, status,
    verified, email_verified_at, phone_verified_at,
    division
) VALUES (
    'KINAS Group Admin',
    'admin@kinasgroup.com',
    '$2b$10$ITrpEblrSEZcNbGrdAiFUelxuC5mIt1yUf4PdnhUxYPg4.Vrvm/Lm',
    '+2348000000000',
    'admin', 'active',
    1, NOW(), NOW(),
    NULL
) ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    phone_verified_at = COALESCE(phone_verified_at, NOW()),
    email_verified_at = COALESCE(email_verified_at, NOW()),
    verified = 1,
    status = 'active';

-- ─────────────────────────────────────────────────────────────────────
-- 2. SUPER AGENT (multi-division, can list on all 4)
-- ─────────────────────────────────────────────────────────────────────
-- Note: users.division is an ENUM, so we use 'marketplace' as the
-- default but agent_profiles.division='all' is the one that matters
-- for multi-division access. The SessionManager/listing-create gate
-- checks agent_profiles.verification_status, not the division ENUM.
INSERT INTO users (
    name, email, password, phone, role, status,
    verified, email_verified_at, phone_verified_at,
    division
) VALUES (
    'KINAS Group Listings',
    'listings@kinasgroup.com',
    '$2b$10$hgZkT4TLoEFmqHVIgJMM8O08nonF9tmuCQtuMiP8zPZgHbi9sE.PK',
    '+2348000000001',
    'agent', 'active',
    1, NOW(), NOW(),
    'marketplace'
) ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    phone_verified_at = COALESCE(phone_verified_at, NOW()),
    email_verified_at = COALESCE(email_verified_at, NOW()),
    verified = 1,
    status = 'active';

-- Pull the user ids we just created / updated
SET @admin_id = (SELECT id FROM users WHERE email = 'admin@kinasgroup.com');
SET @agent_id = (SELECT id FROM users WHERE email = 'listings@kinasgroup.com');

-- ─────────────────────────────────────────────────────────────────────
-- 3. AGENT PROFILE — pre-approved (no KYC flow needed for this account)
-- ─────────────────────────────────────────────────────────────────────
INSERT INTO agent_profiles (
    user_id, division, bio,
    company_name, company_legal_name,
    cac_number, tin,
    verification_status, kyc_provider, kyc_passed_at,
    business_doc_reviewed_by, business_doc_reviewed_at, business_doc_notes
) VALUES (
    @agent_id, 'all',
    'Official KINAS Group corporate listings account. Verified internal account with authority to publish listings on all four divisions.',
    'KINAS GROUP',
    'KINAS Group International Limited',
    'RC-0000000',
    '20000000-0001',
    'approved', 'internal', NOW(),
    @admin_id, NOW(),
    'Internal corporate account — auto-approved by SuperAdmin. No KYC required.'
) ON DUPLICATE KEY UPDATE
    verification_status = 'approved',
    kyc_provider = 'internal',
    kyc_passed_at = COALESCE(kyc_passed_at, NOW()),
    business_doc_reviewed_by = @admin_id,
    business_doc_reviewed_at = NOW(),
    business_doc_notes = 'Internal corporate account — auto-approved by SuperAdmin.',
    company_legal_name = 'KINAS Group International Limited',
    cac_number = 'RC-0000000',
    division = 'all';

COMMIT;

-- =====================================================================
-- 4. SAMPLE LISTINGS (one featured per division) so the front page
--    isn't empty on first load. You can delete these after seeding.
-- =====================================================================

-- ── KINAS AUTOMOBILE: 2024 Rolls-Royce Spectre ──
INSERT INTO car_listings (
    agent_id, title, brand, model, year,
    price, mileage, fuel_type, transmission, color, body_type, drivetrain,
    description, city, state, country,
    status, featured, views
) VALUES (
    @agent_id,
    '2024 Rolls-Royce Spectre — First All-Electric Roller',
    'Rolls-Royce', 'Spectre', 2024,
    450000000, 1200, 'electric', 'automatic', 'Tempest Grey', 'coupe', 'AWD',
    'The first fully electric Rolls-Royce. 585 hp, 329 mi range, 0-60 in 4.4 s. Bespoke interior in Selby Grey & Seashell. Includes 4-year service plan and bespoke delivery experience.',
    'Lagos', 'Lagos', 'Nigeria',
    'active', 1, 47
);

-- ── WILLIAMS CONNECT HOME: 6-bedroom Ikoyi mansion ──
INSERT INTO property_listings (
    agent_id, title, property_type, listing_type,
    price, beds, baths, sqft,
    description, city, state, country,
    status, featured, views
) VALUES (
    @agent_id,
    '6-Bedroom Waterfront Mansion with Private Jetty — Old Ikoyi',
    'mansion', 'sale',
    2500000000, 6, 7, 12500,
    'Three-storey waterfront mansion on 1.2 acres. Features: private jetty, infinity pool, 8-car garage, cinema, gym, wine cellar, smart-home automation, full back-up power, 24/7 gated estate security.',
    'Lagos', 'Lagos', 'Nigeria',
    'active', 1, 89
);

-- ── KINAS VOLT: 15kW Commercial solar install ──
INSERT INTO solar_listings (
    agent_id, title, service_type, brand, capacity_kw,
    price, warranty_years,
    description, city, state, country,
    status, views
) VALUES (
    @agent_id,
    '15kW Commercial Solar System — Tier-1 JA Solar Panels + Huawei Inverter',
    'commercial', 'JA Solar + Huawei', 15.0,
    12500000, 25,
    'Complete turnkey 15kW commercial installation. Includes 30 × 550W JA Solar panels, Huawei SUN2000-15KTL inverter, smart monitoring, 25-year panel warranty, 10-year inverter warranty. Federal tax credit eligible.',
    'Abuja', 'FCT', 'Nigeria',
    'active', 31
);

-- -- KINAS MARKETPLACE: Rolex Daytona
INSERT INTO marketplace_listings (
    agent_id, title, category_id, brand, condition_status, price,
    description, city, state, country,
    status, featured, views
) VALUES (
    @agent_id,
    'Rolex Cosmograph Daytona — 2023 Unworn, Box & Papers',
    1,  -- Watches & Timepieces
    'Rolex', 'new',
    18500000,
    'Brand new 2023 Rolex Daytona 116500LN. Full set: box, papers, warranty card, all original tags. White gold bezel, Cerachrom insert, Oysterflex bracelet. KINAS-verified authentic with lifetime authenticity guarantee.',
    'Lagos', 'Lagos', 'Nigeria',
    'active', 1, 64
);

-- =====================================================================
-- 5. Welcome banner in activity log
-- =====================================================================
INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
VALUES
    (@admin_id, 'system_init', 'Corporate admin account seeded via seed_corporate_accounts.sql', '127.0.0.1', NOW()),
    (@agent_id, 'system_init', 'Corporate agent account seeded with full KYC bypass via seed_corporate_accounts.sql', '127.0.0.1', NOW());

-- =====================================================================
-- Summary
-- =====================================================================
SELECT
    'Super Admin' AS account,
    'admin@kinasgroup.com'   AS email,
    'SuperAdmin@2026'        AS password,
    'login at /auth/login.php' AS how_to_use;

SELECT
    'Super Agent (all divisions)' AS account,
    'listings@kinasgroup.com'     AS email,
    'SuperAgent@2026'             AS password,
    'login → go straight to /divisions/*/create.php' AS how_to_use
UNION ALL SELECT
    'Note', '', '',
    'CHANGE THESE PASSWORDS IMMEDIATELY after first login via the admin user-edit page.';
