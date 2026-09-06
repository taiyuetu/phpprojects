-- 013：商品表挂到分类主数据（categories 见 schema.sql 基线 / 旧库由基线自愈补表）
-- 增量只做“纯加列”：新库基线已含 category_id → migrate.php 自动跳过；
-- 旧库缺列 → 执行本语句。分类回填与同步触发器见 014。
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

ALTER TABLE products ADD COLUMN category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL;
