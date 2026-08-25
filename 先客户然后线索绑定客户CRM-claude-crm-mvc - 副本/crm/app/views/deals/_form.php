<?php $d = $old ?? $deal ?? []; ?>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <label class="form-label">商机名称 *</label>
        <input type="text" name="title" class="form-control" value="<?= e($d['title'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">客户 *</label>
        <select name="customer_id" class="form-select" required>
            <option value="">选择客户…</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($d['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
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
        <select name="stage" class="form-select">
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
