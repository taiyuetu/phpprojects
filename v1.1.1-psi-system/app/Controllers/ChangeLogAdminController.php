<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ChangeLog;

/**
 * 变更日志管理控制器
 */
class ChangeLogAdminController extends Controller
{
    /**
     * 显示管理面板
     */
    public function index(): void
    {
        $this->view('changelogs/admin/index', [
            'title' => '变更日志管理',
        ]);
    }

    /**
     * 执行归档操作
     */
    public function archive(): void
    {
        $this->verifyCsrf();
        
        $retentionDays = (int) $this->input('retention_days', 365);
        
        // 调用归档脚本
        $output = [];
        exec('php ' . __DIR__ . '/../../database/archive_logs.php 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->flash('success', '归档操作完成。');
        } else {
            $this->flash('error', '归档操作失败：' . implode("\n", $output));
        }
        
        $this->redirect('/admin/changelogs');
    }

    /**
     * 执行清理操作
     */
    public function cleanup(): void
    {
        $this->verifyCsrf();
        
        $retentionDays = (int) $this->input('retention_days', 1095);
        
        // 调用清理脚本
        $output = [];
        exec('php ' . __DIR__ . '/../../database/cleanup_logs.php 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->flash('success', '清理操作完成。');
        } else {
            $this->flash('error', '清理操作失败：' . implode("\n", $output));
        }
        
        $this->redirect('/admin/changelogs');
    }

    /**
     * 显示统计信息
     */
    public function stats(): void
    {
        $db = \App\Core\Database::connect();
        
        // 获取变更日志统计
        $stmt = $db->query("SELECT COUNT(*) as count FROM change_logs");
        $activeCount = $stmt->fetch()['count'];
        
        // 获取归档日志统计
        $archiveCount = 0;
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='change_logs_archive'");
        if ($stmt->fetch()) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM change_logs_archive");
            $archiveCount = $stmt->fetch()['count'];
        }
        
        // 获取数据库大小
        $dbPath = $db->query("PRAGMA database_list")->fetch()['file'];
        $dbSize = filesize($dbPath);
        
        // 获取各表记录数
        $stmt = $db->query("SELECT table_name, COUNT(*) as count FROM change_logs GROUP BY table_name ORDER BY count DESC");
        $tableStats = $stmt->fetchAll();
        
        $this->view('changelogs/admin/stats', [
            'title'        => '变更日志统计',
            'activeCount'  => $activeCount,
            'archiveCount' => $archiveCount,
            'dbSize'       => $dbSize,
            'tableStats'   => $tableStats,
        ]);
    }
}