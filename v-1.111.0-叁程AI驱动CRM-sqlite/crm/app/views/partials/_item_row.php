<?php
/**
 * 一条商品明细行（商机表单与订单表单共用）。
 *
 * 之前两边各写一份行 markup、JS 里再抄第三份模板，三份长在一起迟早不一致
 * （商品的自动回填要同时改三处）。现在只这一份，新行由 JS 克隆它再改索引。
 *
 * @var int        $rowIndex  items[N] 的 N
 * @var array|null $item      已存在的明细（编辑时）
 * @var string     $rowLabel  列标题里的必填标记，可选
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
// 允许 '__IDX__' 这个占位索引：JS 克隆模板时整体替换，所以不能强转 int（会变成 0）
$i = (string) ($rowIndex ?? '0');
if ($i !== '__IDX__' && !ctype_digit($i)) {
    $i = '0';
}
$item = is_array($item ?? null) ? (array) $item : null;
$pid = (string) (($item['product_id'] ?? '') !== null ? (string) ($item['product_id'] ?? '') : '');
$legacy = null;
if ($item !== null && $pid === '') {
    // 没关联商品的历史行：允许原样留住，但一改就得从商品库里选
    $legacy = [
        'product_name' => (string) ($item['product_name'] ?? ''),
        'sku'          => (string) ($item['sku'] ?? ''),
        'unit'         => (string) ($item['unit'] ?? '件'),
        'unit_price'   => (string) ($item['unit_price'] ?? '0'),
    ];
}
?>
<div class="item-row card card-table p-3 mb-2<?= $legacy !== null ? ' item-row-legacy' : '' ?>"
     data-item-row>
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small">商品 <span class="text-danger">*</span></label>
            <?php
            $name = 'items[' . $i . '][product_id]';
            $selected = $pid;
            include APP_PATH . '/views/products/_picker.php';
            ?>
            <?php if ($legacy !== null): ?>
                <input type="hidden" name="items[<?= $i ?>][legacy_name]" value="<?= e($legacy['product_name']) ?>">
                <input type="hidden" name="items[<?= $i ?>][legacy_price]" value="<?= e($legacy['unit_price']) ?>">
                <div class="form-text py-1 text-warning">
                    <i class="bi bi-exclamation-triangle"></i> 这是升级前手填的明细。
                    不改它可以原样保留；一改名/价就必须从商品库里选一个商品。
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-1">
            <label class="form-label small">数量</label>
            <input type="number" step="0.01" min="0" name="items[<?= $i ?>][quantity]"
                   class="form-control form-control-sm item-qty" value="<?= e((string) ($item['quantity'] ?? '1')) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label small">单位</label>
            <select name="items[<?= $i ?>][unit]" class="form-select form-select-sm item-unit">
                <?php foreach (OrderItem::unitOptions() as $u): ?>
                    <option value="<?= e($u) ?>" <?= ((string) ($item['unit'] ?? '件')) === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">单价</label>
            <input type="number" step="0.01" min="0" name="items[<?= $i ?>][unit_price]"
                   class="form-control form-control-sm item-price" value="<?= e((string) ($item['unit_price'] ?? '0')) ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label small">小计</label>
            <input type="text" class="form-control form-control-sm item-subtotal" readonly
                   value="<?= e(number_format((float) ($item['subtotal'] ?? 0), 2)) ?>">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="删除这一行">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
    <div class="row g-2 mt-1">
        <div class="col-md-4">
            <label class="form-label small">名称快照（可改，只影响这一行）</label>
            <input type="text" name="items[<?= $i ?>][product_name]" class="form-control form-control-sm item-name"
                   maxlength="150" value="<?= e((string) ($item['product_name'] ?? '')) ?>" placeholder="选中商品后自动带出">
        </div>
        <div class="col-md-2">
            <label class="form-label small">SKU 快照</label>
            <input type="text" name="items[<?= $i ?>][sku]" class="form-control form-control-sm item-sku"
                   maxlength="60" value="<?= e((string) ($item['sku'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small">备注</label>
            <input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm"
                   maxlength="500" placeholder="如：含安装调试、需木箱包装"
                   value="<?= e((string) ($item['notes'] ?? '')) ?>">
        </div>
    </div>
</div>
