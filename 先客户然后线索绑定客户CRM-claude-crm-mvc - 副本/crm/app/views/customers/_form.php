<?php $c = $old ?? $customer ?? []; ?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">姓名 *</label>
        <input type="text" name="name" class="form-control" value="<?= e($c['name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">公司</label>
        <input type="text" name="company" class="form-control" value="<?= e($c['company'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">邮箱</label>
        <input type="email" name="email" class="form-control" value="<?= e($c['email'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">电话</label>
        <input type="text" name="phone" class="form-control" value="<?= e($c['phone'] ?? '') ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label">地址</label>
        <input type="text" name="address" class="form-control" value="<?= e($c['address'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">状态</label>
        <select name="status" class="form-select">
            <option value="active" <?= ($c['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>活跃</option>
            <option value="inactive" <?= ($c['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>非活跃</option>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">备注</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($c['notes'] ?? '') ?></textarea>
    </div>
</div>
