<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ChangeLog;

/**
 * 变更日志控制器 - 查看系统变更历史
 */
class ChangeLogController extends Controller
{
    /**
     * 显示所有表的最新变更
     */
    public function index(): void
    {
        $this->view('changelogs/index', [
            'title'   => '系统变更日志',
            'changes' => ChangeLog::getAllRecentChanges(100),
        ]);
    }

    /**
     * 显示指定表的变更历史
     */
    public function table(string $tableName): void
    {
        $this->view('changelogs/table', [
            'title'     => ucfirst($tableName) . ' 变更历史',
            'tableName' => $tableName,
            'changes'   => ChangeLog::getRecentChanges($tableName, 100),
        ]);
    }

    /**
     * 显示指定记录的变更历史
     */
    public function record(string $tableName, string $recordId): void
    {
        $this->view('changelogs/record', [
            'title'     => ucfirst($tableName) . ' #' . $recordId . ' 变更历史',
            'tableName' => $tableName,
            'recordId'  => $recordId,
            'changes'   => ChangeLog::getHistory($tableName, (int)$recordId, 50),
        ]);
    }

    /**
     * 显示变更详情
     */
    public function show(string $id): void
    {
        $change = ChangeLog::find($id);
        if (!$change) {
            $this->flash('error', '变更日志不存在。');
            $this->redirect('/changelogs');
        }

        $this->view('changelogs/show', [
            'title'  => '变更详情 #' . $id,
            'change' => $change,
            'oldData' => ChangeLog::parseJson($change['old_data']),
            'newData' => ChangeLog::parseJson($change['new_data']),
            'changes' => ChangeLog::getChanges(
                ChangeLog::parseJson($change['old_data']),
                ChangeLog::parseJson($change['new_data'])
            ),
        ]);
    }
}