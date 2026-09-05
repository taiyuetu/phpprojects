-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
-- 008：商品主数据表 products
-- 表结构与 database/schema.sql 完全一致（基线仍是唯一真相，这里只为已有库补表）。
-- SKU 用局部唯一索引：不填不受约束，填了就不能与别人重号。
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
CREATE UNIQUE INDEX IF NOT EXISTS uidx_products_sku ON products(sku) WHERE sku IS NOT NULL AND sku <> '';
CREATE INDEX IF NOT EXISTS idx_products_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_products_name    ON products(name);
