<?php
/** @var array $deal @var array|null $customer @var array $orders @var array $attachments @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$relatedType = 'deal';
$relatedId   = (int) $deal['id'];
$stageTimeCol = 'stage_' . ($deal['stage'] ?? '') . '_at';
$isArchived   = !empty($deal['archived']);
$wonNoOrder   = ($deal['stage'] ?? '') === 'closed_won' && !$orders;
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><?= e($deal['title']) ?>
            <span class="badge bg-light text-muted border align-middle fs-6" title="稳定编号：与 AI 说同一条记录时用它"><?= e((new Deal())->codeOf($deal)) ?></span>
            <?= statusBadge((string) $deal['stage']) ?>
            <?php if ($isArchived): ?>
                <span class="badge bg-secondary">已归档</span>
            <?php endif; ?>
        </h3>
        <?php if (!empty($customer)): ?>
            <div class="text-muted">客户：<a href="<?= url('/customers/' . (int) $customer['id']) ?>" class="text-decoration-none"><?= e($customer['name']) ?></a></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isArchived): ?>
            <form method="POST" action="<?= url('/deals/' . $deal['id'] . '/unarchive') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> 恢复
                </button>
            </form>
        <?php elseif ($wonNoOrder): ?>
            <form method="POST" action="<?= url('/deals/' . $deal['id'] . '/create-order') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-receipt"></i> 创建订单
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= url('/deals/' . $deal['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?= url('/deals' . ($isArchived ? '/archived' : '')) ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">商机信息</h6>
            <p class="mb-1"><i class="bi bi-cash-coin me-2"></i>金额：<strong><?= money((float) $deal['value']) ?></strong></p>
            <p class="mb-1"><i class="bi bi-calendar-event me-2"></i>预计成交：<?= !empty($deal['close_date']) ? formatDate($deal['close_date'], 'Y-m-d') : '—' ?></p>
            <?= ownerBlock($deal['owner_id'] ?? null) ?>
            <p class="mb-1"><i class="bi bi-clock-history me-2"></i>创建时间：<?= formatDate($deal['created_at'], 'Y-m-d H:i') ?></p>
            <?php if (!empty($deal['updated_at'])): ?>
                <p class="mb-1"><i class="bi bi-arrow-repeat me-2"></i>最近更新：<?= formatDate($deal['updated_at'], 'Y-m-d H:i') ?></p>
            <?php endif; ?>
            <?php if (!empty($deal[$stageTimeCol])): ?>
                <p class="mb-1"><i class="bi bi-flag me-2"></i>进入当前阶段：<?= formatDate($deal[$stageTimeCol], 'Y-m-d H:i') ?></p>
            <?php endif; ?>
            <?php if ($isArchived && !empty($deal['archived_at'])): ?>
                <p class="mb-1"><i class="bi bi-archive me-2"></i>归档时间：<?= formatDate($deal['archived_at'], 'Y-m-d H:i') ?></p>
            <?php endif; ?>
        </div>

        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-2">相关明细</h6>
            <p class="small text-muted mb-0">
                明细行在<a href="<?= url('/deals/' . $deal['id'] . '/edit') ?>">编辑页</a>维护；
                <?php if ($orders): ?>
                    成交后会自动生成订单，请查看下方订单。
                <?php else: ?>
                    成交（或从看板点击“创建订单”）后将自动生成订单。
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="col-lg-6">
        <!-- 关联订单 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase mb-2">
                <i class="bi bi-receipt me-1"></i>订单 <span class="badge bg-secondary ms-1"><?= count($orders) ?></span>
            </h6>
            <?php if (!$orders): ?>
                <p class="text-muted small mb-0">暂无订单。商机成交后会自动创建。</p>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <div>
                            <a href="<?= url('/orders/' . (int) $o['id']) ?>" class="text-decoration-none fw-semibold"><?= e($o['order_number']) ?></a>
                            <div class="small text-muted"><?= formatDate($o['order_date'], 'Y-m-d') ?> · <?= statusBadge((string) $o['status']) ?></div>
                        </div>
                        <span class="fw-semibold"><?= money((float) $o['amount']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php include APP_PATH . '/views/partials/_attachments.php'; ?>
    </div>
</div>
