<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">系统变更日志</h2>
        <div>
            <a href="<?= Router::url('/changelogs/table/products') ?>" class="btn btn-secondary btn-sm">产品变更</a>
            <a href="<?= Router::url('/changelogs/table/purchases') ?>" class="btn btn-secondary btn-sm">采购变更</a>
            <a href="<?= Router::url('/changelogs/table/sales') ?>" class="btn btn-secondary btn-sm">销售变更</a>
        </div>
    </div>

    <?php if (empty($changes)): ?>
        <p class="empty-state">暂无变更记录。</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>时间</th>
                <th>表名</th>
                <th>记录ID</th>
                <th>操作</th>
                <th>操作人</th>
                <th>详情</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($changes as $change): ?>
            <tr>
                <td><?= htmlspecialchars($change['created_at']) ?></td>
                <td>
                    <a href="<?= Router::url('/changelogs/table/' . $change['table_name']) ?>">
                        <?= htmlspecialchars($change['table_name']) ?>
                    </a>
                </td>
                <td>
                    <a href="<?= Router::url('/changelogs/record/' . $change['table_name'] . '/' . $change['record_id']) ?>">
                        #<?= $change['record_id'] ?>
                    </a>
                </td>
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
                    <a href="<?= Router::url('/changelogs/' . $change['id']) ?>" class="btn btn-secondary btn-sm">查看</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>