<?php
/**
 * 添加change_logs表到现有数据库
 */

require_once __DIR__ . '/../config/config.php';

try {
    $db = new PDO('sqlite:' . $config['db']['sqlite_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE TABLE IF NOT EXISTS change_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        table_name TEXT NOT NULL,
        record_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        old_data TEXT,
        new_data TEXT,
        user_id INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    $db->exec($sql);
    echo "✓ change_logs表创建成功\n";
    
    // 创建索引以提高查询性能
    $db->exec("CREATE INDEX IF NOT EXISTS idx_change_logs_table_record ON change_logs(table_name, record_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_change_logs_created_at ON change_logs(created_at)");
    echo "✓ 索引创建成功\n";
    
} catch (PDOException $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
?>