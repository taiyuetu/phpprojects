<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$o = $old ?? $order ?? [];
?>
<div class="row g-3 mb-3">
    <?php $fieldsOwner = new Order(); $values = $o ?? []; ?>
    <?php include APP_PATH . '/views/partials/_fields_auto.php'; ?>
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
    <?php
    // 明细行由共用局部渲染（商机表单用的是同一份），商品目录也随选择框一起带出去
    $rows = (array) ($items ?? []);
    $products = (array) ($products ?? []);
    include APP_PATH . '/views/partials/_items_fields.php';
    ?>
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
