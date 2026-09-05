<?php
/** @var array $product @var array $usage @var array $recent @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">
        <?= e((string) $product['name']) ?>
        <span class="badge bg-light text-dark border align-middle"><?= e((string) $product['public_code']) ?></span>
        <span class="badge <?= $product['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> align-middle">
            <?= e(Product::statusLabel((string) $product['status'])) ?>
        </span>
    </h3>
    <div class="d-flex gap-2">
        <a href="<?= url('/products/' . (int) $product['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">编辑</a>
        <a href="<?= url('/products') ?>" class="btn btn-sm btn-outline-secondary">返回商品库</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card card-table p-3 h-100">
            <table class="table table-sm mb-0">
                <tbody>
                <tr><th class="text-muted" style="width:120px">SKU</th><td><?= e((string) ($product['sku'] ?: '—')) ?></td></tr>
                <tr><th class="text-muted">分类 / 品牌</th>
                    <td><?= e((string) ($product['category'] ?: '—')) ?> / <?= e((string) ($product['brand'] ?: '—')) ?></td></tr>
                <tr><th class="text-muted">规格</th><td><?= e((string) ($product['spec'] ?: '—')) ?></td></tr>
                <tr><th class="text-muted">单价 / 单位</th>
                    <td><?= money((float) $product['price']) ?> / <?= e((string) $product['unit']) ?>
                        <?php if (isset($product['cost']) && $product['cost'] !== null && $product['cost'] !== ''): ?>
                            <span class="text-muted small">（参考价 <?= money((float) $product['cost']) ?>）</span>
                        <?php endif; ?>
                    </td></tr>
                <tr><th class="text-muted">备注</th><td class="text-prewrap"><?= nl2br(e((string) ($product['notes'] ?: '—'))) ?></td></tr>
                <tr><th class="text-muted">负责人</th><td><?= ownerBlock($product['owner_id'] ?? null) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-table p-3 h-100">
            <div class="text-muted small mb-2">卖出情况</div>
            <div class="fs-4"><?= (int) $usage['items'] ?> <span class="fs-6 text-muted">条明细 / <?= (int) $usage['orders'] ?> 张订单</span></div>
            <div class="text-primary fs-5"><?= money((float) $usage['amount']) ?></div>
            <div class="form-text mt-2">合计取的是明细里的成交快照，不是当前单价 × 数量。</div>
        </div>
    </div>
</div>

<div class="card card-table">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>最近成交</span>
        <span class="badge bg-primary"><?= count($recent) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr class="text-muted small"><th>订单</th><th>客户</th><th>下单日期</th><th class="text-end">数量</th><th class="text-end">成交单价</th><th class="text-end">小计</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="6" class="text-center text-muted p-4">还没有成交记录。</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><a href="<?= url('/orders/' . (int) $r['order_id']) ?>"><?= e((string) $r['order_number']) ?></a></td>
                    <td><?= e((string) ($r['customer_name'] ?: '—')) ?></td>
                    <td class="small text-muted"><?= e(substr((string) $r['order_date'], 0, 10)) ?></td>
                    <td class="text-end"><?= e((string) $r['quantity']) ?> <?= e((string) $r['unit']) ?></td>
                    <td class="text-end"><?= money((float) $r['unit_price']) ?></td>
                    <td class="text-end fw-semibold"><?= money((float) $r['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
