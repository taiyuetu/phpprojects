-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 006：给 客户 / 线索 / 商机 三张既有表加「稳定编号」列 public_code。
--
-- 通篇只有 ADD COLUMN，所以全新数据库（基线已含该列）会被 migrate.php 自动跳过、仅登记；
-- 老数据库则在这里补列。编号的唯一性索引与历史行回填放在 007，
-- 因为 SQLite 的 ADD COLUMN 不能带 UNIQUE，且必须先有列才能建索引、先有值才能建唯一索引。

ALTER TABLE customers ADD COLUMN public_code TEXT;
ALTER TABLE leads     ADD COLUMN public_code TEXT;
ALTER TABLE deals     ADD COLUMN public_code TEXT;