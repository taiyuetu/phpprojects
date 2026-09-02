-- ===============================================================
-- MiniCRM Complete Database Schema (SQLite)
-- Usage: sqlite3 database/crm.sqlite < database/schema.sql
-- ===============================================================

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- ---------------------------------------------------------------
-- 1. Users (CRM staff who log in)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    password   TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'sales' CHECK (role IN ('admin','sales')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
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
    facebook                  TEXT,
    tiktok                    TEXT,
    website                   TEXT,
    source_country            TEXT,
    source_city               TEXT,
    address                   TEXT,
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
    owner_id              INTEGER,
    created_at            TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at            TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_deals_stage ON deals(stage);

-- ---------------------------------------------------------------
-- 5. Follow-ups (follow-up records / price comparison tracking)
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
-- 6. Activities (notes / timeline entries logged against a customer)
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

CREATE TRIGGER IF NOT EXISTS trg_follow_ups_updated
    AFTER UPDATE ON follow_ups
    FOR EACH ROW
BEGIN
    UPDATE follow_ups SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- ---------------------------------------------------------------
-- Seed Data
-- ---------------------------------------------------------------

-- 1. Default admin login: admin@example.com / password
INSERT OR IGNORE INTO users (id, name, email, password, role) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$bStQzLf6y2u8oGLFPaP5oeX5IR.5IVsE8.yhYMxCpyKJVDMjCjvNO', 'admin');

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

-- 5. Sample follow-ups
INSERT OR IGNORE INTO follow_ups (id, customer_id, user_id, type, title, description, next_action, next_date) VALUES
(1, 1, 1, 'price_comparison', 'Initial Quote Comparison', 'Customer compared our quote against competitor A', 'Send customized discount proposal', date('now', '+3 days')),
(2, 2, 1, 'follow_up', 'Contract Renewal Discussion', 'Discussed annual support SLA terms', 'Send updated draft contract', date('now', '+7 days'));
