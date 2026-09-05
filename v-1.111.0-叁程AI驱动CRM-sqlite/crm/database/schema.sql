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
    -- 稳定编号：AI 与人工引用记录时用它，比裸 id 好念也好核对（见 Model::publicCode()）
    public_code               TEXT,
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
-- 2b. Products (商品主数据 / 目录)
-- ---------------------------------------------------------------
-- 商机的明细与订单的明细都应从这里选定，而不是每人每次手挨商品名：
-- 同一个商品被不同人写成“6206 轴承 / 深沟球轴承 6206 / bearing 6206”时，
-- 报价、销量、对账全部失算。order_items 保留名称/价格快照（见下），
-- 所以商品后续改价不会改写历史订单。
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    public_code  TEXT,
    name         TEXT NOT NULL,
    sku          TEXT,
    category     TEXT,
    brand        TEXT,
    spec         TEXT,
    unit         TEXT NOT NULL DEFAULT '件',
    price        REAL NOT NULL DEFAULT 0.00,
    cost         REAL,
    status       TEXT NOT NULL DEFAULT 'active'
                 CHECK (status IN ('active','inactive')),
    notes        TEXT,
    owner_id     INTEGER,
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uidx_products_public_code ON products(public_code);
-- 局部唯一索引：没填 SKU 的商品不受约束，填了就不能与别人重号
CREATE UNIQUE INDEX IF NOT EXISTS uidx_products_sku ON products(sku) WHERE sku IS NOT NULL AND sku <> '';
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_products_name    ON products(name);

-- ---------------------------------------------------------------
-- 3. Leads (prospective customers, pre-sale)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    public_code               TEXT,
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
    public_code           TEXT,
    title                 TEXT NOT NULL,
    customer_id           INTEGER NOT NULL,
    value                 REAL NOT NULL DEFAULT 0.00,
    stage                 TEXT NOT NULL DEFAULT 'open' CHECK (stage IN ('open','proposal','negotiation','closed_won','closed_lost')),
    close_date            TEXT,
    -- 未成交阶段的明细行草稿（JSON；成交后清空，明细交给自动生成的订单）
    draft_items           TEXT,
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
    -- product_id 指向商品库；product_name/sku/unit/unit_price 是成交时的快照，
    -- 因为商品今天改价不能改写已经签出去的订单。
    product_id  INTEGER,
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
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
-- idx_order_items_product 不在基线里建：基线每次迁移都会先重放，而老库的 product_id
-- 要等增量 009 的 ALTER 才存在，这里建索引会直接报 no such column。见 010。

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
-- 10. AI assistant audit trail (every instruction + plan + result)
-- ---------------------------------------------------------------
-- The AI never writes business data directly: it returns a plan of tool calls
-- (plan_json) which PHP validates against Ai::tools() and the caller's own
-- permissions, and — unless 自动执行 is turned on — only executes after the
-- user confirms. This table is the paper trail for all of that.
CREATE TABLE IF NOT EXISTS ai_actions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER,
    instruction  TEXT NOT NULL,
    reply        TEXT,
    plan_json    TEXT NOT NULL,
    result_json  TEXT,
    status       TEXT NOT NULL DEFAULT 'pending'
                 CHECK (status IN ('pending','executed','cancelled','failed','invalid')),
    error        TEXT,
    provider     TEXT,
    model        TEXT,
    latency_ms   INTEGER,
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    executed_at  TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ai_actions_user ON ai_actions(user_id, created_at);

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

-- 1c. AI assistant defaults. The API key is deliberately NOT seeded here:
--     it lives in .env (AI_API_KEY, recommended) or in this table when entered
--     on the 设置 → AI 页, and is never echoed back to the browser.
INSERT OR IGNORE INTO app_settings (name, value) VALUES
('ai_enabled',     '0'),
('ai_provider',    'mock'),
('ai_model',       ''),
('ai_base_url',    ''),
('ai_mode',        'preview'),
('ai_temperature', '0.2');

-- >>> DEMO_DATA_BEGIN >>>
-- 以下为演示用业务样例数据（商品 / 客户 / 线索 / 商机 / 订单 / 跟进）。
-- 生产新库可用 `php database/migrate.php --no-demo` 或环境变量 CRM_DEMO_DATA=0
-- 跳过整段（管理员账号与系统设置始终会建）。
-- 1c. Sample products (商品库：名称与下面 sample order items 对得上，
--     所以一个全新库装完就能直接“从商品里选”，不会先被空目录拦住)
--     注意：不写 public_code，与其他表一致，由 Model 自动派生/迁移回填。
-- ------------------------------------------------------------
INSERT OR IGNORE INTO products (id, name, sku, category, brand, spec, unit, price, cost, status, owner_id)
SELECT 1, 'CRM企业版年度授权', 'CRM-ENT-001', '软件授权', 'Self', '企业版 / 不限用户数 / 1 年', '套', 8000.00, 2200.00, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE id = 1);
INSERT OR IGNORE INTO products (id, name, sku, category, brand, spec, unit, price, cost, status, owner_id)
SELECT 2, '实施部署服务', 'SVC-IMP-001', '服务', 'Self', '现场部署 + 培训上岗', '次', 2500.00, 900.00, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE id = 2);
INSERT OR IGNORE INTO products (id, name, sku, category, brand, spec, unit, price, cost, status, owner_id)
SELECT 3, '培训服务（3天）', 'SVC-TRN-001', '服务', 'Self', '3 天 / 最多 10 人', '天', 500.00, 180.00, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE id = 3);
INSERT OR IGNORE INTO products (id, name, sku, category, brand, spec, unit, price, cost, status, owner_id)
SELECT 4, '技术支持年度合同', 'SUP-ANN-001', '服务', 'Self', '5x8 工单支持 / 1 年', '年', 5000.00, 1600.00, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE id = 4);
INSERT OR IGNORE INTO products (id, name, sku, category, brand, spec, unit, price, cost, status, owner_id)
SELECT 5, '7x24紧急响应服务', 'SUP-EMR-001', '服务', 'Self', '30 分钟响应 / 1 年', '年', 1000.00, 400.00, 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM products WHERE id = 5);

-- 2. Sample customers
-- 注意：种子数据不引用 public_code —— 基线每次迁移都会重放（自愈），而老库要等增量 006 才有这一列；
-- 编号统一由 007 回填（同一套 前缀 + 六位 id 规则，所以这里写不写结果一样）。
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

-- >>> DEMO_DATA_END >>>
