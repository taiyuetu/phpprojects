<?php $l = $old ?? $lead ?? []; ?>

<div class="row g-3 mb-3">
    <div class="col-md-12">
        <label class="form-label">线索标题 *</label>
        <input type="text" name="title" class="form-control" value="<?= e($l['title'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">联系人</label>
        <input type="text" name="contact_name" class="form-control" value="<?= e($l['contact_name'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">联系邮箱</label>
        <input type="email" name="contact_email" class="form-control" value="<?= e($l['contact_email'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">来源</label>
        <input type="text" name="source" class="form-control" value="<?= e($l['source'] ?? '') ?>" placeholder="网站、推荐…">
    </div>
    <div class="col-md-4">
        <label class="form-label">预估金额</label>
        <input type="number" step="0.01" min="0" name="value" class="form-control" value="<?= e($l['value'] ?? 0) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">状态</label>
        <select name="status" class="form-select">
            <?php foreach (['new', 'contacted', 'qualified', 'lost'] as $s): ?>
                <?php $zhStatus = ['new'=>'新建','contacted'=>'已联系','qualified'=>'已确认','lost'=>'已流失']; ?>
                <option value="<?= $s ?>" <?= ($l['status'] ?? 'new') === $s ? 'selected' : '' ?>><?= e($zhStatus[$s] ?? $s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">备注</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($l['notes'] ?? '') ?></textarea>
    </div>
</div>
