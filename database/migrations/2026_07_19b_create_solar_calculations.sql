-- =====================================================================
-- 2026_07_19b_create_solar_calculations.sql
-- ---------------------------------------------------------------------
-- Backs api/calculator/calculate.php (saves a snapshot for logged-in
-- users) and api/calculator/generate-receipt.php (reads it back to
-- produce a PDF). Reachable from the "Solar Calculator" button in the
-- Kinas Volt homepage hero. The INSERT here isn't wrapped in a
-- try/catch, so any logged-in user using the calculator would have
-- hit an uncaught fatal error with no table to insert into.
-- =====================================================================

CREATE TABLE IF NOT EXISTS solar_calculations (
    id                 INT PRIMARY KEY AUTO_INCREMENT,
    user_id            INT NOT NULL,
    monthly_bill       DECIMAL(12,2) NOT NULL,
    roof_area          DECIMAL(12,2) NOT NULL,
    sun_hours          DECIMAL(5,2) NOT NULL,
    electricity_rate   DECIMAL(8,4) NOT NULL,
    system_size        DECIMAL(10,2) NOT NULL,
    system_cost        DECIMAL(15,2) NOT NULL,
    monthly_savings    DECIMAL(12,2) NOT NULL,
    calculation_data   JSON NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
