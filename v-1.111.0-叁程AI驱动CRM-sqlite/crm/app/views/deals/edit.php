<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
// Set variables for attachment partial
$relatedType = 'deal';
$relatedId = (int) $deal['id'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">编辑商机</h3>
    <a href="<?= url('/deals') ?>" class="btn btn-outline-secondary btn-sm">返回看板</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-table p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2">
                    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/deals/' . $deal['id']) ?>" id="deal-form">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <?php include __DIR__ . '/_form.php'; ?>
                <button type="submit" class="btn btn-primary mt-3">保存修改</button>
            </form>
        </div>

        <!-- 附件 -->
        <?php include APP_PATH . '/views/partials/_attachments.php'; ?>
    </div>

    <div class="col-lg-4">
        <!-- 关联订单 -->
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-receipt me-1"></i>关联订单
                <span class="badge bg-success ms-1"><?= count($orders) ?></span>
            </h6>
            <?php if (!$orders): ?>
                <p class="text-muted small mb-0">该商机暂无订单。</p>
                <?php if ($deal['stage'] === 'closed_won'): ?>
                    <form method="POST" action="<?= url('/deals/' . $deal['id'] . '/create-order') ?>" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-plus-lg"></i> 创建订单
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <a href="<?= url('/orders/' . $o['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($o['order_number']) ?>
                                </a>
                                <br>
                                <small class="text-muted"><?= money($o['amount']) ?></small>
                            </div>
                            <?= statusBadge($o['status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 只有成交阶段才需要填明细：不是成交就别把这块占着屏幕
    const stageSelect = document.getElementById('deal-stage-select');
    const itemsSection = document.getElementById('items-section');
    if (!stageSelect || !itemsSection) return;
    const toggle = function () {
        itemsSection.style.display = stageSelect.value === 'closed_won' ? '' : 'none';
    };
    stageSelect.addEventListener('change', toggle);
    toggle();
});
</script>
<?php include APP_PATH . '/views/partials/_items_js.php'; ?>
