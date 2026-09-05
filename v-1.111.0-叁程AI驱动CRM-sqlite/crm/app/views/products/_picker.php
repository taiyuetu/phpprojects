<?php
/**
 * 商品选择框：上方输入框搜索，下方保留原生 select 直接下拉选。
 *
 * 为什么两个都要：加明细的人分两种——一种习惯打字（“6206”三个字符就筛完了），
 * 一种习惯翻列表。只做输入框等于强迫后者每次都要想到关键词；只做 select 则让前者
 * 在几百个 option 里滚动。两者共用同一个值，谁顺手用哪个。
 *
 * 值只落在 select（product_id）上，输入框只是过滤器 + 已选名称的回显，
 * 所以禁用 JS 时它仍然是一个普通下拉框，表单照常提交。
 * option 列表由 _picker_js.php 从 window.CRM_PRODUCTS 生成：
 * 页面里已有的行、以及“添加商品”新克隆的行，用的是同一份数据，不会长得不一样。
 *
 * @var string $name     select 的 name，如 items[0][product_id]
 * @var string $selected 已选中的 product_id
 * @var array|null $legacy 历史手填行（没关联商品时用它保住在屏数据）
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$name = (string) ($name ?? 'product_id');
$selected = (string) ($selected ?? '');
$legacyJson = is_array($legacy ?? null) ? json_encode((array) $legacy, JSON_UNESCAPED_UNICODE) : '';
// 有 legacy 时不加 required：这种行允许“原样保留”，不能被浏览器拦在提交前；
// 一旦用户改了名称或价格，服务端仍会要求他从商品库里选。
//
// 历史行名称是用户可自由输入的快照文本，必须整体当作普通文本转义后再放进
// 单引号属性：json_encode 不转义单引号，裸输出时一个 ' 就能关掉 data-legacy
// 属性注入任意 HTML（存储型 XSS）。e() 把引号 / < > / & 变成实体，浏览器解析
// 属性时自动解码，所以 dataset.legacy 拿到的仍是原样 JSON，JSON.parse 不受影响。
?>
<div class="product-picker"
     data-selected="<?= e($selected) ?>"
     <?php if ($legacyJson !== ''): ?>data-legacy='<?= e($legacyJson) ?>'<?php endif; ?>>
    <input type="text"
           class="form-control form-control-sm mb-1 picker-search"
           placeholder="搜索商品：名称 / SKU / 规格"
           autocomplete="off"
           aria-label="搜索商品">
    <select name="<?= e($name) ?>"
            class="form-select form-select-sm picker-select"
            data-picker-select
            <?= $legacy !== null ? '' : 'required' ?>
            aria-label="选择商品"></select>
    <div class="form-text picker-hint text-danger d-none py-1" data-picker-empty>
        商品库里没有匹配的商品。<a href="<?= url('/products/create') ?>" target="_blank" rel="noopener">去新建一个 →</a>
        或点右上「商品库」维护目录。
    </div>
</div>
