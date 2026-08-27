<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;"><?= htmlspecialchars($title) ?></h2>
        <a href="<?= Router::url('/changelogs/record/' . $change['table_name'] . '/' . $change['record_id']) ?>" class="btn btn-secondary btn-sm">返回记录历史</a>
    </div>

    <div class="change-detail">
        <div class="change-meta">
            <div class="meta-item">
                <strong>表名：</strong>
                <span><?= htmlspecialchars($change['table_name']) ?></span>
            </div>
            <div class="meta-item">
                <strong>记录ID：</strong>
                <span>#<?= $change['record_id'] ?></span>
            </div>
            <div class="meta-item">
                <strong>操作类型：</strong>
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
            </div>
            <div class="meta-item">
                <strong>操作时间：</strong>
                <span><?= htmlspecialchars($change['created_at']) ?></span>
            </div>
            <div class="meta-item">
                <strong>操作人：</strong>
                <span><?= htmlspecialchars($change['user_name'] ?? '系统') ?></span>
            </div>
        </div>

        <?php if ($change['action'] === 'update' && !empty($changes)): ?>
        <div class="change-summary">
            <h3>变更字段摘要</h3>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>字段名</th>
                        <th>旧值</th>
                        <th>新值</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($changes as $changeItem): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($changeItem['field']) ?></code></td>
                        <td><?= htmlspecialchars($changeItem['old'] ?? 'null') ?></td>
                        <td><?= htmlspecialchars($changeItem['new'] ?? 'null') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="change-data">
            <h3>详细数据</h3>
            <div class="data-sections">
                <?php if ($oldData): ?>
                <div class="data-section">
                    <h4>变更前数据</h4>
                    <pre><?= htmlspecialchars(json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
                <?php endif; ?>

                <?php if ($newData): ?>
                <div class="data-section">
                    <h4>变更后数据</h4>
                    <pre><?= htmlspecialchars(json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.change-detail {
    margin-top: 20px;
}

.change-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.meta-item {
    margin-bottom: 10px;
}

.meta-item strong {
    display: block;
    margin-bottom: 5px;
    color: #495057;
}

.change-summary {
    margin-bottom: 30px;
}

.change-summary h3 {
    margin-bottom: 15px;
    color: #495057;
}

.change-data h3 {
    margin-bottom: 15px;
    color: #495057;
}

.data-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.data-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    overflow-x: auto;
}

.data-section h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #495057;
}

.data-section pre {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    font-size: 14px;
    line-height: 1.5;
    max-height: 400px;
    overflow-y: auto;
}
</style>