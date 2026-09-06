<?php
/**
 * 自动字段渲染 partial —— 新增字段的表单端自动接线。
 *
 * 用法：在 _form.php 的网格里放一行即可，字段来自模型注册表：
 *
 *     <?php $fieldsOwner = new Customer(); $values = $c ?? []; ?>
 *     <?php include APP_PATH . '/views/partials/_fields_auto.php'; ?>
 *
 * 只有注册表里标了 `'form' => true`（或其布局数组）的列才会在这里出现，
 * 手写精修的列不标就不会重复渲染。于是“加一个字段”只改注册表一处：
 *   1) schema.sql / 迁移加列；2) 模型 $fields 加一行并带 'form' => true
 * → 搜索、校验、AI、表单全部自动覆盖，视图零改动。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$fieldsOwner = $fieldsOwner ?? null;
$values = is_array($values ?? null) ? $values : [];
if ($fieldsOwner === null || !method_exists($fieldsOwner, 'autoFormFields')) {
    return;
}
$autoFields = $fieldsOwner->autoFormFields();
if ($autoFields === []) {
    return;                     // 没有待自动渲染的列：什么都不输出，不影响手写布局
}
$enumOptions = static fn(string $f): ?array => $fieldsOwner->fieldEnumOptions($f);
foreach ($autoFields as $name => $field) {
    echo Fields::block($field, $values, ['enumOptions' => $enumOptions]);
}
