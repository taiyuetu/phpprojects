-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
-- 010：把历史上手写的明细名导入商品库，并把明细链回去
-- 不是“纯 ADD COLUMN”文件，所以新库也会执行一次（每条语句都幂等，可重复跑）。
-- 为什么放在迁移里：不导入的话，老库已有明细全是“未关联商品”，
-- 页面一片黄标、AI 也搜不到任何商品，商品库等于白建。
-- 价格取该组合里**最新一条**的成交价（比平均值更贴近现状）。
-- 注意 GROUP BY 之后不能再写 WHERE，所以先去重、再在外层用 NOT EXISTS 过滤已存在的商品。

INSERT INTO products (name, sku, unit, price, status, notes, owner_id)
SELECT src.name, src.sku, src.unit, src.price, 'active', src.notes, src.owner_id
  FROM (
    SELECT oi.product_name AS name,
           MAX(oi.sku)     AS sku,
           MAX(oi.unit)    AS unit,
           (SELECT o2.unit_price FROM order_items o2
             WHERE o2.product_name = oi.product_name AND IFNULL(o2.sku,'') = IFNULL(oi.sku,'')
             ORDER BY o2.id DESC LIMIT 1) AS price,
           '由历史订单明细自动导入（迁移 010），建议补上规格/分类' AS notes,
           (SELECT o3.owner_id FROM order_items o4 JOIN orders o3 ON o3.id = o4.order_id
             WHERE o4.product_name = oi.product_name AND IFNULL(o4.sku,'') = IFNULL(oi.sku,'')
             ORDER BY o4.id DESC LIMIT 1) AS owner_id,
           IFNULL(oi.sku,'') AS sku_key
      FROM order_items oi
     GROUP BY oi.product_name, IFNULL(oi.sku,'')
  ) AS src
 WHERE NOT EXISTS (SELECT 1 FROM products p
                    WHERE p.name = src.name AND IFNULL(p.sku,'') = src.sku_key);

-- 链回去：同名多条商品取 id 最小的那条，保证结果稳定可重复
UPDATE order_items
   SET product_id = (SELECT MIN(p.id) FROM products p
                      WHERE p.name = order_items.product_name
                        AND IFNULL(p.sku,'') = IFNULL(order_items.sku,''))
 WHERE product_id IS NULL
   AND EXISTS (SELECT 1 FROM products p2
                WHERE p2.name = order_items.product_name
                  AND IFNULL(p2.sku,'') = IFNULL(order_items.sku,''));

-- 编号：与 007 同一套规则（前缀 + 六位 id），所以与 Model::publicCode() 派生结果一致
UPDATE products SET public_code = 'PROD-' || printf('%06d', id)
 WHERE public_code IS NULL OR public_code = '';

-- 明细索引（不能写在基线：老库重放基线时还没有 product_id 列）
CREATE INDEX IF NOT EXISTS idx_order_items_product ON order_items(product_id);
