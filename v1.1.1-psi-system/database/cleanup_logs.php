<?php
/**
 * 变更日志清理脚本
 * 删除超过指定天数的归档日志
 */

require_once __DIR__ . '/../config/config.php';

// 配置
$archiveRetentionDays = 1095; // 归档保留天数（3年）
$batchSize = 1000;            // 每批处理记录数

try {
    $db = new PDO('sqlite:' . $config['db']['sqlite_path']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== 变更日志清理工具 ===\n\n";
    
    // 1. 检查归档表是否存在
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='change_logs_archive'");
    if (!$stmt->fetch()) {
        echo "归档表不存在，无需清理。\n";
        exit(0);
    }
    
    // 2. 计算需要清理的记录数
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$archiveRetentionDays} days"));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM change_logs_archive WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $totalToDelete = $stmt->fetch()['count'];
    
    echo "清理策略: 删除超过 {$archiveRetentionDays} 天的归档日志\n";
    echo "截止日期: $cutoffDate\n";
    echo "需要清理的记录数: $totalToDelete\n\n";
    
    if ($totalToDelete == 0) {
        echo "没有需要清理的记录。\n";
        exit(0);
    }
    
    // 3. 分批删除
    $deleted = 0;
    $startTime = microtime(true);
    
    while ($deleted < $totalToDelete) {
        // 获取一批记录ID
        $stmt = $db->prepare("SELECT id FROM change_logs_archive WHERE created_at < ? LIMIT ?");
        $stmt->execute([$cutoffDate, $batchSize]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids)) {
            break;
        }
        
        // 删除记录
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM change_logs_archive WHERE id IN ($placeholders)")->execute($ids);
        
        $deleted += count($ids);
        $progress = round(($deleted / $totalToDelete) * 100, 1);
        echo "已删除: $deleted / $totalToDelete ($progress%)\n";
    }
    
    $elapsed = round(microtime(true) - $startTime, 2);
    echo "\n✓ 清理完成！\n";
    echo "  - 删除记录数: $deleted\n";
    echo "  - 耗时: {$elapsed}秒\n";
    
    // 4. 压缩数据库（可选）
    echo "\n压缩数据库...\n";
    $db->exec("VACUUM");
    echo "✓ 数据库压缩完成\n";
    
    // 5. 显示最终数据库大小
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