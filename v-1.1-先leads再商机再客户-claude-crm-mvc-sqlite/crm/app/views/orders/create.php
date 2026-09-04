<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">新建订单</h3>
    <a href="<?= url('/orders') ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
</div>

<div class="card card-table p-4" style="max-width:1000px;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/orders') ?>" id="order-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <?php include __DIR__ . '/_form.php'; ?>
        <button type="submit" class="btn btn-primary mt-3">创建订单</button>
    </form>
</div>

<?php include __DIR__ . '/_items_js.php'; ?>
