<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">订单管理</h3>
    <a href="<?= url('/orders/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新建订单</a>
</div>

<!-- 订单统计 -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">订单总数</div>
                    <div class="stat-value"><?= (int) $total ?></div>
                </div>
                <div class="stat-icon bg-primary"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">订单总额</div>
                    <div class="stat-value"><?= money($totalAmount) ?></div>
                </div>
                <div class="stat-icon bg-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">待处理</div>
                    <div class="stat-value"><?= $this->model('Order')->countByStatus('pending') ?></div>
                </div>
                <div class="stat-icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">已完成</div>
                    <div class="stat-value"><?= $this->model('Order')->countByStatus('completed') ?></div>
                </div>
                <div class="stat-icon bg-info"><i class="bi bi-check-circle"></i></div>
            </div>
        </div>
    </div>
</div>

<?php
// 筛选链接/分页链接统一携带 status + q，避免搜索状态下切页/切状态丢关键词
$orderFilter = function (string $value) use ($status, $search): string {
    $p = [];
    if ($value !== '') { $p['status'] = $value; }
    if ($search !== '') { $p['q'] = $search; }
    return $p ? '?' . http_build_query($p) : '';
};
$orderPage = function (int $n) use ($status, $search): string {
    $p = [];
    if ($status !== '') { $p['status'] = $status; }
    if ($search !== '') { $p['q'] = $search; }
    $p['page'] = $n;
    return '?' . http_build_query($p);
};
?>

<!-- 状态筛选 + 关键词搜索 -->
<div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div class="btn-group btn-group-sm" role="group">
        <a href="<?= url('/orders' . $orderFilter('')) ?>" class="btn btn-outline-secondary <?= $status === '' ? 'active' : '' ?>">
            全部 <span class="badge bg-secondary ms-1"><?= $this->model('Order')->countOrders() ?></span>
        </a>
        <?php foreach (Order::statusOptions() as $val => $label): ?>
            <a href="<?= url('/orders' . $orderFilter($val)) ?>" class="btn btn-outline-secondary <?= $status === $val ? 'active' : '' ?>">
                <?= e($label) ?> <span class="badge bg-secondary ms-1"><?= $this->model('Order')->countByStatus($val) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <form method="GET" action="<?= url('/orders') ?>" class="d-flex gap-2">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px"
               placeholder="搜索单号、标题、客户、商机…" value="<?= e($search) ?>">
        <button class="btn btn-sm btn-outline-secondary" type="submit">搜索</button>
        <?php if ($search !== ''): ?>
            <a href="<?= url('/orders') ?>" class="btn btn-sm btn-link">清除</a>
        <?php endif; ?>
    </form>
</div>

<!-- 订单列表 -->
<div class="card card-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr class="text-muted small">
                    <th>订单编号</th>
                    <th>订单标题</th>
                    <th>客户</th>
                    <th>关联商机</th>
                    <th>金额</th>
                    <th>状态</th>
                    <th>付款状态</th>
                    <th>下单日期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="9" class="text-muted p-3">暂无订单记录。</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>
                            <a href="<?= url('/orders/' . $o['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= e($o['order_number']) ?>
                            </a>
                        </td>
                        <td><?= e($o['title']) ?></td>
                        <td>
                            <a href="<?= url('/customers/' . $o['customer_id']) ?>" class="text-decoration-none">
                                <?= e($o['customer_name'] ?? '—') ?>
                            </a>
                            <?php if (!empty($o['customer_company'])): ?>
                                <div class="small text-muted"><?= e($o['customer_company']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['deal_id']): ?>
                                <a href="<?= url('/deals/' . $o['deal_id'] . '/edit') ?>" class="text-decoration-none">
                                    <?= e($o['deal_title'] ?? '—') ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold"><?= money($o['amount']) ?></td>
                        <td><?= statusBadge($o['status']) ?></td>
                        <td>
                            <?php
                            $paymentBadge = [
                                'unpaid'  => 'bg-danger',
                                'partial' => 'bg-warning text-dark',
                                'paid'    => 'bg-success',
                            ];
                            $paymentLabel = Order::paymentStatusLabel($o['payment_status']);
                            ?>
                            <span class="badge <?= $paymentBadge[$o['payment_status']] ?? 'bg-secondary' ?>">
                                <?= e($paymentLabel) ?>
                            </span>
                        </td>
                        <td><?= formatDate($o['order_date'], 'Y-m-d') ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= url('/orders/' . $o['id']) ?>" class="btn btn-outline-primary py-0 px-1" title="查看">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= url('/orders/' . $o['id'] . '/edit') ?>" class="btn btn-outline-secondary py-0 px-1" title="编辑">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="<?= url('/orders/' . $o['id']) ?>" class="d-inline"
                                      onsubmit="return confirm('确定删除此订单？');">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                    <button type="submit" class="btn btn-outline-danger py-0 px-1" title="删除">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 分页 -->
<?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination pagination-sm justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('/orders' . $orderPage($page - 1)) ?>">上一页</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('/orders' . $orderPage($i)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('/orders' . $orderPage($page + 1)) ?>">下一页</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
