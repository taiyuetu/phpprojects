-- PSI System — Sample Data Dump
-- Generated: 2026-08-31 14:11:01
-- This file contains the full schema + seed data for a working demo.
-- Usage: sqlite3 database/database.sqlite < database/sample_data.sql

-- PSI System schema. Written to run on SQLite as-is.
-- (For MySQL: change AUTOINCREMENT -> AUTO_INCREMENT, INTEGER PK -> INT, TEXT -> VARCHAR as needed.)

CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    password   TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'staff',   -- admin | staff
    attributes TEXT DEFAULT '{}',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    attributes TEXT DEFAULT '{}',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    phone      TEXT,
    email      TEXT,
    address    TEXT,
    attributes TEXT DEFAULT '{}',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    phone      TEXT,
    email      TEXT,
    address    TEXT,
    attributes TEXT DEFAULT '{}',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    sku           TEXT NOT NULL UNIQUE,
    name          TEXT NOT NULL,
    category_id   INTEGER,
    unit          TEXT NOT NULL DEFAULT 'pcs',
    cost_price    REAL NOT NULL DEFAULT 0,
    sale_price    REAL NOT NULL DEFAULT 0,
    quantity      INTEGER NOT NULL DEFAULT 0,
    reorder_level INTEGER NOT NULL DEFAULT 0,
    gallery       TEXT DEFAULT '[]',
    attributes    TEXT DEFAULT '{}',
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchases (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no            TEXT NOT NULL UNIQUE,
    supplier_id           INTEGER NOT NULL,
    purchase_date         TEXT NOT NULL,
    expected_arrival_date TEXT,
    notes                 TEXT DEFAULT '',
    total                 REAL NOT NULL DEFAULT 0,
    attributes            TEXT DEFAULT '{}',
    created_by            INTEGER,
    created_at            TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS purchase_arrivals (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id   INTEGER NOT NULL,
    arrival_date  TEXT NOT NULL,
    qty           INTEGER NOT NULL,
    notes         TEXT DEFAULT '',
    created_by    INTEGER,
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS purchase_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    product_id  INTEGER NOT NULL,
    qty         INTEGER NOT NULL,
    unit_cost   REAL NOT NULL,
    subtotal    REAL NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE IF NOT EXISTS sales (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no  TEXT NOT NULL UNIQUE,
    customer_id INTEGER,
    sale_date   TEXT NOT NULL,
    total       REAL NOT NULL DEFAULT 0,
    attributes  TEXT DEFAULT '{}',
    created_by  INTEGER,
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS sale_items (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id    INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    qty        INTEGER NOT NULL,
    unit_price REAL NOT NULL,
    subtotal   REAL NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Full audit trail of every stock movement, regardless of source.
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id    INTEGER NOT NULL,
    type          TEXT NOT NULL,      -- purchase | sale | adjustment
    qty_change    INTEGER NOT NULL,   -- positive or negative
    balance_after INTEGER NOT NULL,
    reference     TEXT,               -- e.g. invoice number
    notes         TEXT,
    attributes    TEXT DEFAULT '{}',
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

--通用变更日志表，记录所有表的变更历史
CREATE TABLE IF NOT EXISTS change_logs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name    TEXT NOT NULL,      -- 表名
    record_id     INTEGER NOT NULL,   -- 记录ID
    action        TEXT NOT NULL,      -- create | update | delete
    old_data      TEXT,               -- 变更前数据（JSON）
    new_data      TEXT,               -- 变更后数据（JSON）
    user_id       INTEGER,            -- 操作用户ID
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


-- Table: users (1 rows)
INSERT OR IGNORE INTO users (id, name, email, password, role, created_at, attributes) VALUES ('1', 'Administrator', 'admin@psi.local', '$2y$10$EPGzx7zpBRwhUfF0oQbP0ewNDZGmZSu1F/lWMRB6sL80mhplvgir2', 'admin', '2026-08-28 14:11:18', '{}');

-- Table: categories (4 rows)
INSERT OR IGNORE INTO categories (id, name, attributes, created_at) VALUES ('1', 'General', '{}', '2026-08-28 14:11:18');
INSERT OR IGNORE INTO categories (id, name, attributes, created_at) VALUES ('2', 'Electronics', '{}', '2026-08-28 14:11:18');
INSERT OR IGNORE INTO categories (id, name, attributes, created_at) VALUES ('3', 'Stationery', '{}', '2026-08-28 14:11:18');
INSERT OR IGNORE INTO categories (id, name, attributes, created_at) VALUES ('4', 'hub bearing', '[]', '2026-08-28 14:13:37');

-- Table: suppliers (1 rows)
INSERT OR IGNORE INTO suppliers (id, name, phone, email, address, attributes, created_at) VALUES ('1', 'Default Supplier Co.', '555-0100', 'sales@supplier.example', NULL, '{}', '2026-08-28 14:11:18');

-- Table: customers (1 rows)
INSERT OR IGNORE INTO customers (id, name, phone, email, address, attributes, created_at) VALUES ('1', 'Walk-in Customer', '', '', NULL, '{}', '2026-08-28 14:11:18');

-- Table: products (3 rows)
INSERT OR IGNORE INTO products (id, sku, name, category_id, unit, cost_price, sale_price, quantity, reorder_level, gallery, attributes, created_at) VALUES ('1', 'tqb1213', 'hub bearing 12', NULL, 'pcs', '0', '0', '3', '0', '[]', '[]', '2026-08-28 14:13:26');
INSERT OR IGNORE INTO products (id, sku, name, category_id, unit, cost_price, sale_price, quantity, reorder_level, gallery, attributes, created_at) VALUES ('2', 'tqb3322', 'toyota hub bearing', '4', 'pcs', '0', '0', '3', '0', '[]', '[]', '2026-08-28 14:17:17');
INSERT OR IGNORE INTO products (id, sku, name, category_id, unit, cost_price, sale_price, quantity, reorder_level, gallery, attributes, created_at) VALUES ('3', '931123', 'cpl bearing', '4', 'pcs', '20', '0', '200', '0', '["product_1787926665_49e6d8eb.png","product_1787926692_3cde6fe1.png","product_1787926692_d4382c34.png","product_1787926692_0042c9d4.png","product_1787926699_4577f7c7.webp","product_1787926710_7c27fa60.png"]', '[]', '2026-08-28 14:17:45');

-- Table: purchases (2 rows)
INSERT OR IGNORE INTO purchases (id, invoice_no, supplier_id, purchase_date, total, created_by, created_at, expected_arrival_date, attributes) VALUES ('1', 'PO-20260828-0001', '1', '2026-08-28', '100', '1', '2026-08-28 14:40:18', NULL, '{}');
INSERT OR IGNORE INTO purchases (id, invoice_no, supplier_id, purchase_date, total, created_by, created_at, expected_arrival_date, attributes) VALUES ('2', 'PO-20260828-0002', '1', '2026-08-28', '100', '1', '2026-08-28 14:46:40', NULL, '{}');

-- Table: purchase_arrivals (1 rows)
INSERT OR IGNORE INTO purchase_arrivals (id, purchase_id, arrival_date, qty, notes, created_by, created_at) VALUES ('1', '1', '2026-08-29', '1', '', '1', '2026-08-29 14:49:18');

-- Table: purchase_items (3 rows)
INSERT OR IGNORE INTO purchase_items (id, purchase_id, product_id, qty, unit_cost, subtotal) VALUES ('1', '1', '1', '2', '50', '100');
INSERT OR IGNORE INTO purchase_items (id, purchase_id, product_id, qty, unit_cost, subtotal) VALUES ('2', '1', '2', '2', '0', '0');
INSERT OR IGNORE INTO purchase_items (id, purchase_id, product_id, qty, unit_cost, subtotal) VALUES ('3', '2', '1', '1', '100', '100');

-- Table: sales (1 rows)
INSERT OR IGNORE INTO sales (id, invoice_no, customer_id, sale_date, total, created_by, created_at, attributes) VALUES ('1', 'INV-20260828-0001', NULL, '2026-08-28', '200', '1', '2026-08-28 14:41:20', '{}');

-- Table: sale_items (1 rows)
INSERT OR IGNORE INTO sale_items (id, sale_id, product_id, qty, unit_price, subtotal) VALUES ('1', '1', '1', '1', '200', '200');

-- Table: inventory_transactions (7 rows)
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('1', '1', 'purchase', '2', '2', 'PO-20260828-0001', 'Purchase #PO-20260828-0001', '2026-08-28 14:40:18', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('2', '2', 'purchase', '2', '2', 'PO-20260828-0001', 'Purchase #PO-20260828-0001', '2026-08-28 14:40:18', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('3', '1', 'sale', '-1', '1', 'INV-20260828-0001', 'Sale #INV-20260828-0001', '2026-08-28 14:41:20', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('4', '1', 'purchase', '1', '2', 'PO-20260828-0002', 'Purchase #PO-20260828-0002', '2026-08-28 14:46:40', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('5', '3', 'adjustment', '200', '200', 'Manual edit', 'Quantity corrected via product edit form', '2026-08-28 14:47:15', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('6', '1', 'purchase_arrival', '1', '3', 'PO-20260828-0001', 'Purchase Arrival #PO-20260828-0001 (Batch)', '2026-08-29 14:49:18', '{}');
INSERT OR IGNORE INTO inventory_transactions (id, product_id, type, qty_change, balance_after, reference, notes, created_at, attributes) VALUES ('7', '2', 'purchase_arrival', '1', '3', 'PO-20260828-0001', 'Purchase Arrival #PO-20260828-0001 (Batch)', '2026-08-29 14:49:18', '{}');

-- Table: change_logs (26 rows)
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('1', 'products', '1', 'create', NULL, '{"sku":"tqb1213","name":"hub bearing 12","category_id":null,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[]"}', '1', '2026-08-28 14:13:26');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('2', 'categories', '4', 'create', NULL, '{"name":"hub bearing","attributes":"[]"}', '1', '2026-08-28 14:13:37');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('3', 'products', '2', 'create', NULL, '{"sku":"tqb3322","name":"toyota hub bearing","category_id":"4","unit":"pcs","cost_price":0,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[]"}', '1', '2026-08-28 14:17:17');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('4', 'products', '3', 'create', NULL, '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\"]"}', '1', '2026-08-28 14:17:45');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('5', 'products', '3', 'update', '{"gallery":"[\"product_1787926665_49e6d8eb.png\"]","created_at":"2026-08-28 14:17:45"}', '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\"]","id":"3"}', '1', '2026-08-28 14:18:12');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('6', 'products', '3', 'update', '{"gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\"]","created_at":"2026-08-28 14:17:45"}', '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\",\"product_1787926699_4577f7c7.webp\"]","id":"3"}', '1', '2026-08-28 14:18:19');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('7', 'products', '3', 'update', '{"gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\",\"product_1787926699_4577f7c7.webp\"]","created_at":"2026-08-28 14:17:45"}', '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\",\"product_1787926699_4577f7c7.webp\",\"product_1787926710_7c27fa60.png\"]","id":"3"}', '1', '2026-08-28 14:18:30');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('8', 'products', '3', 'update', '{"created_at":"2026-08-28 14:17:45"}', '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":0,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\",\"product_1787926699_4577f7c7.webp\",\"product_1787926710_7c27fa60.png\"]","id":"3"}', '1', '2026-08-28 14:18:41');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('9', 'purchases', '1', 'create', NULL, '{"invoice_no":"PO-20260828-0001","supplier_id":1,"purchase_date":"2026-08-28","created_by":1,"total":100}', '1', '2026-08-28 14:40:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('10', 'products', '1', 'update', '{"sku":"tqb1213","name":"hub bearing 12","category_id":null,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":0,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:13:26"}', '{"quantity":2,"id":1}', '1', '2026-08-28 14:40:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('11', 'inventory_transactions', '1', 'create', NULL, '{"product_id":1,"type":"purchase","qty_change":2,"balance_after":2,"reference":"PO-20260828-0001","notes":"Purchase #PO-20260828-0001"}', '1', '2026-08-28 14:40:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('12', 'products', '2', 'update', '{"sku":"tqb3322","name":"toyota hub bearing","category_id":4,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":0,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:17:17"}', '{"quantity":2,"id":2}', '1', '2026-08-28 14:40:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('13', 'inventory_transactions', '2', 'create', NULL, '{"product_id":2,"type":"purchase","qty_change":2,"balance_after":2,"reference":"PO-20260828-0001","notes":"Purchase #PO-20260828-0001"}', '1', '2026-08-28 14:40:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('14', 'sales', '1', 'create', NULL, '{"invoice_no":"INV-20260828-0001","customer_id":null,"sale_date":"2026-08-28","created_by":1,"total":200}', '1', '2026-08-28 14:41:20');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('15', 'products', '1', 'update', '{"sku":"tqb1213","name":"hub bearing 12","category_id":null,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":2,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:13:26"}', '{"quantity":1,"id":1}', '1', '2026-08-28 14:41:20');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('16', 'inventory_transactions', '3', 'create', NULL, '{"product_id":1,"type":"sale","qty_change":-1,"balance_after":1,"reference":"INV-20260828-0001","notes":"Sale #INV-20260828-0001"}', '1', '2026-08-28 14:41:20');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('17', 'purchases', '2', 'create', NULL, '{"invoice_no":"PO-20260828-0002","supplier_id":1,"purchase_date":"2026-08-28","created_by":1,"total":100}', '1', '2026-08-28 14:46:40');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('18', 'products', '1', 'update', '{"sku":"tqb1213","name":"hub bearing 12","category_id":null,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":1,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:13:26"}', '{"quantity":2,"id":1}', '1', '2026-08-28 14:46:40');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('19', 'inventory_transactions', '4', 'create', NULL, '{"product_id":1,"type":"purchase","qty_change":1,"balance_after":2,"reference":"PO-20260828-0002","notes":"Purchase #PO-20260828-0002"}', '1', '2026-08-28 14:46:40');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('20', 'products', '3', 'update', '{"quantity":0,"created_at":"2026-08-28 14:17:45"}', '{"sku":"931123","name":"cpl bearing","category_id":"4","unit":"pcs","cost_price":20,"sale_price":0,"quantity":200,"reorder_level":0,"attributes":"[]","gallery":"[\"product_1787926665_49e6d8eb.png\",\"product_1787926692_3cde6fe1.png\",\"product_1787926692_d4382c34.png\",\"product_1787926692_0042c9d4.png\",\"product_1787926699_4577f7c7.webp\",\"product_1787926710_7c27fa60.png\"]","id":"3"}', '1', '2026-08-28 14:47:15');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('21', 'inventory_transactions', '5', 'create', NULL, '{"product_id":"3","type":"adjustment","qty_change":200,"balance_after":200,"reference":"Manual edit","notes":"Quantity corrected via product edit form"}', '1', '2026-08-28 14:47:15');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('22', 'purchase_arrivals', '1', 'create', NULL, '{"purchase_id":1,"arrival_date":"2026-08-29","qty":1,"notes":"","created_by":1}', '1', '2026-08-29 14:49:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('23', 'products', '1', 'update', '{"sku":"tqb1213","name":"hub bearing 12","category_id":null,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":2,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:13:26"}', '{"quantity":3,"id":1}', '1', '2026-08-29 14:49:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('24', 'inventory_transactions', '6', 'create', NULL, '{"product_id":1,"type":"purchase_arrival","qty_change":1,"balance_after":3,"reference":"PO-20260828-0001","notes":"Purchase Arrival #PO-20260828-0001 (Batch)"}', '1', '2026-08-29 14:49:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('25', 'products', '2', 'update', '{"sku":"tqb3322","name":"toyota hub bearing","category_id":4,"unit":"pcs","cost_price":0,"sale_price":0,"quantity":2,"reorder_level":0,"gallery":"[]","attributes":"[]","created_at":"2026-08-28 14:17:17"}', '{"quantity":3,"id":2}', '1', '2026-08-29 14:49:18');
INSERT OR IGNORE INTO change_logs (id, table_name, record_id, action, old_data, new_data, user_id, created_at) VALUES ('26', 'inventory_transactions', '7', 'create', NULL, '{"product_id":2,"type":"purchase_arrival","qty_change":1,"balance_after":3,"reference":"PO-20260828-0001","notes":"Purchase Arrival #PO-20260828-0001 (Batch)"}', '1', '2026-08-29 14:49:18');

