-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 商机“未成交阶段”的明细行草稿（JSON）。成交后由系统清空，明细交给自动生成的订单。
-- 该列同样声明在基线 schema.sql 的 deals 表里：
--   全新库由基线建好 → 本文件自动 skipped；
--   老库缺列 → 本文件真实执行 ALTER（见 database/migrations/README.md 的约定）。
ALTER TABLE deals ADD COLUMN draft_items TEXT;
