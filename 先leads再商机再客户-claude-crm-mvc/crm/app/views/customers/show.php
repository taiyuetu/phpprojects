<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0"><?= e($customer['name']) ?></h3>
        <div class="text-muted"><?= e($customer['company'] ?: '') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/customers/' . $customer['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?= url('/customers') ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">联系信息</h6>
            <p class="mb-1"><i class="bi bi-envelope me-2"></i><?= e($customer['email'] ?: '—') ?></p>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i><?= e($customer['phone'] ?: '—') ?></p>
            <p class="mb-1"><i class="bi bi-geo-alt me-2"></i><?= e($customer['address'] ?: '—') ?></p>
            <p class="mb-1"><i class="bi bi-person-badge me-2"></i>负责人：<?= e($customer['owner_name'] ?? '—') ?></p>
            <p class="mb-0"><?= statusBadge($customer['status']) ?></p>
            <?php if (!empty($customer['notes'])): ?>
                <hr>
                <h6 class="text-muted small text-uppercase">备注</h6>
                <p class="mb-0 small"><?= nl2br(e($customer['notes'])) ?></p>
            <?php endif; ?>
        </div>

        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">商机</h6>
            <?php if (!$deals): ?>
                <p class="text-muted small mb-0">该客户暂无商机。</p>
            <?php endif; ?>
            <?php foreach ($deals as $d): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><?= e($d['title']) ?><br><small class="text-muted"><?= money($d['value']) ?></small></span>
                    <?= statusBadge($d['stage']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">活动记录</h6>

            <form method="POST" action="<?= url('/customers/' . $customer['id'] . '/notes') ?>" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="input-group">
                    <input type="text" name="description" class="form-control" placeholder="记录电话、会议或备注…" required>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 添加</button>
                </div>
            </form>

            <?php if (!$activities): ?>
                <p class="text-muted small">暂无活动记录。</p>
            <?php endif; ?>

            <ul class="list-unstyled">
                <?php foreach ($activities as $a): ?>
                    <li class="d-flex mb-3">
                        <div class="me-3">
                            <span class="stat-icon bg-secondary" style="width:34px;height:34px;font-size:1rem;">
                                <i class="bi bi-chat-left-text"></i>
                            </span>
                        </div>
                        <div>
                            <div><?= e($a['description']) ?></div>
                            <div class="small text-muted">
                                <?= e($a['user_name'] ?? '未知') ?> · <?= formatDate($a['created_at'], 'M j, Y g:i A') ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
