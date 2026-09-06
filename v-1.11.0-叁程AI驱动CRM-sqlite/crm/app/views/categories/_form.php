<?php
/** @var array $old @var array $errors @var string $action @var string $submitText
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

<form method="POST" action="<?= e($action ?? url('/categories')) ?>" class="card card-table p-4">
    <?php if (!empty($editing)): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">分类名称 <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required maxlength="60"
                   value="<?= $val('name') ?>" placeholder="如：轴承 / 软件授权 / 服务">
            <div class="form-text">分类名称不能重复。</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">排序</label>
            <input type="number" name="sort_order" class="form-control" min="0" step="1"
                   value="<?= $val('sort_order', '0') ?>">
            <div class="form-text">数字越小越靠前，默认 0。</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">状态</label>
            <select name="status" class="form-select">
                <?php foreach (Category::statusOptions() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($old['status'] ?? 'active') === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">停用后不会出现在商品的分类下拉里。</div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><?= e($submitText ?? '保存分类') ?></button>
        <a href="<?= url('/categories') ?>" class="btn btn-outline-secondary">取消</a>
    </div>
</form>
