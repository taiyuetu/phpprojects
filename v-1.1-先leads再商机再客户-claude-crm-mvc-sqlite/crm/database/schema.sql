-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- ===============================================================
-- 叁程 CRM (Triphase CRM) — Complete Database Schema (SQLite)
-- ===============================================================
-- THIS FILE IS THE CANONICAL / SINGLE SOURCE OF TRUTH for the database.
-- It is fully idempotent: every statement uses IF NOT EXISTS / OR IGNORE,
-- so it is safe to re-run any number of times on any database.
--
-- Recommended usage (handles both fresh installs AND upgrading existing DBs):
--     php database/migrate.php
--
-- Direct (fresh install only):
--     sqlite3 database/crm.sqlite < database/schema.sql
--
-- For one-off structural changes to EXISTING tables (ALTER TABLE), put a new
-- numbered file in database/migrations/ -- migrate.php applies it exactly once.
-- ===============================================================
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- ---------------------------------------------------------------
-- 1. Users (CRM staff who log in)
-- ---------------------------------------------------------------
-- These rows are the SINGLE SOURCE OF TRUTH for "who a person is":
-- every other table references users by id only (owner_id / user_id /
-- uploaded_by), so editing a profile here is what the whole app shows --
-- customers' 负责人, leads' 负责人, deals, orders, follow-ups, attachments.
CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    password   TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'sales' CHECK (role IN ('admin','sales')),
    phone      TEXT,
    whatsapp   TEXT,
    job_title  TEXT,
    notes      TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    -- nullable on purpose: SQLite forbids ALTER TABLE ADD COLUMN with a
    -- non-constant default, and legacy databases must upgrade cleanly.
    updated_at TEXT
);

-- ---------------------------------------------------------------
-- 1b. App settings (key/value; edited from the 设置 page by admins)
-- ---------------------------------------------------------------
-- Read through Setting::get() / appSetting(), which cache one query per
-- request and fall back to Setting::defaults() when a row is missing.
CREATE TABLE IF NOT EXISTS app_settings (
    name       TEXT PRIMARY KEY,
    value      TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_by INTEGER,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------
-- 2. Customers (companies / people the org does business with)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    name                      TEXT NOT NULL,
    company                   TEXT,
    email                     TEXT,
    phone                     TEXT,
    whatsapp                  TEXT,
    wechat                    TEXT,
    facebook                  TEXT,
    tiktok                    TEXT,
    website                   TEXT,
    source_country            TEXT,
    source_city               TEXT,
    address                   TEXT,
    shipping_address          TEXT,
    first_purchase_from_china INTEGER NOT NULL DEFAULT 0,
    has_import_capability     INTEGER NOT NULL DEFAULT 0,
    conversion_time           TEXT,
    status                    TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
    owner_id                  INTEGER,
    notes                     TEXT,
    created_at                TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at                TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_customers_status ON customers(status);

-- ---------------------------------------------------------------
-- 3. Leads (prospective customers, pre-sale)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id               INTEGER,
    title                     TEXT NOT NULL,
    company                   TEXT,
    contact_name              TEXT,
    contact_email             TEXT,
    lead_time                 TEXT,
    whatsapp                  TEXT,
    phone                     TEXT,
    facebook                  TEXT,
    tiktok                    TEXT,
    website                   TEXT,
    source                    TEXT,
    source_country            TEXT,
    source_city               TEXT,
    address                   TEXT,
    status                    TEXT NOT NULL DEFAULT 'new' CHECK (status IN ('new','contacted','qualified','lost')),
    lost_reason               TEXT,
    lost_at                   TEXT,
    value                     REAL NOT NULL DEFAULT 0.00,
    first_purchase_from_china INTEGER NOT NULL DEFAULT 0,
    has_import_capability     INTEGER NOT NULL DEFAULT 0,
    owner_id                  INTEGER,
    notes                     TEXT,
    created_at                TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at                TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE INDEX IF NOT EXISTS idx_leads_customer ON leads(customer_id);
CREATE INDEX IF NOT EXISTS idx_leads_lost ON leads(lost_reason);

-- ---------------------------------------------------------------
-- 4. Deals (opportunities being worked toward a close)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deals (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    title                 TEXT NOT NULL,
    customer_id           INTEGER NOT NULL,
    value                 REAL NOT NULL DEFAULT 0.00,
    stage                 TEXT NOT NULL DEFAULT 'open' CHECK (stage IN ('open','proposal','negotiation','closed_won','closed_lost')),
    close_date            TEXT,
    stage_open_at         TEXT,
    stage_proposal_at     TEXT,
    stage_negotiation_at  TEXT,
    stage_closed_won_at   TEXT,
    stage_closed_lost_at  TEXT,
    archived              INTEGER NOT NULL DEFAULT 0,
    archived_at           TEXT,
    owner_id              INTEGER,
    created_at            TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at            TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_deals_stage ON deals(stage);
CREATE INDEX IF NOT EXISTS idx_deals_archived ON deals(archived);

-- ---------------------------------------------------------------
-- 5. Orders (confirmed orders from closed deals)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number    TEXT NOT NULL UNIQUE,
    deal_id         INTEGER,
    customer_id     INTEGER NOT NULL,
    title           TEXT NOT NULL,
    amount          REAL NOT NULL DEFAULT 0.00,
    status          TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','processing','shipped','delivered','completed','cancelled')),
    payment_status  TEXT NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','partial','paid')),
    order_date      TEXT NOT NULL DEFAULT (date('now')),
    delivery_date   TEXT,
    shipping_address TEXT,
    notes           TEXT,
    owner_id        INTEGER,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_deal ON orders(deal_id);
CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(order_date);

-- ---------------------------------------------------------------
-- 6. Order Items (line items / product details per order)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id    INTEGER NOT NULL,
    product_name TEXT NOT NULL,
    sku         TEXT,
    quantity    REAL NOT NULL DEFAULT 1,
    unit_price  REAL NOT NULL DEFAULT 0.00,
    subtotal    REAL NOT NULL DEFAULT 0.00,
    unit        TEXT DEFAULT '件',
    notes       TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);

-- ---------------------------------------------------------------
-- 6. Follow-ups (follow-up records / price comparison tracking)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follow_ups (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    user_id     INTEGER,
    type        TEXT NOT NULL DEFAULT 'price_comparison' CHECK (type IN ('price_comparison','no_response','follow_up','other')),
    title       TEXT NOT NULL,
    description TEXT,
    next_action TEXT,
    next_date   TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_follow_ups_customer ON follow_ups(customer_id);
CREATE INDEX IF NOT EXISTS idx_follow_ups_date ON follow_ups(next_date);

-- ---------------------------------------------------------------
-- 7. Activities (notes / timeline entries logged against a customer)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activities (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    user_id     INTEGER,
    type        TEXT NOT NULL DEFAULT 'note',
    description TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------
-- 8. Attachments (file uploads for deals and orders)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    related_type  TEXT NOT NULL CHECK (related_type IN ('deal','order','customer')),
    related_id    INTEGER NOT NULL,
    filename      TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type     TEXT NOT NULL,
    file_size     INTEGER NOT NULL DEFAULT 0,
    uploaded_by   INTEGER,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_attachments_related ON attachments(related_type, related_id);

-- ---------------------------------------------------------------
-- Triggers to emulate MySQL's ON UPDATE CURRENT_TIMESTAMP
-- ---------------------------------------------------------------
CREATE TRIGGER IF NOT EXISTS trg_customers_updated
    AFTER UPDATE ON customers
    FOR EACH ROW
BEGIN
    UPDATE customers SET updated_at = datetime('now') WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_leads_updated
    AFTER UPDATE ON leads
    FOR EACH ROW
BEGIN
    UPDATE leads SET updated_at = datetime('now') WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_deals_updated
    AFTER UPDATE ON deals
    FOR EACH ROW
BEGIN
    UPDATE deals SET updated_at = datetime('now') WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_orders_updated
    AFTER UPDATE ON orders
    FOR EACH ROW
BEGIN
    UPDATE orders SET updated_at = datetime('now') WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_order_items_updated
    AFTER UPDATE ON order_items
    FOR EACH ROW
BEGIN
    UPDATE order_items SET updated_at = datetime('now') WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_follow_ups_updated
    AFTER UPDATE ON follow_ups
    FOR EACH ROW
BEGIN
    UPDATE follow_ups SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- ---------------------------------------------------------------
-- Seed Data
-- NOTE: seeds are idempotent (INSERT OR IGNORE). New tables added to schema.sql
-- are picked up automatically by `php database/migrate.php` on existing DBs.

-- 1. Default admin login: admin@example.com / password
INSERT OR IGNORE INTO users (id, name, email, password, role) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$bStQzLf6y2u8oGLFPaP5oeX5IR.5IVsE8.yhYMxCpyKJVDMjCjvNO', 'admin');

-- 1b. App settings defaults (only inserted once; later edits are kept,
--     because INSERT OR IGNORE never overwrites an existing row).
--     Anything missing here falls back to Setting::defaults() at read time.
INSERT OR IGNORE INTO app_settings (name, value) VALUES
('app_name',        '叁程 CRM'),
('app_tagline',     '线索 · 商机 · 客户，一段不落'),
('company_name',    ''),
('copyright_notice','© 2026 wayne · 叁程 CRM (Triphase CRM)'),
('currency_symbol', '$');

-- 2. Sample customers
INSERT OR IGNORE INTO customers (id, name, company, email, phone, whatsapp, source_country, source_city, first_purchase_from_china, has_import_capability, status, owner_id) VALUES
(1, 'Jane Cooper', 'Acme Corp', 'jane@acme.com', '555-0101', '+1-555-0101', 'United States', 'New York', 0, 1, 'active', 1),
(2, 'Robert Fox', 'Globex Inc', 'robert@globex.com', '555-0102', '+1-555-0102', 'Canada', 'Toronto', 1, 1, 'active', 1),
(3, 'Wade Warren', 'Initech', 'wade@initech.com', '555-0103', '+1-555-0103', 'United Kingdom', 'London', 0, 1, 'inactive', 1);

-- 3. Sample leads
INSERT OR IGNORE INTO leads (id, title, company, contact_name, contact_email, phone, whatsapp, source, source_country, status, value, owner_id) VALUES
(1, 'Website inquiry - CRM upgrade', 'Acme Corp', 'Jane Cooper', 'jane@acme.com', '555-0101', '+1-555-0101', 'Website', 'United States', 'new', 5000.00, 1),
(2, 'Referral - support contract', 'Globex Inc', 'Robert Fox', 'robert@globex.com', '555-0102', '+1-555-0102', 'Referral', 'Canada', 'contacted', 8000.00, 1);

-- 4. Sample deals
INSERT OR IGNORE INTO deals (id, title, customer_id, value, stage, close_date, stage_open_at, stage_proposal_at, owner_id) VALUES
(1, 'Acme Corp - Annual License', 1, 12000.00, 'proposal', date('now', '+30 days'), datetime('now'), datetime('now'), 1),
(2, 'Globex Inc - Support Renewal', 2, 6000.00, 'open', date('now', '+45 days'), datetime('now'), NULL, 1);

-- 5. Sample orders
INSERT OR IGNORE INTO orders (id, order_number, deal_id, customer_id, title, amount, status, payment_status, order_date, delivery_date, owner_id) VALUES
(1, 'ORD-2024-001', 1, 1, 'Acme Corp - Annual License Order', 12000.00, 'confirmed', 'partial', date('now', '-10 days'), date('now', '+20 days'), 1),
(2, 'ORD-2024-002', 2, 2, 'Globex Inc - Support Renewal Order', 6000.00, 'pending', 'unpaid', date('now', '-5 days'), date('now', '+40 days'), 1);

-- 6. Sample order items
INSERT OR IGNORE INTO order_items (id, order_id, product_name, sku, quantity, unit_price, subtotal, unit, sort_order) VALUES
(1, 1, 'CRM企业版年度授权', 'CRM-ENT-001', 1, 8000.00, 8000.00, '套', 1),
(2, 1, '实施部署服务', 'SVC-IMP-001', 1, 2500.00, 2500.00, '次', 2),
(3, 1, '培训服务（3天）', 'SVC-TRN-001', 3, 500.00, 1500.00, '天', 3),
(4, 2, '技术支持年度合同', 'SUP-ANN-001', 1, 5000.00, 5000.00, '年', 1),
(5, 2, '7x24紧急响应服务', 'SUP-EMR-001', 1, 1000.00, 1000.00, '年', 2);

-- 7. Sample follow-ups
INSERT OR IGNORE INTO follow_ups (id, customer_id, user_id, type, title, description, next_action, next_date) VALUES
(1, 1, 1, 'price_comparison', 'Initial Quote Comparison', 'Customer compared our quote against competitor A', 'Send customized discount proposal', date('now', '+3 days')),
(2, 2, 1, 'follow_up', 'Contract Renewal Discussion', 'Discussed annual support SLA terms', 'Send updated draft contract', date('now', '+7 days'));
