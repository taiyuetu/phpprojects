-- Migration script to add orders table to existing database
-- Run this if you have an existing database without the orders table

PRAGMA foreign_keys = ON;

-- Create orders table if it doesn't exist
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

-- Create indexes if they don't exist
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_deal ON orders(deal_id);
CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(order_date);

-- Create trigger for updated_at if it doesn't exist
CREATE TRIGGER IF NOT EXISTS trg_orders_updated
    AFTER UPDATE ON orders
    FOR EACH ROW
BEGIN
    UPDATE orders SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- Insert sample orders if the table is empty
INSERT OR IGNORE INTO orders (id, order_number, deal_id, customer_id, title, amount, status, payment_status, order_date, delivery_date, owner_id) VALUES
(1, 'ORD-2024-001', 1, 1, 'Acme Corp - Annual License Order', 12000.00, 'confirmed', 'partial', date('now', '-10 days'), date('now', '+20 days'), 1),
(2, 'ORD-2024-002', 2, 2, 'Globex Inc - Support Renewal Order', 6000.00, 'pending', 'unpaid', date('now', '-5 days'), date('now', '+40 days'), 1);

-- Create order_items table
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

CREATE TRIGGER IF NOT EXISTS trg_order_items_updated
    AFTER UPDATE ON order_items
    FOR EACH ROW
BEGIN
    UPDATE order_items SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- Insert sample order items
INSERT OR IGNORE INTO order_items (id, order_id, product_name, sku, quantity, unit_price, subtotal, unit, sort_order) VALUES
(1, 1, 'CRM企业版年度授权', 'CRM-ENT-001', 1, 8000.00, 8000.00, '套', 1),
(2, 1, '实施部署服务', 'SVC-IMP-001', 1, 2500.00, 2500.00, '次', 2),
(3, 1, '培训服务（3天）', 'SVC-TRN-001', 3, 500.00, 1500.00, '天', 3),
(4, 2, '技术支持年度合同', 'SUP-ANN-001', 1, 5000.00, 5000.00, '年', 1),
(5, 2, '7x24紧急响应服务', 'SUP-EMR-001', 1, 1000.00, 1000.00, '年', 2);
