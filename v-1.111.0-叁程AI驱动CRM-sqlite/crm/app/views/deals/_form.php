<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$d = $old ?? $deal ?? [];
?>
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <label class="form-label">商机名称 *</label>
        <input type="text" name="title" class="form-control" value="<?= e($d['title'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">客户 *</label>
        <select name="customer_id" id="customer-select-deal" class="form-select" required>
            <option value="">选择客户…</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($d['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?><?= $c['company'] ? ' (' . e($c['company']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">金额</label>
        <input type="number" step="0.01" min="0" name="value" class="form-control" value="<?= e($d['value'] ?? 0) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">阶段</label>
        <select name="stage" class="form-select" id="deal-stage-select">
            <?php foreach (['open' => '进行中', 'proposal' => '方案阶段', 'negotiation' => '谈判中', 'closed_won' => '成交', 'closed_lost' => '丢单'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($d['stage'] ?? 'open') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">预计成交日期</label>
        <input type="date" name="close_date" class="form-control" value="<?= e(is_string($d['close_date'] ?? null) ? substr($d['close_date'], 0, 10) : '') ?>">
    </div>
</div>

<!-- 商品明细区域（仅当阶段为 closed_won 时显示） -->
<div id="items-section" class="mt-3" style="<?= ($d['stage'] ?? '') === 'closed_won' ? '' : 'display:none;' ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-muted mb-0"><i class="bi bi-box-seam me-1"></i>商品明细（成交后自动生成订单）</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">
            <i class="bi bi-plus-lg"></i> 添加商品
        </button>
    </div>
    <?php
    // 与订单表单同一份明细行局部：商品选择框、数量、单价、小计都长一样，不改两处
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
new TomSelect('#customer-select-deal',{
    create: false,
    placeholder: '输入关键字搜索客户…',
    maxOptions: 500,
    allowEmptyOption: true
});
</script>
