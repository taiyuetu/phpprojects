<?php
/** @var string $csrf @var array $old @var array $errors
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<?php $action = url('/products'); $submitText = '保存商品'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">新增商品</h3>
    <a href="<?= url('/products') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回商品库</a>
</div>

<?php include APP_PATH . '/views/products/_form.php'; ?>
