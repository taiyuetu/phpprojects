<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;"><?= htmlspecialchars($title) ?></h2>
        <a href="<?= Router::url('/changelogs/table/' . $tableName) ?>" class="btn btn-secondary btn-sm">返回<?= htmlspecialchars($tableName) ?>日志</a>
    </div>

    <?php if (empty($changes)): ?>
        <p class="empty-state">暂无此记录的变更历史。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>时间</th>
                <th>操作</th>
                <th>操作人</th>
                <th>变更字段</th>
                <th>详情</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($changes as $change): ?>
            <tr>
                <td><?= htmlspecialchars($change['created_at']) ?></td>
                <td>
                    <?php
                    $actionClass = '';
                    $actionText = '';
                    switch ($change['action']) {
                        case 'create':
                            $actionClass = 'badge-green';
                            $actionText = '创建';
                            break;
                        case 'update':
                            $actionClass = 'badge-blue';
                            $actionText = '更新';
                            break;
                        case 'delete':
                            $actionClass = 'badge-red';
                            $actionText = '删除';
                            break;
                        default:
                            $actionClass = 'badge-gray';
                            $actionText = $change['action'];
                    }
                    ?>
                    <span class="badge <?= $actionClass ?>"><?= $actionText ?></span>
                </td>
                <td><?= htmlspecialchars($change['user_name'] ?? '系统') ?></td>
                <td>
                    <?php
                    $oldData = \App\Models\ChangeLog::parseJson($change['old_data']);
                    $newData = \App\Models\ChangeLog::parseJson($change['new_data']);
                    $changesList = \App\Models\ChangeLog::getChanges($oldData, $newData);
                    
                    if (empty($changesList)) {
                        echo '<span class="text-muted">-</span>';
                    } else {
                        $fieldNames = array_column($changesList, 'field');
                        echo htmlspecialchars(implode(', ', array_slice($fieldNames, 0, 3)));
                        if (count($fieldNames) > 3) {
                            echo ' <span class="text-muted">+' . (count($fieldNames) - 3) . '个字段</span>';
                        }
                    }
                    ?>
                </td>
                <td>
                    <a href="<?= Router::url('/changelogs/' . $change['id']) ?>" class="btn btn-secondary btn-sm">查看</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>