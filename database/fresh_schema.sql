-- =====================================================================
-- KINAS GROUP — Fresh schema (v3)
-- =====================================================================
-- Run this on a clean database. Drops and recreates the kinas_group
-- database with all tables for the current product scope:
--   - Users + agent profiles (with 2-stage KYC status enum)
--   - Phone OTP verification (Termii)
--   - MetaMap identity verification (webhook-driven)
--   - Business documents (CAC/TIN/etc., admin-reviewed)
--   - Listings across 4 divisions (with location + sector filters)
--   - Saved listings, inquiries, messages, activity log
-- =====================================================================

DROP DATABASE IF EXISTS kinas_group;
CREATE DATABASE kinas_group CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kinas_group;

-- =====================================================================
-- USERS + AGENT PROFILES
-- =====================================================================

CREATE TABLE users (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password        VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    phone_verified_at TIMESTAMP NULL,
    role            ENUM('user', 'agent', 'admin') DEFAULT 'user',
    verified        BOOLEAN DEFAULT FALSE COMMENT 'Personal KYC passed (MetaMap)',
    status          ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    avatar          VARCHAR(500),
    company         VARCHAR(255),
    division        ENUM('automobile', 'real_estate', 'solar', 'marketplace'),
    email_verified_at   TIMESTAMP NULL,
    verification_code   VARCHAR(128) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE agent_profiles (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL UNIQUE,
    division            VARCHAR(50) NOT NULL,
    bio                 TEXT,
    company_name        VARCHAR(255),
    company_legal_name  VARCHAR(255) COMMENT 'Legal name as on CAC',
    cac_number          VARCHAR(50)  COMMENT 'CAC registration / RC / BN number',
    tin                 VARCHAR(50)  COMMENT 'Tax ID',
    license_number      VARCHAR(100),
    website             VARCHAR(500),
    avatar              VARCHAR(500),

    -- 2-stage KYC: personal (MetaMap) + business (admin review)
    verification_status ENUM(
        'pending',             -- registered, no KYC yet
        'phone_verified',      -- phone OTP confirmed
        'kyc_passed',          -- MetaMap approved the person
        'documents_submitted', -- CAC uploaded, awaiting admin
        'approved',            -- admin approved → can list
        'rejected',            -- admin rejected
        'suspended'            -- post-approval suspension
    ) DEFAULT 'pending',
    kyc_provider        VARCHAR(32)  NULL DEFAULT 'metamap',
    kyc_verification_id VARCHAR(64)  NULL COMMENT 'MetaMap verification id',
    kyc_passed_at       TIMESTAMP    NULL,
    business_doc_reviewed_by INT     NULL,
    business_doc_reviewed_at TIMESTAMP NULL,
    business_doc_notes  TEXT         NULL,

    tax_id              VARCHAR(100) NULL,
    years_in_business   ENUM('lt_1','1_3','3_5','5_plus') NULL,
    professional_affiliations VARCHAR(500) NULL,

    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)             REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_doc_reviewed_by) REFERENCES users(id),
    INDEX idx_division  (division),
    INDEX idx_status    (verification_status)
) ENGINE=InnoDB;

-- =====================================================================
-- PHONE OTP (Termii) — only the hash is stored
-- =====================================================================

CREATE TABLE phone_otps (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    user_id         INT NOT NULL,
    phone           VARCHAR(20) NOT NULL,
    code_hash       VARCHAR(255) NOT NULL COMMENT 'bcrypt of the 6-digit code',
    purpose         ENUM('register','login','reset','change_phone') DEFAULT 'register',
    attempts        TINYINT DEFAULT 0,
    max_attempts    TINYINT DEFAULT 5,
    termii_message_id VARCHAR(64) NULL,
    termii_status       VARCHAR(32) NULL COMMENT 'delivered / sent / failed',
    expires_at      TIMESTAMP NOT NULL,
    consumed_at     TIMESTAMP NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_phone (user_id, phone),
    INDEX idx_expires    (expires_at),
    INDEX idx_active     (consumed_at, expires_at)
) ENGINE=InnoDB;

-- =====================================================================
-- METAMAP IDENTITY VERIFICATION
-- =====================================================================

CREATE TABLE metamap_verifications (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    verification_id     VARCHAR(64) NOT NULL UNIQUE,
    status              ENUM('created','in_progress','approved','rejected','review_needed','expired') DEFAULT 'created',
    mati_status         VARCHAR(64) NULL,
    country             VARCHAR(8) NULL,
    metadata            JSON NULL,
    decision_payload    JSON NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at        TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user  (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================================
-- BUSINESS DOCUMENTS (CAC etc.) — agent uploads, admin reviews
-- =====================================================================

CREATE TABLE business_documents (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    user_id         INT NOT NULL,
    document_type   ENUM('cac_certificate','tin_certificate','utility_bill','other') NOT NULL,
    document_url    VARCHAR(500) NOT NULL,
    file_name       VARCHAR(255) NULL,
    file_size       INT NULL,
    mime_type       VARCHAR(64) NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_notes     TEXT NULL,
    reviewed_by     INT NULL,
    reviewed_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    INDEX idx_type   (document_type)
) ENGINE=InnoDB;

-- =====================================================================
-- LISTINGS (4 divisions) + IMAGES
-- =====================================================================

CREATE TABLE car_listings (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    agent_id        INT NOT NULL,
    title           VARCHAR(255) NOT NULL,
    brand           VARCHAR(100) NOT NULL,
    model           VARCHAR(100) NOT NULL,
    year            INT NOT NULL,
    price           DECIMAL(15,2) NOT NULL,
    mileage         INT,
    fuel_type       VARCHAR(50),
    transmission    VARCHAR(50),
    color           VARCHAR(50),
    condition_status VARCHAR(50),
    body_type       VARCHAR(50),
    drivetrain      VARCHAR(50),
    doors           TINYINT,
    description     TEXT,
    features        JSON,
    vin             VARCHAR(50),
    city            VARCHAR(100),
    state           VARCHAR(100),
    country         VARCHAR(100) DEFAULT 'Nigeria',
    status          ENUM('active', 'sold', 'pending', 'flagged', 'removed') DEFAULT 'active',
    featured        BOOLEAN DEFAULT FALSE,
    views           INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_brand (brand),
    INDEX idx_status (status),
    INDEX idx_price (price),
    INDEX idx_year  (year),
    INDEX idx_city  (city),
    INDEX idx_state (state)
) ENGINE=InnoDB;

CREATE TABLE property_listings (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    agent_id        INT NOT NULL,
    title           VARCHAR(255) NOT NULL,
    property_type   VARCHAR(100) NOT NULL,
    listing_type    ENUM('sale', 'rent') NOT NULL,
    price           DECIMAL(15,2) NOT NULL,
    beds            INT,
    baths           INT,
    sqft            INT,
    lot_size        DECIMAL(10,2),
    year_built      INT,
    address         VARCHAR(500),
    city            VARCHAR(100),
    state           VARCHAR(100),
    zip_code        VARCHAR(20),
    country         VARCHAR(100) DEFAULT 'Nigeria',
    latitude        DECIMAL(10,8),
    longitude       DECIMAL(11,8),
    description     TEXT,
    features        JSON,
    amenities       JSON,
    view_type       VARCHAR(100),
    hoa_fees        DECIMAL(12,2),
    status          ENUM('active', 'sold', 'rented', 'pending', 'flagged', 'removed') DEFAULT 'active',
    featured        BOOLEAN DEFAULT FALSE,
    views           INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_price  (price),
    INDEX idx_beds   (beds),
    INDEX idx_city   (city),
    INDEX idx_state  (state)
) ENGINE=InnoDB;

CREATE TABLE solar_listings (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    agent_id        INT NOT NULL,
    title           VARCHAR(255) NOT NULL,
    service_type    ENUM('residential', 'commercial', 'industrial', 'maintenance', 'financing') NOT NULL,
    brand           VARCHAR(100),
    capacity_kw     DECIMAL(8,2),
    warranty_years  TINYINT,
    price           DECIMAL(15,2),
    description     TEXT,
    features        JSON,
    city            VARCHAR(100),
    state           VARCHAR(100),
    country         VARCHAR(100) DEFAULT 'Nigeria',
    status          ENUM('active', 'inactive', 'flagged', 'removed') DEFAULT 'active',
    views           INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_city   (city)
) ENGINE=InnoDB;

CREATE TABLE marketplace_categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id   INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES marketplace_categories(id)
) ENGINE=InnoDB;

CREATE TABLE marketplace_listings (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    agent_id         INT NOT NULL,
    title            VARCHAR(255) NOT NULL,
    category_id      INT,
    brand            VARCHAR(100),
    price            DECIMAL(15,2) NOT NULL,
    description      TEXT,
    condition_status VARCHAR(50),
    city             VARCHAR(100),
    state            VARCHAR(100),
    country          VARCHAR(100) DEFAULT 'Nigeria',
    status           ENUM('active', 'sold', 'pending', 'flagged', 'removed') DEFAULT 'active',
    featured         BOOLEAN DEFAULT FALSE,
    views            INT DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id)    REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES marketplace_categories(id),
    INDEX idx_status  (status),
    INDEX idx_city    (city),
    INDEX idx_brand   (brand)
) ENGINE=InnoDB;

CREATE TABLE listing_images (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    listing_id    INT NOT NULL,
    listing_type  ENUM('car', 'property', 'solar', 'marketplace') NOT NULL,
    url           VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    sort_order    INT DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id, listing_type)
) ENGINE=InnoDB;

-- =====================================================================
-- SAVED LISTINGS, INQUIRIES, MESSAGES
-- =====================================================================

CREATE TABLE saved_listings (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    user_id      INT NOT NULL,
    listing_id   INT NOT NULL,
    listing_type ENUM('car','property','solar','marketplace') NOT NULL,
    saved_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_listing (user_id, listing_id, listing_type),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE inquiries (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    user_id      INT NULL,
    agent_id     INT NOT NULL,
    listing_id   INT NOT NULL,
    listing_type ENUM('car','property','solar','marketplace') NOT NULL,
    name         VARCHAR(255) NOT NULL,
    email        VARCHAR(255) NOT NULL,
    phone        VARCHAR(20),
    message      TEXT NOT NULL,
    is_read      TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_agent (agent_id),
    INDEX idx_user  (user_id)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    listing_id  INT,
    listing_type ENUM('car','property','solar','marketplace'),
    subject     VARCHAR(255),
    body        TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB;

-- =====================================================================
-- ACTIVITY LOG, AUDIT, RATE LIMIT
-- =====================================================================

CREATE TABLE activity_logs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NULL,
    action      VARCHAR(64) NOT NULL,
    details     TEXT,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user    (user_id),
    INDEX idx_action  (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    actor_id    INT NULL,
    entity_type VARCHAR(64) NOT NULL,
    entity_id   INT NOT NULL,
    action      VARCHAR(64) NOT NULL,
    before_state JSON,
    after_state  JSON,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;

CREATE TABLE rate_limits (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    bucket      VARCHAR(64) NOT NULL,
    window_start TIMESTAMP NOT NULL,
    count       INT DEFAULT 1,
    UNIQUE KEY uk_bucket_window (bucket, window_start)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Admin user (password: 'Admin@2026' — CHANGE IN PRODUCTION)
-- bcrypt hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (name, email, password, phone, role, verified, status, email_verified_at, division)
VALUES ('Site Admin', 'admin@kinasgroup.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '+2348000000000', 'admin', 1, 'active', NOW(), NULL);

-- Default marketplace categories
INSERT INTO marketplace_categories (name, slug, description) VALUES
  ('Watches & Timepieces', 'watches', 'Luxury wristwatches and clocks'),
  ('Jewelry',              'jewelry', 'Fine jewelry and gemstones'),
  ('Art',                  'art',     'Original artworks and sculptures'),
  ('Fashion & Accessories','fashion', 'Designer fashion, leather goods, accessories'),
  ('Yachts',               'yachts',  'Yachts and watercraft'),
  ('Private Jets',         'jets',    'Private aviation'),
  ('Collectibles',         'collectibles', 'Rare and collectible items');
