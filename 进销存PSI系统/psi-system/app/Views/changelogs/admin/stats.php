<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">变更日志统计</h2>
        <a href="<?= Router::url('/admin/changelogs') ?>" class="btn btn-secondary btn-sm">返回管理</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($activeCount) ?></div>
            <div class="stat-label">活跃日志记录</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= number_format($archiveCount) ?></div>
            <div class="stat-label">归档日志记录</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= round($dbSize / 1024 / 1024, 2) ?> MB</div>
            <div class="stat-label">数据库大小</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-number"><?= number_format($activeCount + $archiveCount) ?></div>
            <div class="stat-label">总记录数</div>
        </div>
    </div>

    <?php if (!empty($tableStats)): ?>
    <div class="table-stats">
        <h3>各表变更统计</h3>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>表名</th>
                    <th>记录数</th>
                    <th>占比</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tableStats as $stat): ?>
                <tr>
                    <td>
                        <a href="<?= Router::url('/changelogs/table/' . $stat['table_name']) ?>">
                            <?= htmlspecialchars($stat['table_name']) ?>
                        </a>
                    </td>
                    <td><?= number_format($stat['count']) ?></td>
                    <td>
                        <?php 
                        $percentage = $activeCount > 0 ? ($stat['count'] / $activeCount) * 100 : 0;
                        echo round($percentage, 1) . '%';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="storage-info">
        <h3>存储信息</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>数据库路径：</strong>
                <span><?= htmlspecialchars($dbPath ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <strong>平均记录大小：</strong>
                <span><?= $activeCount > 0 ? round(($dbSize / $activeCount) / 1024, 2) . ' KB' : 'N/A' ?></span>
            </div>
            <div class="info-item">
                <strong>预计1年后大小：</strong>
                <span>
                    <?php
                    if ($activeCount > 0) {
                        $dailyGrowth = $activeCount / 30; // 假设30天的数据
                        $yearlyGrowth = $dailyGrowth * 365;
                        $yearlySize = ($yearlyGrowth * ($dbSize / $activeCount)) / 1024 / 1024;
                        echo round($yearlySize, 2) . ' MB';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #dee2e6;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9em;
}

.table-stats {
    margin-bottom: 30px;
}

.table-stats h3 {
    margin-bottom: 15px;
    color: #495057;
}

.storage-info {
    background: #e9ecef;
    padding: 20px;
    border-radius: 8px;
}

.storage-info h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #495057;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.info-item {
    margin-bottom: 10px;
}

.info-item strong {
    display: block;
    margin-bottom: 5px;
    color: #495057;
}
</style>