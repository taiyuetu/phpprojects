<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$l = $old ?? $lead ?? [];
?>
<div class="row g-3 mb-3">
    <?php $fieldsOwner = new Lead(); $values = $l ?? []; ?>
    <?php include APP_PATH . '/views/partials/_fields_auto.php'; ?>
    <div class="col-md-12">
        <label class="form-label">线索标题 *</label>
        <input type="text" name="title" class="form-control" value="<?= e($l['title'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">公司</label>
        <input type="text" name="company" class="form-control" value="<?= e($l['company'] ?? '') ?>">
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
        <label class="form-label">电话号码</label>
        <input type="text" name="phone" class="form-control" value="<?= e($l['phone'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">WhatsApp</label>
        <input type="text" name="whatsapp" class="form-control" value="<?= e($l['whatsapp'] ?? '') ?>" placeholder="号码或链接">
    </div>
    <div class="col-md-4">
        <label class="form-label">线索时间</label>
        <input type="datetime-local" name="lead_time" class="form-control" value="<?= e($l['lead_time'] ?? '') ?>">
    </div>

    <!-- 社交媒体 & 网站 -->
    <div class="col-md-4">
        <label class="form-label">Facebook 主页</label>
        <input type="url" name="facebook" class="form-control" value="<?= e($l['facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
    </div>
    <div class="col-md-4">
        <label class="form-label">TikTok 频道</label>
        <input type="url" name="tiktok" class="form-control" value="<?= e($l['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/...">
    </div>
    <div class="col-md-4">
        <label class="form-label">官方网站</label>
        <input type="url" name="website" class="form-control" value="<?= e($l['website'] ?? '') ?>" placeholder="https://...">
    </div>

    <!-- 来源 & 地址 -->
    <div class="col-md-4">
        <label class="form-label">来源</label>
        <input type="text" name="source" class="form-control" value="<?= e($l['source'] ?? '') ?>" placeholder="网站、推荐…">
    </div>
    <div class="col-md-4">
        <label class="form-label">来源国家</label>
        <input type="text" name="source_country" class="form-control" value="<?= e($l['source_country'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">来源城市</label>
        <input type="text" name="source_city" class="form-control" value="<?= e($l['source_city'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label">具体地址</label>
        <input type="text" name="address" class="form-control" value="<?= e($l['address'] ?? '') ?>">
    </div>

    <!-- 采购信息 -->
    <div class="col-md-3">
        <label class="form-label">预估金额</label>
        <input type="number" step="0.01" min="0" name="value" class="form-control" value="<?= e($l['value'] ?? 0) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">状态</label>
        <select name="status" class="form-select" id="leadStatus" onchange="toggleLostReason()">
            <?php foreach (['new', 'contacted', 'qualified', 'lost'] as $s): ?>
                <?php $zhStatus = ['new'=>'新建','contacted'=>'已联系','qualified'=>'已确认','lost'=>'已流失']; ?>
                <option value="<?= $s ?>" <?= ($l['status'] ?? 'new') === $s ? 'selected' : '' ?>><?= e($zhStatus[$s] ?? $s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">&nbsp;</label>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="first_purchase_from_china" id="firstPurchase" value="1"
                <?= !empty($l['first_purchase_from_china']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="firstPurchase">第一次从中国采购</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">&nbsp;</label>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="has_import_capability" id="importCap" value="1"
                <?= !empty($l['has_import_capability']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="importCap">有进口能力</label>
        </div>
    </div>

    <div class="col-md-4" id="lostReasonDiv" style="display: <?= ($l['status'] ?? '') === 'lost' ? 'block' : 'none' ?>;">
        <label class="form-label">流失原因 <span class="text-danger">*</span></label>
        <select name="lost_reason" class="form-select" id="lostReason">
            <option value="">请选择...</option>
            <?php foreach (Lead::lostReasonOptions() as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($l['lost_reason'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">备注</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($l['notes'] ?? '') ?></textarea>
    </div>
</div>

<script>
function toggleLostReason() {
    const status = document.getElementById('leadStatus').value;
    const div = document.getElementById('lostReasonDiv');
    const select = document.getElementById('lostReason');
    
    if (status === 'lost') {
        div.style.display = 'block';
        select.required = true;
    } else {
        div.style.display = 'none';
        select.required = false;
        select.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleLostReason);
</script>
