-- ============================================================
-- KINAS GROUP — Complete Database Patch
-- Run this ONCE against your Railway MySQL database.
-- Safe to run on both a fresh install and an existing DB.
-- ============================================================

USE railway;

-- ============================================================
-- PATCH 1: Fix users.status ENUM — add 'pending'
-- (Bug 1: register.php inserts status='pending' but ENUM had none)
-- ============================================================
ALTER TABLE users 
  MODIFY COLUMN status ENUM('active', 'suspended', 'banned', 'pending') DEFAULT 'pending';

-- ============================================================
-- PATCH 2: Fix users.division ENUM — use real division slugs
-- (Bug 2: constants.php uses slug keys, not old short names)
-- ============================================================
ALTER TABLE users 
  MODIFY COLUMN division ENUM(
    'kinas-automobile',
    'williams-connect-home',
    'kinas-volt',
    'kinas-marketplace'
  );

-- ============================================================
-- PATCH 3: Create sessions table if missing, then add columns
-- (Bug 3: login.php inserts ip_address and user_agent; the table
--  itself is not in fresh_schema.sql on older deploys, so login
--  fails on Railway with Table 'kinas_group.sessions' doesn't exist)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    token       VARCHAR(128) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token (token),
    INDEX idx_user_id    (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE sessions
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL,
  ADD COLUMN IF NOT EXISTS user_agent TEXT NULL;

-- ============================================================
-- PATCH 4: Create login_attempts table
-- (Bug 4: includes/auth.php queries this table)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    ip_address   VARCHAR(45) NOT NULL,
    email        VARCHAR(255) NOT NULL,
    success      TINYINT(1) NOT NULL DEFAULT 0,
    attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_email   (email)
);

-- ============================================================
-- PATCH 5: Create agent_profiles table
-- (Bug 7: register.php tries to insert into this table)
-- ============================================================
CREATE TABLE IF NOT EXISTS agent_profiles (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL UNIQUE,
    division            VARCHAR(50) NOT NULL,
    verification_status ENUM('pending', 'approved', 'rejected', 'suspended') DEFAULT 'pending',
    bio                 TEXT,
    company_name        VARCHAR(255),
    license_number      VARCHAR(100),
    website             VARCHAR(500),
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_division (division),
    INDEX idx_status   (verification_status)
);

-- ============================================================
-- PATCH 6: Re-create marketplace tables in correct FK order
-- (Bug 6: marketplace_listings FK references categories before it exists)
-- Only runs CREATE TABLE IF NOT EXISTS — safe on fresh or existing DB.
-- ============================================================
CREATE TABLE IF NOT EXISTS marketplace_categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image       VARCHAR(500),
    parent_id   INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES marketplace_categories(id)
);

CREATE TABLE IF NOT EXISTS marketplace_listings (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    agent_id         INT NOT NULL,
    title            VARCHAR(255) NOT NULL,
    category_id      INT,
    price            DECIMAL(15,2) NOT NULL,
    description      TEXT,
    condition_status VARCHAR(50),
    status           ENUM('active', 'sold', 'pending', 'flagged', 'removed') DEFAULT 'active',
    featured         BOOLEAN DEFAULT FALSE,
    views            INT DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id)    REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES marketplace_categories(id)
);

-- Insert default marketplace categories if not already present
INSERT IGNORE INTO marketplace_categories (name, slug, description) VALUES
  ('Electronics',    'electronics',   'Phones, laptops, gadgets and accessories'),
  ('Fashion',        'fashion',       'Clothing, shoes and fashion accessories'),
  ('Home & Garden',  'home-garden',   'Furniture, decor and garden supplies'),
  ('Sports',         'sports',        'Sports equipment and outdoor gear'),
  ('Collectibles',   'collectibles',  'Rare items, antiques and collectibles'),
  ('Art',            'art',           'Paintings, sculptures and art pieces'),
  ('Jewelry',        'jewelry',       'Jewelry and watches'),
  ('Vehicles',       'vehicles',      'Cars, motorcycles and other vehicles'),
  ('Other',          'other',         'Miscellaneous items');

-- ============================================================
-- PATCH 7: Security migrations (from migrations.sql — idempotent)
-- ============================================================

-- OTP expiry tracking
ALTER TABLE otp_codes
  ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NOT NULL DEFAULT (DATE_ADD(NOW(), INTERVAL 10 MINUTE)),
  ADD COLUMN IF NOT EXISTS user_id    INT NULL,
  ADD COLUMN IF NOT EXISTS verified   BOOLEAN NOT NULL DEFAULT FALSE,
  ADD INDEX  IF NOT EXISTS idx_expires (expires_at);

-- DB-backed rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id           INT          PRIMARY KEY AUTO_INCREMENT,
    rate_key     VARCHAR(255) NOT NULL,
    attempts     INT          NOT NULL DEFAULT 1,
    window_start TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key_window (rate_key, window_start)
);

-- Additional user columns for security
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS reset_token           VARCHAR(128) NULL,
  ADD COLUMN IF NOT EXISTS reset_token_expiry    TIMESTAMP    NULL,
  ADD COLUMN IF NOT EXISTS reset_token_used      BOOLEAN      NOT NULL DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS phone_verified        BOOLEAN      NOT NULL DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS email_verified_at     TIMESTAMP    NULL,
  ADD COLUMN IF NOT EXISTS last_login            TIMESTAMP    NULL,
  ADD COLUMN IF NOT EXISTS failed_login_attempts INT          NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS locked_until          TIMESTAMP    NULL,
  ADD COLUMN IF NOT EXISTS verification_code     VARCHAR(128) NULL;

-- Sessions index
ALTER TABLE sessions
  ADD INDEX IF NOT EXISTS idx_expires (expires_at);

-- Activity logs indexes  
ALTER TABLE activity_logs
  ADD INDEX IF NOT EXISTS idx_user_action (user_id, action),
  ADD INDEX IF NOT EXISTS idx_created (created_at);

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    id          INT          PRIMARY KEY AUTO_INCREMENT,
    user_id     INT         NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50)  NULL,
    entity_id   INT          NULL,
    old_value   TEXT         NULL,
    new_value   TEXT         NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  TEXT         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created   (user_id, created_at),
    INDEX idx_action_created (action, created_at)
);

-- ============================================================
-- PATCH 8: Seed Admin Account
-- Password = Admin@Kinas2025  (bcrypt hash below)
-- CHANGE THIS PASSWORD immediately after first login!
-- ============================================================
INSERT INTO users (
    name, email, password, phone, role, 
    division, status, verified, phone_verified,
    email_verified_at, created_at
) 
SELECT 
    'KINAS Admin',
    'info7admin@gmail.com',
    -- bcrypt hash of 'Admin@Kinas2025' (cost 12)
    '$2y$12$5s5rRcK9aTp.muylFLm6n.vR52uonPr7Kaq61S.kKUCxmbLCTfy6q',
    '+2340000000000',
    'admin',
    'kinas-automobile',
    'active',
    TRUE,
    TRUE,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'info7admin@gmail.com'
);

-- ============================================================
-- DONE
-- ============================================================
SELECT 
    'complete_patch.sql applied successfully!' AS status,
    (SELECT COUNT(*) FROM users WHERE role='admin') AS admin_count,
    (SELECT COUNT(*) FROM information_schema.TABLES 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME IN ('agent_profiles','login_attempts','rate_limits','marketplace_categories','marketplace_listings')
    ) AS critical_tables_present;
