-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- 015：回填 leads.lead_time（线索时间）。
--
-- 线索时间这一列一直没人写：页面上是选填，AI 建线索时也只当它不存在，于是列表与详情
-- 里这一栏全空。现在新建线索由系统按上海时间落当前时刻（见 Ai::kindInfo 的 defaults），
-- 历史数据在这里补齐。
--
-- 时区口径：created_at 是 SQLite 的 datetime('now')，那是 UTC；PHP 侧写的时间是上海时间。
-- 所以要 +8 小时换算，让 lead_time 与 lost_at / conversion_time 同一口径（都是 +08:00 的墙上时间）。
--
-- 只填 NULL/空串 ⇒ 重复执行安全，也不会覆盖用户手工填过的值。

UPDATE leads
   SET lead_time = datetime(created_at, '+8 hours')
 WHERE lead_time IS NULL OR lead_time = '';
