-- 变更日志归档表结构
-- 用于存储超过保留期的变更日志

CREATE TABLE IF NOT EXISTS change_logs_archive (
    id INTEGER PRIMARY KEY,
    table_name TEXT NOT NULL,
    record_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    old_data TEXT,
    new_data TEXT,
    user_id INTEGER,
    created_at TEXT NOT NULL,
    archived_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 归档表索引
CREATE INDEX IF NOT EXISTS idx_archive_table_record ON change_logs_archive(table_name, record_id);
CREATE INDEX IF NOT EXISTS idx_archive_created_at ON change_logs_archive(created_at);
CREATE INDEX IF NOT EXISTS idx_archive_archived_at ON change_logs_archive(archived_at);