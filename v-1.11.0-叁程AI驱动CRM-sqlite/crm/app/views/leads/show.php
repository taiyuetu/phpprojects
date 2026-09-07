<?php
/** @var array $lead @var array|null $customer @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$isLost = ($lead['status'] ?? '') === 'lost';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><?= e($lead['title']) ?>
            <span class="badge bg-light text-muted border align-middle fs-6" title="稳定编号：与 AI 说同一条记录时用它"><?= e((new Lead())->codeOf($lead)) ?></span>
            <?= statusBadge((string) $lead['status']) ?>
        </h3>
        <?php if (!empty($lead['company'])): ?>
            <div class="text-muted"><?= e($lead['company']) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isLost): ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/reactivate') ?>"
                  onsubmit="return confirm('确定重新激活此线索？将恢复为"已联系"状态。');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-arrow-counterclockwise"></i> 重新激活
                </button>
            </form>
        <?php else: ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/convert') ?>"
                  onsubmit="return confirm('确定将此线索转为商机？系统将自动创建客户和商机记录。');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-right-circle"></i> 转为商机
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= url('/leads/' . $lead['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?= url('/leads') ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <!-- 联系信息 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">联系信息</h6>
            <?php if (!empty($lead['contact_name'])): ?>
                <p class="mb-1"><i class="bi bi-person me-2"></i><?= e($lead['contact_name']) ?></p>
            <?php endif; ?>
            <?php if (!empty($lead['contact_email'])): ?>
                <p class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:<?= e($lead['contact_email']) ?>"><?= e($lead['contact_email']) ?></a></p>
            <?php endif; ?>
            <?php if (!empty($lead['phone'])): ?>
                <p class="mb-1"><i class="bi bi-telephone me-2"></i><?= e($lead['phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($lead['whatsapp'])): ?>
                <p class="mb-1"><i class="bi bi-whatsapp me-2"></i><?= e($lead['whatsapp']) ?></p>
            <?php endif; ?>
            <?php if (!empty($lead['facebook'])): ?>
                <p class="mb-1"><i class="bi bi-facebook me-2"></i><a href="<?= e($lead['facebook']) ?>" target="_blank">Facebook</a></p>
            <?php endif; ?>
            <?php if (!empty($lead['tiktok'])): ?>
                <p class="mb-1"><i class="bi bi-tiktok me-2"></i><a href="<?= e($lead['tiktok']) ?>" target="_blank">TikTok</a></p>
            <?php endif; ?>
            <?php if (!empty($lead['website'])): ?>
                <p class="mb-1"><i class="bi bi-globe me-2"></i><a href="<?= e($lead['website']) ?>" target="_blank">官方网站</a></p>
            <?php endif; ?>
            <?php if (!empty($lead['address'])): ?>
                <p class="mb-1"><i class="bi bi-geo-alt me-2"></i><?= e($lead['address']) ?></p>
            <?php endif; ?>
            <?php if (!empty($lead['source_country']) || !empty($lead['source_city'])): ?>
                <p class="mb-1"><i class="bi bi-map me-2"></i><?= e($lead['source_country'] ?: '') ?><?= !empty($lead['source_city']) ? ' · ' . e($lead['source_city']) : '' ?></p>
            <?php endif; ?>
        </div>

        <!-- 关联客户（已转为商机/客户） -->
        <?php if (!empty($customer)): ?>
            <div class="card card-table p-3 mb-3">
                <h6 class="text-muted small text-uppercase mb-2">已关联客户</h6>
                <a href="<?= url('/customers/' . (int) $customer['id']) ?>" class="text-decoration-none">
                    <i class="bi bi-people me-2"></i><?= e($customer['name']) ?>
                </a>
                <div class="small text-muted mt-1">此线索已转为商机并建立了客户档案。</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <!-- 跟进信息 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">跟进信息</h6>
            <p class="mb-1"><i class="bi bi-tag me-2"></i>来源：<?= e($lead['source'] ?: '—') ?></p>
            <p class="mb-1"><i class="bi bi-cash-coin me-2"></i>预估金额：<?= money((float) $lead['value']) ?></p>
            <p class="mb-1"><i class="bi bi-hourglass-split me-2"></i>线索时间：<?= formatDate($lead['lead_time'] ?? '', 'Y-m-d H:i') ?></p>
            <?= ownerBlock($lead['owner_id'] ?? null) ?>
            <?php if ($isLost && !empty($lead['lost_reason'])): ?>
                <p class="mb-1"><i class="bi bi-x-octagon me-2"></i>流失原因：
                    <span class="badge bg-danger-subtle text-danger"><?= e(Lead::lostReasonLabel((string) $lead['lost_reason'])) ?></span>
                </p>
            <?php endif; ?>
            <p class="mb-1"><i class="bi bi-clock-history me-2"></i>创建时间：<?= formatDate($lead['created_at'], 'Y-m-d H:i') ?></p>
            <?php if (!empty($lead['updated_at'])): ?>
                <p class="mb-1"><i class="bi bi-arrow-repeat me-2"></i>最近更新：<?= formatDate($lead['updated_at'], 'Y-m-d H:i') ?></p>
            <?php endif; ?>
            <div class="mt-2">
                <?php if (!empty($lead['first_purchase_from_china'])): ?>
                    <span class="badge bg-info-subtle text-info me-1">第一次从中国采购</span>
                <?php endif; ?>
                <?php if (!empty($lead['has_import_capability'])): ?>
                    <span class="badge bg-success-subtle text-success me-1">有进口能力</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($lead['notes'])): ?>
                <hr>
                <h6 class="text-muted small text-uppercase">备注</h6>
                <p class="mb-0 small"><?= nl2br(e($lead['notes'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
