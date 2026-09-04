<?php $d = $old ?? $deal ?? []; ?>

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
    <div id="items-container">
        <!-- Default empty item row -->
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
    </div>
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
