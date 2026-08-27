-- PSI System schema. Written to run on SQLite as-is.
-- (For MySQL: change AUTOINCREMENT -> AUTO_INCREMENT, INTEGER PK -> INT, TEXT -> VARCHAR as needed.)

CREATE TABLE IF NOT EXISTS users (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    password   TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'staff',   -- admin | staff
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL UNIQUE,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    phone      TEXT,
    email      TEXT,
    address    TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    phone      TEXT,
    email      TEXT,
    address    TEXT,
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
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchases (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_no  TEXT NOT NULL UNIQUE,
    supplier_id INTEGER NOT NULL,
    purchase_date TEXT NOT NULL,
    total       REAL NOT NULL DEFAULT 0,
    created_by  INTEGER,
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
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
