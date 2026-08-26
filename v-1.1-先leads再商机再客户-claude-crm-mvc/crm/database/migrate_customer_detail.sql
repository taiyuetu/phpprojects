-- Migration: Add customer detail page features
-- 1. Add customer_id back to leads table
-- 2. Create follow_ups table for tracking price comparison inquiries

USE crm_db;

-- Add customer_id column to leads table
ALTER TABLE leads ADD COLUMN customer_id INT UNSIGNED NULL AFTER id;
ALTER TABLE leads ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
ALTER TABLE leads ADD INDEX idx_leads_customer (customer_id);

-- Create follow_ups table for tracking follow-up records
CREATE TABLE IF NOT EXISTS follow_ups (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED        NOT NULL,
    user_id     INT UNSIGNED        NULL,
    type        ENUM('price_comparison','no_response','follow_up','other') NOT NULL DEFAULT 'price_comparison',
    title       VARCHAR(200)        NOT NULL,
    description TEXT                NULL,
    next_action VARCHAR(200)        NULL,
    next_date   DATE                NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_follow_ups_customer (customer_id),
    INDEX idx_follow_ups_date (next_date)
) ENGINE=InnoDB;
