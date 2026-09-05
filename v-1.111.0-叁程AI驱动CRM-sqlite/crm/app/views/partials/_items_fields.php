<?php
/**
 * 明细区（商机表单 / 订单表单共用）。
 *
 * 原来两边各写一份行 markup、JS 里再抄第三份字符串模板，三处一起长，
 * 商品选择框一改就得改三个地方。现在只有这一份：
 * 已有行与「添加商品」克隆出来的新行都从这里出。
 *
 * @var array      $rows      已存在的明细（可为空数组）
 * @var string     $itemKey   行内 name 前缀，默认 items
 * @var array      $products  Product::pickList()
 * @param int      $__i       循环内使用
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$rows = (array) ($rows ?? []);
$products = (array) ($products ?? []);
$emptyIndex = count($rows);
?>
<div id="items-container">
    <?php foreach ($rows as $i => $item): ?>
        <?php include APP_PATH . '/views/partials/_item_row.php'; ?>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
        <?php
        // 至少摆一行空行：新增订单/商机本来就要填明细，比让用户多点一次“添加商品”顺手
        $item = null;
        $rowIndex = 0;
        include APP_PATH . '/views/partials/_item_row.php';
        ?>
    <?php endif; ?>
</div>

<template id="item-row-template">
    <?php
    $item = null;
    $rowIndex = '__IDX__';
    include APP_PATH . '/views/partials/_item_row.php';
    ?>
</template>

<?php include APP_PATH . '/views/products/_picker_js.php'; ?>
