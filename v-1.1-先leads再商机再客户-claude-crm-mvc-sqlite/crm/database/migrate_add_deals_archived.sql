-- Migration: Add archived fields to deals table
-- Usage: sqlite3 database/crm.sqlite < database/migrate_add_deals_archived.sql

ALTER TABLE deals ADD COLUMN archived INTEGER NOT NULL DEFAULT 0;
ALTER TABLE deals ADD COLUMN archived_at TEXT;

CREATE INDEX IF NOT EXISTS idx_deals_archived ON deals(archived);
