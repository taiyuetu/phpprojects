-- 叁程 CRM (Triphase CRM)
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

-- Update attachments table to allow 'customer' as related_type
-- SQLite doesn't support ALTER CHECK constraints, so we recreate the table.

PRAGMA foreign_keys = OFF;

-- Create new table with updated CHECK constraint
CREATE TABLE attachments_new (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    related_type  TEXT NOT NULL CHECK (related_type IN ('deal','order','customer')),
    related_id    INTEGER NOT NULL,
    filename      TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type     TEXT NOT NULL,
    file_size     INTEGER NOT NULL DEFAULT 0,
    uploaded_by   INTEGER,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Copy data
INSERT INTO attachments_new SELECT * FROM attachments;

-- Replace old table
DROP TABLE attachments;
ALTER TABLE attachments_new RENAME TO attachments;

-- Recreate index
CREATE INDEX IF NOT EXISTS idx_attachments_related ON attachments(related_type, related_id);

PRAGMA foreign_keys = ON;
