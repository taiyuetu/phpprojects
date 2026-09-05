<?php
/** @var array $old 表单回填值
 * @var array $errors
 * @var string $action 提交地址
 * @var string $submitText
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$val = static fn(string $k, $d = '') => e((string) (($old[$k] ?? $d)));
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
        <?php foreach ($errors as $err): ?><div><?= e((string) $err) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action ?? url('/products')) ?>" class="card card-table p-4">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">商品名称 <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required maxlength="150"
                   value="<?= $val('name') ?>" placeholder="如：6206 深沟球轴承">
            <div class="form-text">商机与订单的明细会从商品库里搜到这个名称。</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">SKU / 货号</label>
            <input type="text" name="sku" class="form-control" maxlength="60" value="<?= $val('sku') ?>"
                   placeholder="如：BRG-6206">
            <div class="form-text">填了就不能与别的商品重复；留空则不做约束。</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <?php foreach (Product::statusOptions() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($old['status'] ?? 'active') === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">停用后不再出现在选择框里，历史订单不受影响。</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">分类</label>
            <input type="text" name="category" class="form-control" maxlength="60" value="<?= $val('category') ?>"
                   placeholder="如：轴承 / 软件授权 / 服务">
        </div>
        <div class="col-md-3">
            <label class="form-label">品牌</label>
            <input type="text" name="brand" class="form-control" maxlength="60" value="<?= $val('brand') ?>" placeholder="如">
        </div>
        <div class="col-md-6">
            <label class="form-label">规格 / 型号</label>
            <input type="text" name="spec" class="form-control" maxlength="150" value="<?= $val('spec') ?>"
                   placeholder="如：6206-2RS / 内径30 外径62 宽16">
        </div>

        <div class="col-md-3">
            <label class="form-label">单价 <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><?= e(appSetting('currency_symbol', '$')) ?></span>
                <input type="number" name="price" class="form-control" step="0.01" min="0" required
                       value="<?= e((string) ($old['price'] ?? '0')) ?>">
            </div>
            <div class="form-text">选择商品时会自动带出这个价，明细里仍可临时改价。</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">参考价（成本）</label>
            <input type="number" name="cost" class="form-control" step="0.01" min="0"
                   value="<?= e((string) ($old['cost'] ?? '')) ?>" placeholder="可留空">
        </div>
        <div class="col-md-3">
            <label class="form-label">单位</label>
            <select name="unit" class="form-select">
                <?php foreach (Product::unitOptions() as $u): ?>
                    <option value="<?= e($u) ?>" <?= ($old['unit'] ?? '件') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12">
            <label class="form-label">备注</label>
            <textarea name="notes" class="form-control" rows="2" maxlength="1000"><?= $val('notes') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><?= e($submitText ?? '保存商品') ?></button>
        <a href="<?= url('/products') ?>" class="btn btn-outline-secondary">取消</a>
    </div>
</form>
