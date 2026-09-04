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
    <div class="col-md-4">
        <label class="form-label">WhatsApp</label>
        <input type="text" name="whatsapp" class="form-control" value="<?= e($c['whatsapp'] ?? '') ?>" placeholder="号码或链接">
    </div>
    <div class="col-md-4">
        <label class="form-label">微信</label>
        <input type="text" name="wechat" class="form-control" value="<?= e($c['wechat'] ?? '') ?>" placeholder="微信号">
    </div>
    <div class="col-md-4">
        <label class="form-label">Facebook 主页</label>
        <input type="url" name="facebook" class="form-control" value="<?= e($c['facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
    </div>
    <div class="col-md-4">
        <label class="form-label">TikTok 频道</label>
        <input type="url" name="tiktok" class="form-control" value="<?= e($c['tiktok'] ?? '') ?>" placeholder="https://tiktok.com/...">
    </div>
    <div class="col-md-4">
        <label class="form-label">官方网站</label>
        <input type="url" name="website" class="form-control" value="<?= e($c['website'] ?? '') ?>" placeholder="https://...">
    </div>
    <div class="col-md-4">
        <label class="form-label">来源国家</label>
        <input type="text" name="source_country" class="form-control" value="<?= e($c['source_country'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">来源城市</label>
        <input type="text" name="source_city" class="form-control" value="<?= e($c['source_city'] ?? '') ?>">
    </div>
    <div class="col-md-8">
        <label class="form-label">地址</label>
        <input type="text" name="address" class="form-control" value="<?= e($c['address'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label">客户新建/转化时间</label>
        <input type="datetime-local" name="conversion_time" class="form-control" value="<?= e($c['conversion_time'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label">收货地址</label>
        <textarea name="shipping_address" class="form-control" rows="2"><?= e($c['shipping_address'] ?? '') ?></textarea>
    </div>
    <div class="col-md-3">
        <label class="form-label">状态</label>
        <select name="status" class="form-select">
            <option value="active" <?= ($c['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>活跃</option>
            <option value="inactive" <?= ($c['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>非活跃</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">&nbsp;</label>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="first_purchase_from_china" id="firstPurchase" value="1"
                <?= !empty($c['first_purchase_from_china']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="firstPurchase">第一次从中国采购</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label d-block">&nbsp;</label>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="has_import_capability" id="importCap" value="1"
                <?= !empty($c['has_import_capability']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="importCap">有进口能力</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">备注</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($c['notes'] ?? '') ?></textarea>
    </div>
</div>
