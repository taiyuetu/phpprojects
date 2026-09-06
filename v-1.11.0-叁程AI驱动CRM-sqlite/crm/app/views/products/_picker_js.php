<?php
/**
 * 商品选择框的数据与行为。
 *
 * 目录数据由服务端一次性渲染（$products 来自 Product::pickList()），
 * 所以输入搜索不需要等接口、离线也能用；已有行与“添加商品”克隆出来的新行
 * 共用这一份数据，不会出现“新行的候选比老行少”。
 *
 * @var array $products Product::pickList() 结果
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$products = (array) ($products ?? []);
?>
<script>
window.CRM_PRODUCTS = <?= json_encode(array_map(static function (array $p) {
    return [
        'id' => (int) $p['id'], 'code' => (string) $p['code'], 'name' => (string) $p['name'],
        'sku' => (string) $p['sku'], 'unit' => (string) $p['unit'], 'price' => (float) $p['price'],
        'spec' => (string) $p['spec'], 'brand' => (string) $p['brand'], 'category' => (string) $p['category'],
        'status' => (string) $p['status'],
        'needle' => Product::haystack($p),
    ];
}, $products), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.CRM_PRODUCT_CURRENCY = <?= json_encode(appSetting('currency_symbol', '$')) ?>;
</script>
<script>
window.CrmProductPicker = (function () {
    'use strict';
    var ALL = window.CRM_PRODUCTS || [];

    function norm(s) { return String(s || '').toLowerCase().trim(); }

    function money(v) {
        var n = Number(v || 0);
        return (window.CRM_PRODUCT_CURRENCY || '$') + n.toFixed(2);
    }

    function labelOf(p) {
        var bits = [p.name];
        if (p.sku) bits.push('·' + p.sku);
        var text = bits.join('');
        if (p.spec) text += '（' + p.spec + '）';
        text += '·' + money(p.price) + '/' + p.unit;
        if (p.status !== 'active') text += '〔已停用〕';
        return text;
    }

    function optionEl(p, value, text, attrs) {
        var el = document.createElement('option');
        el.value = value;
        el.textContent = text;
        Object.keys(attrs).forEach(function (k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    function catalogAttrs(p) {
        return {
            'data-needle': p.needle,
            'data-name': p.name,
            'data-sku': p.sku,
            'data-unit': p.unit,
            'data-price': p.price,
            'data-code': p.code,
            'data-index': String(ALL.indexOf(p))
        };
    }

    function build(picker) {
        var sel = picker.querySelector('[data-picker-select]');
        var legacy = null;
        try { legacy = picker.dataset.legacy ? JSON.parse(picker.dataset.legacy) : null; } catch (e) { legacy = null; }

        sel.innerHTML = '';
        sel.appendChild(optionEl({}, '', '— 请选择商品 —', {}));
        if (legacy) {
            // 历史手填行：不因为上了商品库就看不见，但它不是一个可选中的商品
            sel.appendChild(optionEl({}, '', '历史手填：' + (legacy.product_name || '（无名称）'), {
                'data-legacy': '1',
                'data-name': legacy.product_name || '',
                'data-sku': legacy.sku || '',
                'data-unit': legacy.unit || '件',
                'data-price': legacy.unit_price || '0'
            }));
        }
        ALL.forEach(function (p) { sel.appendChild(optionEl(p, String(p.id), labelOf(p), catalogAttrs(p))); });

        var want = picker.dataset.selected || '';
        if (want) {
            sel.value = want;
            if (sel.value !== want) sel.value = '';   // 目录里没有这个 id（被删了）→ 保持未选
        }
        return sel;
    }

    function matches(query) {
        var q = norm(query);
        if (q === '') return ALL.slice();
        return ALL.filter(function (p) {
            return p.needle.indexOf(q) !== -1 || String(p.name).toLowerCase().indexOf(q) !== -1;
        });
    }

    function applyFilter(picker, query) {
        var sel = picker.querySelector('[data-picker-select]');
        var empty = picker.querySelector('[data-picker-empty]');
        var hits = matches(query);
        var current = sel.value;

        // 整体重建：placeholder 与“历史手填”永远留着，否则一搜索就把它们挤掉
        sel.innerHTML = '';
        sel.appendChild(optionEl({}, '', '— 请选择商品 —', {}));
        var legacy = null;
        try { legacy = picker.dataset.legacy ? JSON.parse(picker.dataset.legacy) : null; } catch (e) { legacy = null; }
        if (legacy) {
            sel.appendChild(optionEl({}, '', '历史手填：' + (legacy.product_name || '（无名称）'), {
                'data-legacy': '1', 'data-name': legacy.product_name || '', 'data-sku': legacy.sku || '',
                'data-unit': legacy.unit || '件', 'data-price': legacy.unit_price || '0'
            }));
        }
        hits.forEach(function (p) { sel.appendChild(optionEl(p, String(p.id), labelOf(p), catalogAttrs(p))); });

        if (current && hits.some(function (p) { return String(p.id) === current; })) {
            sel.value = current;
        } else if (current) {
            sel.value = '';
        }
        // 唯一命中就直接选上：这就是“输入即选定”，比再点一下下拉快
        if (norm(query) !== '' && hits.length === 1) {
            sel.value = String(hits[0].id);
            emit(picker, sel);
        }
        if (empty) empty.classList.toggle('d-none', !(norm(query) !== '' && hits.length === 0));
        picker.setAttribute('data-hits', String(hits.length));
    }

    function emit(picker, sel) {
        var o = sel.options[sel.selectedIndex];
        var detail = {
            productId: sel.value,
            name: o ? (o.getAttribute('data-name') || '') : '',
            sku: o ? (o.getAttribute('data-sku') || '') : '',
            unit: o ? (o.getAttribute('data-unit') || '') : '',
            price: o ? (o.getAttribute('data-price') || '') : '',
            code: o ? (o.getAttribute('data-code') || '') : '',
            legacy: !!(o && o.getAttribute('data-legacy') === '1')
        };
        picker.dispatchEvent(new CustomEvent('product:picked', { bubbles: true, detail: detail }));
    }

    function showSelectedName(picker) {
        var sel = picker.querySelector('[data-picker-select]');
        var input = picker.querySelector('.picker-search');
        var o = sel.options[sel.selectedIndex];
        if (input && o && o.value !== '') {
            input.value = (o.getAttribute('data-name') || o.text).trim();
        }
    }

    function init(picker) {
        if (picker.__ready) return;
        picker.__ready = true;
        var input = picker.querySelector('.picker-search');
        var sel = build(picker);
        if (!input || !sel) return;

        input.addEventListener('input', function () {
            // 改字就是在换商品：先清掉已选值，免得“名字改了、id 还是上一个”
            if (sel.value !== '') { sel.value = ''; }
            applyFilter(picker, input.value);
        });
        input.addEventListener('focus', function () { applyFilter(picker, input.value); });
        input.addEventListener('blur', function () {
            if (!sel.value) { applyFilter(picker, ''); input.value = ''; }
            else { showSelectedName(picker); }
        });
        sel.addEventListener('change', function () {
            if (sel.value !== '') { applyFilter(picker, ''); }
            showSelectedName(picker);
            emit(picker, sel);
        });

        showSelectedName(picker);
        if (sel.value) emit(picker, sel);
    }

    function initAll(root) {
        (root || document).querySelectorAll('.product-picker').forEach(init);
    }

    // 克隆出新行后调用：清掉上一行的状态，让新行从“未选”开始
    function reset(picker) {
        picker.__ready = false;
        delete picker.dataset.selected;
        delete picker.dataset.legacy;
        var input = picker.querySelector('.picker-search');
        if (input) input.value = '';
        var empty = picker.querySelector('[data-picker-empty]');
        if (empty) empty.classList.add('d-none');
        init(picker);
    }

    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
    return { init: init, initAll: initAll, reset: reset, matches: matches };
})();
</script>
