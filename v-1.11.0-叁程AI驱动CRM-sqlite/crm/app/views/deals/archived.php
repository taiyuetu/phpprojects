<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">已归档商机</h3>
        <div class="text-muted small">丢单后自动归档的商机，保留历史数据供查阅。</div>
    </div>
    <a href="<?= url('/deals') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 返回看板</a>
</div>

<form method="GET" action="<?= url('/deals/archived') ?>" class="d-flex gap-2 mb-3" style="max-width:440px">
    <input type="text" name="q" class="form-control form-control-sm"
           placeholder="搜索商机标题、客户名称…" value="<?= e($search) ?>">
    <button class="btn btn-sm btn-outline-secondary" type="submit">搜索</button>
    <?php if ($search !== ''): ?>
        <a href="<?= url('/deals/archived') ?>" class="btn btn-sm btn-link">清除</a>
    <?php endif; ?>
</form>

<?php if (empty($deals)): ?>
    <div class="card card-table p-5 text-center text-muted">
        <i class="bi bi-archive fs-1 d-block mb-2"></i>
        暂无已归档的商机。
    </div>
<?php else: ?>
    <div class="card card-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>商机名称</th>
                        <th>客户</th>
                        <th>金额</th>
                        <th>丢单/归档时间</th>
                        <th>关联订单</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deals as $d): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($d['title']) ?></td>
                            <td><?= e($d['customer_name'] ?? '—') ?></td>
                            <td><?= money($d['value']) ?></td>
                            <td><?= formatDate($d['stage_closed_lost_at'] ?? $d['archived_at'], 'Y-m-d H:i') ?></td>
                            <td>
                                <?php
                                $orders = $this->model('Deal')->orders((int) $d['id']);
                                if ($orders): ?>
                                    <?php foreach ($orders as $o): ?>
                                        <a href="<?= url('/orders/' . $o['id']) ?>" class="text-decoration-none">
                                            <?= e($o['order_number']) ?>
                                        </a><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?= url('/deals/' . $d['id'] . '/unarchive') ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary py-0 px-1" title="恢复商机，回到看板的进行中列">
                                        <i class="bi bi-arrow-counterclockwise"></i> 恢复
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
