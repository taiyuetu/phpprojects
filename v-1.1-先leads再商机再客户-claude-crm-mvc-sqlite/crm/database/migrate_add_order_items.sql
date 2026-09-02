-- Migration script to add order_items table to existing database
-- Run: sqlite3 database/crm.sqlite < database/migrate_add_order_items.sql

PRAGMA foreign_keys = ON;

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

-- Create trigger for updated_at
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
