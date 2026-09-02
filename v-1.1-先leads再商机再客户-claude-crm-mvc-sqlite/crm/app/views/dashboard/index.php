<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">仪表盘</h3>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">客户总数</div>
                    <div class="stat-value"><?= (int) $stats['total_customers'] ?></div>
                </div>
                <div class="stat-icon bg-primary"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">活跃客户</div>
                    <div class="stat-value"><?= (int) $stats['active_customers'] ?></div>
                </div>
                <div class="stat-icon bg-success"><i class="bi bi-person-check-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">待处理线索</div>
                    <div class="stat-value"><?= (int) $stats['open_leads'] ?></div>
                </div>
                <div class="stat-icon bg-info"><i class="bi bi-magnet-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted small">商机管线</div>
                    <div class="stat-value"><?= money($stats['pipeline_value']) ?></div>
                </div>
                <div class="stat-icon bg-warning"><i class="bi bi-currency-dollar"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-table">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>最近客户</strong>
                <a href="<?= url('/customers') ?>" class="small">查看全部</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <tbody>
                    <?php if (!$recentCustomers): ?>
                        <tr><td class="text-muted p-3">暂无客户。</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentCustomers as $c): ?>
                        <tr>
                            <td>
                                <a href="<?= url('/customers/' . $c['id']) ?>" class="fw-semibold text-decoration-none"><?= e($c['name']) ?></a>
                                <div class="small text-muted"><?= e($c['company'] ?: '—') ?></div>
                            </td>
                            <td class="text-end"><?= statusBadge($c['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-table">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>最近商机</strong>
                <a href="<?= url('/deals') ?>" class="small">查看全部</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <tbody>
                    <?php if (!$recentDeals): ?>
                        <tr><td class="text-muted p-3">暂无商机。</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentDeals as $d): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($d['title']) ?></div>
                                <div class="small text-muted"><?= e($d['customer_name'] ?? '—') ?></div>
                            </td>
                            <td class="text-end">
                                <?= money($d['value']) ?><br>
                                <?= statusBadge($d['stage']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card card-table">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>最近线索</strong>
                <a href="<?= url('/leads') ?>" class="small">查看全部</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr class="text-muted small">
                            <th>线索</th>
                            <th>联系人</th>
                            <th>来源</th>
                            <th>预估金额</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentLeads): ?>
                        <tr><td class="text-muted p-3" colspan="5">暂无线索。</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentLeads as $l): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($l['title']) ?></td>
                            <td><?= e($l['contact_name'] ?: '—') ?></td>
                            <td><?= e($l['source'] ?: '—') ?></td>
                            <td><?= money($l['value']) ?></td>
                            <td><?= statusBadge($l['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
