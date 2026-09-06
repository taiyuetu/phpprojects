-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 007：回填 public_code 并加唯一索引。
--
-- 编号规则与 Model::publicCode() 完全一致：前缀 + 六位 id（CUS-000007 / LEAD-000007 / DEAL-000007）。
-- 由 id 派生 ⇒ 天然唯一、可复现、可口语传达；AI 若编一个不存在的编号，解析阶段就会被拒。
-- 只填 NULL，所以重复执行与增量执行都安全（本文件也只会执行一次）。

UPDATE customers SET public_code = 'CUS-'  || printf('%06d', id) WHERE public_code IS NULL OR public_code = '';
UPDATE leads     SET public_code = 'LEAD-' || printf('%06d', id) WHERE public_code IS NULL OR public_code = '';
UPDATE deals     SET public_code = 'DEAL-' || printf('%06d', id) WHERE public_code IS NULL OR public_code = '';

CREATE UNIQUE INDEX IF NOT EXISTS uidx_customers_public_code ON customers(public_code);
CREATE UNIQUE INDEX IF NOT EXISTS uidx_leads_public_code     ON leads(public_code);
CREATE UNIQUE INDEX IF NOT EXISTS uidx_deals_public_code     ON deals(public_code);