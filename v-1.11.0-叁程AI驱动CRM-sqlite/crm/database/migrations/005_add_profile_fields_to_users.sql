-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- Add editable profile fields to users (Settings → 个人信息).
--
-- Everything here is nullable with no default, because SQLite rejects
-- "ALTER TABLE ... ADD COLUMN ... DEFAULT (datetime('now'))".
--
-- These columns are also present in the canonical schema.sql baseline, so on a
-- FRESH database migrate.php prints
--   skipped: 005_add_profile_fields_to_users.sql (column(s) already present in baseline)
-- and only registers the file; on a pre-v1.3.0 database the ALTERs run for real.

ALTER TABLE users ADD COLUMN phone TEXT;
ALTER TABLE users ADD COLUMN whatsapp TEXT;
ALTER TABLE users ADD COLUMN job_title TEXT;
ALTER TABLE users ADD COLUMN notes TEXT;
ALTER TABLE users ADD COLUMN updated_at TEXT;
