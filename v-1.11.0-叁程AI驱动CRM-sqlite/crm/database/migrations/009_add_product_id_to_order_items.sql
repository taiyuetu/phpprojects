-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
-- 009：订单明细关联商品库
-- 只有一句 ALTER，所以新库（基线已含该列）会被 migrate.php 自动判为 skipped。
-- product_id 可空：历史明细保留自由填写的文本快照，由 010 尽力回填；
-- 删除商品时 ON DELETE SET NULL（外键只在基线建表时生效，老库由应用层保证）。
ALTER TABLE order_items ADD COLUMN product_id INTEGER;
