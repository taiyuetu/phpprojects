-- ===============================================================
-- MiniCRM Complete Database Schema
-- Single-file import: contains all tables, indexes, and seed data.
-- Usage: mysql -u root -p < database/schema.sql
-- ===============================================================

CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_db;

-- ---------------------------------------------------------------
-- 1. Users (CRM staff who log in)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)        NOT NULL,
    email      VARCHAR(150)        NOT NULL UNIQUE,
    password   VARCHAR(255)        NOT NULL,
    role       ENUM('admin','sales') NOT NULL DEFAULT 'sales',
    created_at TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 2. Customers (companies / people the org does business with)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                      VARCHAR(150)        NOT NULL,
    company                   VARCHAR(150)        NULL,
    email                     VARCHAR(150)        NULL,
    phone                     VARCHAR(40)         NULL,
    whatsapp                  VARCHAR(100)        NULL COMMENT 'WhatsApp信息',
    facebook                  VARCHAR(255)        NULL COMMENT 'Facebook主页',
    tiktok                    VARCHAR(255)        NULL COMMENT 'TikTok频道',
    website                   VARCHAR(255)        NULL COMMENT '官方网站',
    source_country            VARCHAR(80)         NULL COMMENT '来源国家',
    source_city               VARCHAR(80)         NULL COMMENT '来源城市',
    address                   VARCHAR(255)        NULL,
    first_purchase_from_china TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '是否第一次从中国采购',
    has_import_capability     TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '是否有进口能力',
    conversion_time           DATETIME            NULL COMMENT '客户新建/转化时间',
    status                    ENUM('active','inactive') NOT NULL DEFAULT 'active',
    owner_id                  INT UNSIGNED        NULL,
    notes                     TEXT                NULL,
    created_at                TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 3. Leads (prospective customers, pre-sale)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id               INT UNSIGNED        NULL COMMENT '关联客户ID',
    title                     VARCHAR(150)        NOT NULL,
    company                   VARCHAR(150)        NULL COMMENT '公司名称',
    contact_name              VARCHAR(150)        NULL,
    contact_email             VARCHAR(150)        NULL,
    lead_time                 DATETIME            NULL COMMENT '线索时间',
    whatsapp                  VARCHAR(100)        NULL COMMENT 'WhatsApp信息',
    phone                     VARCHAR(40)         NULL COMMENT '电话号码',
    facebook                  VARCHAR(255)        NULL COMMENT 'Facebook主页',
    tiktok                    VARCHAR(255)        NULL COMMENT 'TikTok频道',
    website                   VARCHAR(255)        NULL COMMENT '官方网站',
    source                    VARCHAR(80)         NULL,
    source_country            VARCHAR(80)         NULL COMMENT '来源国家',
    source_city               VARCHAR(80)         NULL COMMENT '来源城市',
    address                   VARCHAR(255)        NULL COMMENT '具体地址',
    status                    ENUM('new','contacted','qualified','lost') NOT NULL DEFAULT 'new',
    lost_reason               VARCHAR(50)         NULL COMMENT '流失原因',
    lost_at                   TIMESTAMP           NULL COMMENT '流失时间',
    value                     DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    first_purchase_from_china TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '是否第一次从中国采购',
    has_import_capability     TINYINT(1)          NOT NULL DEFAULT 0 COMMENT '是否有进口能力',
    owner_id                  INT UNSIGNED        NULL,
    notes                     TEXT                NULL,
    created_at                TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_leads_status (status),
    INDEX idx_leads_customer (customer_id),
    INDEX idx_leads_lost (lost_reason)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 4. Deals (opportunities being worked toward a close)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deals (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                 VARCHAR(150)        NOT NULL,
    customer_id           INT UNSIGNED        NOT NULL,
    value                 DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    stage                 ENUM('open','proposal','negotiation','closed_won','closed_lost') NOT NULL DEFAULT 'open',
    close_date            DATE                NULL,
    stage_open_at         DATETIME            NULL COMMENT '进入进行中时间',
    stage_proposal_at     DATETIME            NULL COMMENT '进入方案阶段时间',
    stage_negotiation_at  DATETIME            NULL COMMENT '进入谈判中时间',
    stage_closed_won_at   DATETIME            NULL COMMENT '成交时间',
    stage_closed_lost_at  DATETIME            NULL COMMENT '丢单时间',
    owner_id              INT UNSIGNED        NULL,
    created_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deals_stage (stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 5. Follow-ups (follow-up records / price comparison tracking)
-- ---------------------------------------------------------------
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 6. Activities (notes / timeline entries logged against a customer)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Seed Data
-- ---------------------------------------------------------------

-- 1. Default admin login: admin@example.com / password
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', '$2y$10$bStQzLf6y2u8oGLFPaP5oeX5IR.5IVsE8.yhYMxCpyKJVDMjCjvNO', 'admin')
ON DUPLICATE KEY UPDATE name = name;

-- 2. Sample customers
INSERT INTO customers (id, name, company, email, phone, whatsapp, source_country, source_city, first_purchase_from_china, has_import_capability, status, owner_id) VALUES
(1, 'Jane Cooper', 'Acme Corp', 'jane@acme.com', '555-0101', '+1-555-0101', 'United States', 'New York', 0, 1, 'active', 1),
(2, 'Robert Fox', 'Globex Inc', 'robert@globex.com', '555-0102', '+1-555-0102', 'Canada', 'Toronto', 1, 1, 'active', 1),
(3, 'Wade Warren', 'Initech', 'wade@initech.com', '555-0103', '+1-555-0103', 'United Kingdom', 'London', 0, 1, 'inactive', 1)
ON DUPLICATE KEY UPDATE name = name;

-- 3. Sample leads
INSERT INTO leads (title, company, contact_name, contact_email, phone, whatsapp, source, source_country, status, value, owner_id) VALUES
('Website inquiry - CRM upgrade', 'Acme Corp', 'Jane Cooper', 'jane@acme.com', '555-0101', '+1-555-0101', 'Website', 'United States', 'new', 5000.00, 1),
('Referral - support contract', 'Globex Inc', 'Robert Fox', 'robert@globex.com', '555-0102', '+1-555-0102', 'Referral', 'Canada', 'contacted', 8000.00, 1)
ON DUPLICATE KEY UPDATE title = title;

-- 4. Sample deals
INSERT INTO deals (title, customer_id, value, stage, close_date, stage_open_at, stage_proposal_at, owner_id) VALUES
('Acme Corp - Annual License', 1, 12000.00, 'proposal', DATE_ADD(CURDATE(), INTERVAL 30 DAY), NOW(), NOW(), 1),
('Globex Inc - Support Renewal', 2, 6000.00, 'open', DATE_ADD(CURDATE(), INTERVAL 45 DAY), NOW(), NULL, 1)
ON DUPLICATE KEY UPDATE title = title;

-- 5. Sample follow-ups
INSERT INTO follow_ups (customer_id, user_id, type, title, description, next_action, next_date) VALUES
(1, 1, 'price_comparison', 'Initial Quote Comparison', 'Customer compared our quote against competitor A', 'Send customized discount proposal', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
(2, 1, 'follow_up', 'Contract Renewal Discussion', 'Discussed annual support SLA terms', 'Send updated draft contract', DATE_ADD(CURDATE(), INTERVAL 7 DAY));
