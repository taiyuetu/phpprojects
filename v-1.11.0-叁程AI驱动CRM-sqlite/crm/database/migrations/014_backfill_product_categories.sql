-- 014：把历史上 products.category 自由文本收编成分类主数据，并保证文本列与下拉选择永远同步。
-- 全幂等（INSERT OR IGNORE / 条件 UPDATE / IF NOT EXISTS），任何库上跑多遍都安全。
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 依赖 category_id 的索引/触发器放这里而不是基线：旧库在 013 加列之前，基线重放不能引用这一列。
CREATE INDEX IF NOT EXISTS idx_products_category_id ON products(category_id);

-- 1) 老分类文本 → categories 表（不重复）
INSERT OR IGNORE INTO categories (name, sort_order)
SELECT DISTINCT category, 0
  FROM products
 WHERE category IS NOT NULL AND category <> '';

-- 2) 老商品按文本挂到新分类（已有 category_id 的不动）
UPDATE products
   SET category_id = (SELECT c.id FROM categories c WHERE c.name = products.category)
 WHERE category_id IS NULL AND category IS NOT NULL AND category <> '';

-- 3) 漂移修复：以分类表为准刷新文本列（新库演示数据也能被对上号）
UPDATE products
   SET category = (SELECT c.name FROM categories c WHERE c.id = products.category_id)
 WHERE category_id IS NOT NULL;

-- 4) 同步触发器：文本列永远是分类名的镜像
CREATE TRIGGER IF NOT EXISTS trg_products_sync_category_insert
    AFTER INSERT ON products
    FOR EACH ROW WHEN NEW.category_id IS NOT NULL
BEGIN
    UPDATE products SET category = (SELECT name FROM categories WHERE id = NEW.category_id)
     WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_products_sync_category_update
    AFTER UPDATE OF category_id ON products
    FOR EACH ROW
BEGIN
    UPDATE products
       SET category = COALESCE((SELECT name FROM categories WHERE id = NEW.category_id), '')
     WHERE id = OLD.id;
END;

CREATE TRIGGER IF NOT EXISTS trg_categories_sync_product_text
    AFTER UPDATE OF name ON categories
    FOR EACH ROW
BEGIN
    UPDATE products SET category = NEW.name WHERE category_id = OLD.id;
END;
