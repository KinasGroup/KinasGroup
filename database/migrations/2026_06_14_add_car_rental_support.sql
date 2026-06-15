-- ============================================================
-- Migration: Add Car Rental Support to KINAS AUTOMOBILE
-- Date: 2026-06-14
-- Description:
--   1. Add `listing_type` to car_listings (sale | rental)
--   2. Add `seats` column to car_listings
--   3. Create car_rental_bookings table for availability checks
-- ============================================================

-- 1. listing_type column (default 'sale' so existing rows are unaffected)
ALTER TABLE car_listings
    ADD COLUMN listing_type ENUM('sale', 'rental') NOT NULL DEFAULT 'sale'
    AFTER id;

-- 2. seats column (NULL = not specified)
ALTER TABLE car_listings
    ADD COLUMN seats TINYINT UNSIGNED NULL DEFAULT NULL
    AFTER doors;

-- Index for fast rental queries
ALTER TABLE car_listings
    ADD INDEX idx_listing_type (listing_type),
    ADD INDEX idx_seats (seats);

-- 3. Rental bookings table
--    Used by rental-search.php to filter out unavailable cars.
CREATE TABLE IF NOT EXISTS car_rental_bookings (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    car_id      INT NOT NULL,
    user_id     INT NOT NULL,
    agent_id    INT NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    total_days  SMALLINT UNSIGNED NOT NULL,
    price_per_day DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    status      ENUM('pending','confirmed','active','completed','cancelled','rejected')
                NOT NULL DEFAULT 'pending',
    notes       TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (car_id)   REFERENCES car_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id),
    FOREIGN KEY (agent_id) REFERENCES users(id),

    INDEX idx_car_dates   (car_id, start_date, end_date),
    INDEX idx_status      (status),
    INDEX idx_user        (user_id),
    INDEX idx_agent       (agent_id)
) ENGINE=InnoDB;
