-- MiniCRM database schema
-- Usage: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- ---------------------------------------------------------------
-- Users (CRM staff who log in)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)        NOT NULL,
    email      VARCHAR(150)        NOT NULL UNIQUE,
    password   VARCHAR(255)        NOT NULL,
    role       ENUM('admin','sales') NOT NULL DEFAULT 'sales',
    created_at TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Customers (companies / people the org does business with)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150)        NOT NULL,
    company    VARCHAR(150)        NULL,
    email      VARCHAR(150)        NULL,
    phone      VARCHAR(40)         NULL,
    address    VARCHAR(255)        NULL,
    status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
    owner_id   INT UNSIGNED        NULL,
    notes      TEXT                NULL,
    created_at TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customers_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Leads (prospective customers, pre-sale)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(150)        NOT NULL,
    customer_id INT UNSIGNED        NULL,
    contact_name  VARCHAR(150)      NULL,
    contact_email VARCHAR(150)      NULL,
    source      VARCHAR(80)         NULL,
    status      ENUM('new','contacted','qualified','lost') NOT NULL DEFAULT 'new',
    value       DECIMAL(12,2)       NOT NULL DEFAULT 0,
    owner_id    INT UNSIGNED        NULL,
    notes       TEXT                NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_leads_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Deals (opportunities being worked toward a close)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deals (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(150)        NOT NULL,
    customer_id INT UNSIGNED        NOT NULL,
    value       DECIMAL(12,2)       NOT NULL DEFAULT 0,
    stage       ENUM('open','proposal','negotiation','closed_won','closed_lost') NOT NULL DEFAULT 'open',
    close_date  DATE                NULL,
    owner_id    INT UNSIGNED        NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deals_stage (stage)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Activities (notes / timeline entries logged against a customer)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activities (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED        NOT NULL,
    user_id     INT UNSIGNED        NULL,
    type        VARCHAR(40)         NOT NULL DEFAULT 'note',
    description TEXT                NOT NULL,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------

-- Default admin login: admin@example.com / password
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', '$2y$10$bStQzLf6y2u8oGLFPaP5oeX5IR.5IVsE8.yhYMxCpyKJVDMjCjvNO', 'admin')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO customers (name, company, email, phone, status, owner_id) VALUES
('Jane Cooper', 'Acme Corp', 'jane@acme.com', '555-0101', 'active', 1),
('Robert Fox', 'Globex Inc', 'robert@globex.com', '555-0102', 'active', 1),
('Wade Warren', 'Initech', 'wade@initech.com', '555-0103', 'inactive', 1)
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO leads (title, customer_id, contact_name, contact_email, source, status, value, owner_id) VALUES
('Website inquiry - CRM upgrade', 1, 'Jane Cooper', 'jane@acme.com', 'Website', 'new', 5000.00, 1),
('Referral - support contract', 2, 'Robert Fox', 'robert@globex.com', 'Referral', 'contacted', 8000.00, 1)
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO deals (title, customer_id, value, stage, close_date, owner_id) VALUES
('Acme Corp - Annual License', 1, 12000.00, 'proposal', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1),
('Globex Inc - Support Renewal', 2, 6000.00, 'open', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 1)
ON DUPLICATE KEY UPDATE title = title;
