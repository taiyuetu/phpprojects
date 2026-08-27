<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">变更日志管理</h2>
        <a href="<?= Router::url('/admin/changelogs/stats') ?>" class="btn btn-secondary btn-sm">查看统计</a>
    </div>

    <div class="admin-grid">
        <div class="admin-card">
            <h3>数据归档</h3>
            <p>将超过保留期的变更日志移到归档表，提高查询性能。</p>
            <form method="post" action="<?= Router::url('/admin/changelogs/archive') ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group">
                    <label for="retention_days">保留天数</label>
                    <input type="number" name="retention_days" id="retention_days" value="365" min="30" max="3650">
                    <small>超过此天数的日志将被归档</small>
                </div>
                <button type="submit" class="btn btn-primary">执行归档</button>
            </form>
        </div>

        <div class="admin-card">
            <h3>数据清理</h3>
            <p>删除超过保留期的归档日志，释放存储空间。</p>
            <form method="post" action="<?= Router::url('/admin/changelogs/cleanup') ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group">
                    <label for="archive_retention_days">归档保留天数</label>
                    <input type="number" name="retention_days" id="archive_retention_days" value="1095" min="365" max="3650">
                    <small>超过此天数的归档日志将被删除</small>
                </div>
                <button type="submit" class="btn btn-danger">执行清理</button>
            </form>
        </div>
    </div>

    <div class="admin-info">
        <h3>存储优化建议</h3>
        <ul>
            <li><strong>低频使用</strong>（每天<10次操作）：建议每年归档一次</li>
            <li><strong>中频使用</strong>（每天10-100次操作）：建议每半年归档一次</li>
            <li><strong>高频使用</strong>（每天>100次操作）：建议每季度归档一次</li>
            <li><strong>生产环境</strong>：建议使用MySQL数据库，性能更好</li>
        </ul>
    </div>
</div>

<style>
.admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.admin-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.admin-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #495057;
}

.admin-card p {
    margin-bottom: 15px;
    color: #6c757d;
}

.admin-info {
    background: #e9ecef;
    padding: 20px;
    border-radius: 8px;
}

.admin-info h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #495057;
}

.admin-info ul {
    margin-bottom: 0;
}

.admin-info li {
    margin-bottom: 8px;
    color: #495057;
}
</style>