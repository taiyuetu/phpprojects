<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$d = $old ?? $deal ?? [];
// 新建页（含校验失败回显）不传 $deal；编辑页传。成交/丢单是流转的终点，只能从编辑页推进。
$creating = empty($deal);

// “金额”字段初始的自动/手动开关：值为 0，或与当前明细小计一致 → 自动（data-auto=1）；
// 值与明细不一致（手改过 / 意向金额）→ 手动（data-auto=0），打开页面时不被自动改写。
// 注意：页面里只要用户再动一次商品行（选商品/改数量单价/加删行）就会重新接管并刷新。
$formRows = is_array($items ?? null) ? (array) $items : [];
$formRowsTotal = 0.0;
foreach ($formRows as $__item) {
    $formRowsTotal += (float) ($__item['quantity'] ?? 0) * (float) ($__item['unit_price'] ?? 0);
}
$dealValue = (float) ($d['value'] ?? 0);
$valueAuto = ($dealValue == 0.0 || abs($dealValue - $formRowsTotal) < 0.005) ? '1' : '0';
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
        <label class="form-label">金额 <span class="text-muted small fw-normal">（明细合计，可手改）</span></label>
        <input type="number" step="0.01" min="0" name="value" id="deal-value-input" class="form-control"
               data-auto="<?= $valueAuto ?>"
               value="<?= e($d['value'] ?? 0) ?>"
               title="选择商品/改数量后按明细小计自动合计；自己手填的金额会保留到你下一次改动商品行为止">
    </div>
    <div class="col-md-4">
        <label class="form-label">阶段</label>
        <select name="stage" class="form-select" id="deal-stage-select">
            <?php $stageOptions = $creating
                ? ['open' => '进行中', 'proposal' => '方案阶段', 'negotiation' => '谈判中']
                : ['open' => '进行中', 'proposal' => '方案阶段', 'negotiation' => '谈判中', 'closed_won' => '成交', 'closed_lost' => '丢单'];
            foreach ($stageOptions as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($d['stage'] ?? 'open') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($creating): ?>
            <div class="form-text">先以「进行中」建档；推进到成交 / 丢单在编辑页进行</div>
        <?php endif; ?>
    </div>
    <div class="col-md-4">
        <label class="form-label">预计成交日期</label>
        <input type="date" name="close_date" class="form-control" value="<?= e(is_string($d['close_date'] ?? null) ? substr($d['close_date'], 0, 10) : '') ?>">
    </div>
</div>

<!-- 商品明细：与订单表单同一份局部，任何阶段都可见、可先填。
     只是这些行只在“保存时处于成交阶段”才会落成订单明细（见 DealController::update）。 -->
<div id="items-section" class="mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-muted mb-0"><i class="bi bi-box-seam me-1"></i>商品明细（成交保存后自动生成订单）</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-item">
            <i class="bi bi-plus-lg"></i> 添加商品
        </button>
    </div>
    <?php
    // 与订单表单同一份明细行局部：商品选择框、数量、单价、小计都长一样，不改两处
    $rows = (array) ($items ?? []);
    $products = (array) ($products ?? []);
    // 商机明细对“还没成交”的保存是可选的，不设 required——否则开放阶段的空行
    // 会触发浏览器 “invalid form control … is not focusable” 拦住整张表单。
    $pickerRequired = false;
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
