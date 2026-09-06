<?php
/** @var string $csrf @var array $old @var array $errors
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<h3 class="mb-4"><i class="bi bi-plus-circle me-2"></i>新增分类</h3>

<?php
$action = url('/categories');
$editing = false;
$submitText = '保存分类';
include __DIR__ . '/_form.php';
?>
