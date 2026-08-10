-- =====================================================================
-- 2026_08_10_create_product_reviews.sql
-- ---------------------------------------------------------------------
-- Creates the product_reviews table for the customer review system.
-- Allows customers to leave 1-5 star ratings and comments on listings
-- (cars, properties, marketplace items).
--
-- Referenced by:
--   - api/reviews/create.php      (submit a review)
--   - api/reviews/list.php        (fetch reviews for a listing)
--   - api/reviews/moderate.php    (admin approve/reject reviews)
--   - admin/reviews.php           (review moderation dashboard)
--   - listing.php                 (display reviews on product pages)
--   - assets/js/reviews.js        (frontend review UI)
--
-- NOTE: Reviews are submitted with status = 'pending' and must be
-- approved by an admin before appearing publicly. This ensures
-- moderation control and prevents spam.
-- =====================================================================

CREATE TABLE IF NOT EXISTS product_reviews (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    user_id       INT NOT NULL COMMENT 'User who left the review',
    listing_type  ENUM('car','property','marketplace') NOT NULL COMMENT 'Type of listing being reviewed',
    listing_id    INT NOT NULL COMMENT 'ID of the listing being reviewed',
    rating        TINYINT(1) NOT NULL COMMENT 'Rating from 1-5 stars',
    comment       TEXT DEFAULT NULL COMMENT 'Review comment text',
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT 'Moderation status',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When review was created',
    updated_at    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'When review was last updated',
    
    UNIQUE KEY uniq_user_listing (user_id, listing_type, listing_id) COMMENT 'Prevents duplicate reviews per user per listing',
    INDEX idx_listing (listing_type, listing_id) COMMENT 'For fetching reviews by listing',
    INDEX idx_user (user_id) COMMENT 'For fetching reviews by user',
    INDEX idx_status (status) COMMENT 'For filtering by moderation status',
    INDEX idx_created (created_at) COMMENT 'For sorting by date',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product reviews for cars, properties, and marketplace items';

-- =====================================================================
-- ROLLBACK (if needed)
-- ---------------------------------------------------------------------
-- DROP TABLE IF EXISTS product_reviews;
-- =====================================================================