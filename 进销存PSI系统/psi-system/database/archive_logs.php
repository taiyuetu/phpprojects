<?php
/**
 * 变更日志归档脚本
 * 将超过指定天数的日志移到归档表
 */

require_once __DIR__ . '/../config/config.php';

// 配置
$retentionDays = 365; // 保留天数（1年）
$batchSize = 1000;    // 每批处理记录数

try {
    $db = new PDO('sqlite:' . $config['db']['sqlite_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== 变更日志归档工具 ===\n\n";
    
    // 1. 检查归档表是否存在
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='change_logs_archive'");
    if (!$stmt->fetch()) {
        echo "创建归档表...\n";
        $archiveSql = file_get_contents(__DIR__ . '/archive_schema.sql');
        $db->exec($archiveSql);
        echo "✓ 归档表创建成功\n\n";
    }
    
    // 2. 计算需要归档的记录数
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM change_logs WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $totalToArchive = $stmt->fetch()['count'];
    
    echo "保留策略: {$retentionDays} 天\n";
    echo "截止日期: $cutoffDate\n";
    echo "需要归档的记录数: $totalToArchive\n\n";
    
    if ($totalToArchive == 0) {
        echo "没有需要归档的记录。\n";
        exit(0);
    }
    
    // 3. 分批归档
    $archived = 0;
    $startTime = microtime(true);
    
    while ($archived < $totalToArchive) {
        // 获取一批记录
        $stmt = $db->prepare("SELECT id FROM change_logs WHERE created_at < ? LIMIT ?");
        $stmt->execute([$cutoffDate, $batchSize]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids)) {
            break;
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 复制到归档表
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("
                INSERT INTO change_logs_archive 
                (id, table_name, record_id, action, old_data, new_data, user_id, created_at)
                SELECT id, table_name, record_id, action, old_data, new_data, user_id, created_at
                FROM change_logs WHERE id IN ($placeholders)
            ")->execute($ids);
            
            // 从原表删除
            $db->prepare("DELETE FROM change_logs WHERE id IN ($placeholders)")->execute($ids);
            
            $db->commit();
            
            $archived += count($ids);
            $progress = round(($archived / $totalToArchive) * 100, 1);
            echo "已归档: $archived / $totalToArchive ($progress%)\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n✓ 归档完成！\n";
    echo "  - 归档记录数: $archived\n";
    echo "  - 耗时: {$elapsed}秒\n";
    
    // 4. 显示归档统计
    $stmt = $db->query("SELECT COUNT(*) as count FROM change_logs_archive");
    $archiveCount = $stmt->fetch()['count'];
    echo "  - 归档表总记录数: $archiveCount\n";
    
    // 5. 显示数据库大小
    $dbSize = filesize($config['db']['sqlite_path']);
    echo "  - 数据库大小: " . round($dbSize / 1024 / 1024, 2) . " MB\n";
    
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
?>