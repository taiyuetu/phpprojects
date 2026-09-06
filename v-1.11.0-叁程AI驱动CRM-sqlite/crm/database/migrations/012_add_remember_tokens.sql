-- 记住密码功能：存储长期登录 token
-- Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.

CREATE TABLE IF NOT EXISTS remember_tokens (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    token       TEXT NOT NULL UNIQUE,
    expires_at  TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_remember_tokens_token ON remember_tokens(token);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_user ON remember_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_expires ON remember_tokens(expires_at);
