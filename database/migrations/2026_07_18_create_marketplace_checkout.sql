-- =====================================================================
-- 2026_07_18_create_marketplace_checkout.sql
-- ---------------------------------------------------------------------
-- The cart icon in templates/header.php (present on every page site-wide),
-- "Add to Cart" on marketplace listings, and the full Paystack checkout
-- flow (api/cart/*, api/payments/checkout-init.php, checkout-verify.php,
-- includes/order-fulfillment.php, admin/marketplace-orders.php,
-- user/orders.php) all reference cart_items, orders, and order_items —
-- none of which had a CREATE TABLE anywhere. Every "Add to cart" click
-- and every page's cart-badge check would fail. Columns below are
-- reconstructed exactly from what that code reads and writes.
--
-- Also adds columns that the SAME checkout flow requires on two
-- *existing* tables (transactions, payout_settings) that were never
-- extended to match:
--   - transactions:      order_id, buyer_id, payment_method,
--                         paystack_reference, settlement_mode
--   - payout_settings:   paystack_bank_code, paystack_subaccount_code,
--                         paystack_subaccount_id, paystack_account_verified,
--                         paystack_verified_account_name,
--                         paystack_subaccount_synced_at
-- =====================================================================

CREATE TABLE IF NOT EXISTS cart_items (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    buyer_id      INT NOT NULL,
    listing_id    INT NOT NULL,
    listing_type  ENUM('car','property','solar','marketplace') NOT NULL DEFAULT 'marketplace',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_buyer_listing (buyer_id, listing_id, listing_type),
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id                     INT PRIMARY KEY AUTO_INCREMENT,
    buyer_id               INT NOT NULL,
    reference              VARCHAR(64) NOT NULL,
    email                  VARCHAR(255) NOT NULL,
    phone                  VARCHAR(30) NULL,
    shipping_address       VARCHAR(500) NOT NULL,
    amount                 DECIMAL(15,2) NOT NULL COMMENT 'Total charged to buyer, including fee gross-up',
    subtotal_amount        DECIMAL(15,2) NOT NULL COMMENT 'Sum of listing prices, before any fee gross-up',
    fee_amount             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    settlement_mode        ENUM('platform','subaccount') NOT NULL DEFAULT 'platform',
    subaccount_code        VARCHAR(100) NULL,
    currency               VARCHAR(8) NOT NULL DEFAULT 'NGN',
    status                 ENUM('pending','paid','failed','abandoned') NOT NULL DEFAULT 'pending',
    gateway_response       VARCHAR(500) NULL,
    paystack_access_code   VARCHAR(100) NULL,
    paystack_channel       VARCHAR(50) NULL,
    paid_at                TIMESTAMP NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reference (reference),
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_buyer (buyer_id),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    order_id      INT NOT NULL,
    listing_id    INT NOT NULL,
    listing_type  ENUM('car','property','solar','marketplace') NOT NULL DEFAULT 'marketplace',
    agent_id      INT NOT NULL,
    title         VARCHAR(255) NOT NULL COMMENT 'Snapshot of listing title at time of purchase',
    price         DECIMAL(15,2) NOT NULL COMMENT 'Snapshot of listing price at time of purchase',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_listing (listing_id, listing_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transactions: extend to record which order/buyer/payment channel each
-- payout line came from (includes/order-fulfillment.php inserts these).
ALTER TABLE transactions
    ADD COLUMN order_id            INT NULL              AFTER listing_type,
    ADD COLUMN buyer_id            INT NULL              AFTER order_id,
    ADD COLUMN payment_method      VARCHAR(30) NULL       AFTER buyer_id,
    ADD COLUMN paystack_reference  VARCHAR(64) NULL       AFTER payment_method,
    ADD COLUMN settlement_mode     ENUM('platform','subaccount') NULL AFTER paystack_reference,
    ADD CONSTRAINT fk_transactions_order  FOREIGN KEY (order_id)  REFERENCES orders(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_transactions_buyer  FOREIGN KEY (buyer_id)  REFERENCES users(id)  ON DELETE SET NULL,
    ADD INDEX idx_order (order_id),
    ADD INDEX idx_paystack_reference (paystack_reference);

-- payout_settings: extend for Paystack subaccount auto-split (agent's
-- connected bank account for direct settlement) — read/written by
-- api/agent/save-payout-settings.php and api/payments/checkout-init.php.
ALTER TABLE payout_settings
    ADD COLUMN paystack_bank_code               VARCHAR(20)  NULL AFTER payment_method,
    ADD COLUMN paystack_subaccount_code         VARCHAR(100) NULL AFTER paystack_bank_code,
    ADD COLUMN paystack_subaccount_id           VARCHAR(50)  NULL AFTER paystack_subaccount_code,
    ADD COLUMN paystack_account_verified        TINYINT(1)   NOT NULL DEFAULT 0 AFTER paystack_subaccount_id,
    ADD COLUMN paystack_verified_account_name   VARCHAR(255) NULL AFTER paystack_account_verified,
    ADD COLUMN paystack_subaccount_synced_at    TIMESTAMP    NULL AFTER paystack_verified_account_name;
