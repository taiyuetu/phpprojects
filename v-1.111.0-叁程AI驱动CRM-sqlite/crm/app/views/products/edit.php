<?php
/** @var array $product @var array $usage @var string $csrf @var array $old @var array $errors
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$action = url('/products/' . (int) $product['id']);
$submitText = '保存修改';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">编辑商品
        <span class="badge bg-light text-dark border align-middle"><?= e((string) $product['public_code']) ?></span>
    </h3>
    <a href="<?= url('/products') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回商品库</a>
</div>

<?php if ((int) ($usage['items'] ?? 0) > 0): ?>
    <div class="alert alert-info small">
        这个商品已被 <strong><?= (int) $usage['items'] ?></strong> 条订单明细（<?= (int) $usage['orders'] ?> 张订单）引用。
        改名称或改价<strong>不会</strong>回溯修改历史订单：明细里存的是成交当时的快照。
    </div>
<?php endif; ?>

<?php include APP_PATH . '/views/products/_form.php'; ?>
