<?php $o = $old ?? $order ?? []; ?>

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">订单编号 *</label>
        <input type="text" name="order_number" class="form-control" value="<?= e($o['order_number'] ?? $orderNumber ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">订单标题 *</label>
        <input type="text" name="title" class="form-control" value="<?= e($o['title'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">客户 *</label>
        <select name="customer_id" id="customer-select-order" class="form-select" required>
            <option value="">选择客户…</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($o['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?><?= $c['company'] ? ' (' . e($c['company']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">关联商机</label>
        <select name="deal_id" class="form-select">
            <option value="">无关联商机</option>
            <?php foreach ($deals as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) ($o['deal_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>>
                    <?= e($d['title']) ?> - <?= e($d['customer_name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">订单状态</label>
        <select name="status" class="form-select">
            <?php foreach (Order::statusOptions() as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($o['status'] ?? 'pending') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">付款状态</label>
        <select name="payment_status" class="form-select">
            <?php foreach (Order::paymentStatusOptions() as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($o['payment_status'] ?? 'unpaid') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">下单日期</label>
        <input type="date" name="order_date" class="form-control" value="<?= e($o['order_date'] ?? date('Y-m-d')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">交付日期</label>
        <input type="date" name="delivery_date" class="form-control" value="<?= e(is_string($o['delivery_date'] ?? null) ? substr($o['delivery_date'], 0, 10) : '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">负责人</label>
        <input type="text" class="form-control" value="<?= e(currentUser()['name'] ?? '') ?>" disabled>
    </div>
    <div class="col-md-4">
        <label class="form-label">订单金额（自动计算）</label>
        <input type="text" id="order-amount-display" class="form-control" readonly value="<?= money($o['amount'] ?? 0) ?>">
        <input type="hidden" name="amount" id="order-amount-hidden" value="<?= e($o['amount'] ?? 0) ?>">
    </div>
    <div class="col-12">
        <label class="form-label">收货地址</label>
        <textarea name="shipping_address" class="form-control" rows="2"><?= e($o['shipping_address'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">备注</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($o['notes'] ?? '') ?></textarea>
    </div>
</div>

<!-- 商品明细 -->
<div class="mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-muted mb-0"><i class="bi bi-box-seam me-1"></i>商品明细</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">
            <i class="bi bi-plus-lg"></i> 添加商品
        </button>
    </div>
    <div id="items-container">
        <?php
        $existingItems = $items ?? [];
        if (empty($existingItems)):
        ?>
            <div class="item-row card card-table p-3 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">商品名称 *</label>
                        <input type="text" name="items[0][product_name]" class="form-control form-control-sm" placeholder="如：CRM企业版授权">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">规格/SKU</label>
                        <input type="text" name="items[0][sku]" class="form-control form-control-sm" placeholder="SKU-001">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">数量</label>
                        <input type="number" step="0.01" min="0" name="items[0][quantity]" class="form-control form-control-sm item-qty" value="1">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">单位</label>
                        <select name="items[0][unit]" class="form-select form-select-sm">
                            <?php foreach (OrderItem::unitOptions() as $u): ?>
                                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">单价</label>
                        <input type="number" step="0.01" min="0" name="items[0][unit_price]" class="form-control form-control-sm item-price" value="0">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">小计</label>
                        <input type="text" class="form-control form-control-sm item-subtotal" readonly value="0.00">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="删除">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <input type="text" name="items[0][notes]" class="form-control form-control-sm" placeholder="备注（可选）">
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($existingItems as $i => $item): ?>
                <div class="item-row card card-table p-3 mb-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small">商品名称 *</label>
                            <input type="text" name="items[<?= $i ?>][product_name]" class="form-control form-control-sm" value="<?= e($item['product_name']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">规格/SKU</label>
                            <input type="text" name="items[<?= $i ?>][sku]" class="form-control form-control-sm" value="<?= e($item['sku'] ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">数量</label>
                            <input type="number" step="0.01" min="0" name="items[<?= $i ?>][quantity]" class="form-control form-control-sm item-qty" value="<?= e($item['quantity']) ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">单位</label>
                            <select name="items[<?= $i ?>][unit]" class="form-select form-select-sm">
                                <?php foreach (OrderItem::unitOptions() as $u): ?>
                                    <option value="<?= e($u) ?>" <?= ($item['unit'] ?? '件') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">单价</label>
                            <input type="number" step="0.01" min="0" name="items[<?= $i ?>][unit_price]" class="form-control form-control-sm item-price" value="<?= e($item['unit_price']) ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">小计</label>
                            <input type="text" class="form-control form-control-sm item-subtotal" readonly value="<?= e($item['subtotal']) ?>">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="删除">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-12">
                            <input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm" placeholder="备注（可选）" value="<?= e($item['notes'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="text-end mt-2">
        <span class="text-muted">商品合计：</span>
        <strong class="text-primary fs-5" id="items-total">$0.00</strong>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>
new TomSelect('#customer-select-order',{
    create: false,
    placeholder: '输入关键字搜索客户…',
    maxOptions: 500,
    allowEmptyOption: true
});
</script>
