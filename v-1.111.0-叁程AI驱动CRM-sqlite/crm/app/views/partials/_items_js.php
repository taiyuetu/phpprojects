<?php
/**
 * 明细行公共行为：加行、删行、算小计与合计，以及“选中商品后回填本行”。
 *
 * 商机表单与订单表单共用这一份。之前两处各写了一遍行逻辑、又在 JS 里
 * 抄了第三份行模板，改一次要同步三处；现在新行由 <template> 克隆而来，
 * 模板与页面渲染的行是同一份 markup。
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<script>
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        const container = document.getElementById('items-container');
        const totalEl = document.getElementById('items-total');
        const addBtn = document.getElementById('btn-add-item');
        const template = document.getElementById('item-row-template');
        if (!container || !totalEl) return;

        let itemIndex = container.querySelectorAll('.item-row').length;
        const amountDisplay = document.getElementById('order-amount-display');
        const amountHidden = document.getElementById('order-amount-hidden');

        function money(n) { return (window.CRM_PRODUCT_CURRENCY || '$') + n.toFixed(2); }

        function calcRow(row) {
            const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
            const subtotal = Math.round(qty * price * 100) / 100;
            const subEl = row.querySelector('.item-subtotal');
            if (subEl) subEl.value = subtotal.toFixed(2);
            return subtotal;
        }

        function calcTotal() {
            let total = 0;
            container.querySelectorAll('.item-row').forEach(function (row) { total += calcRow(row); });
            totalEl.textContent = money(total);
            if (amountDisplay) amountDisplay.value = money(total);
            if (amountHidden) amountHidden.value = total.toFixed(2);
        }

        // 选中商品 → 名称/SKU/单位/单价回填本行。
        // 名称只在“空着”或“上次也是自动填的”时候覆盖：用户自己改过的名字不能被他选的商品冲掉。
        container.addEventListener('product:picked', function (ev) {
            const row = ev.target.closest ? ev.target.closest('.item-row') : null;
            const d = ev.detail || {};
            if (!row || !d.productId) return;

            const nameEl = row.querySelector('.item-name');
            if (nameEl && (!nameEl.value || nameEl.dataset.auto === '1')) {
                nameEl.value = d.name || '';
                nameEl.dataset.auto = '1';
            }
            const skuEl = row.querySelector('.item-sku');
            if (skuEl && (!skuEl.value || skuEl.dataset.auto === '1')) {
                skuEl.value = d.sku || '';
                skuEl.dataset.auto = '1';
            }
            const unitEl = row.querySelector('.item-unit');
            if (unitEl && d.unit) unitEl.value = d.unit;
            const priceEl = row.querySelector('.item-price');
            if (priceEl) priceEl.value = d.price || '0';

            // 一旦真的选了商品，就把“这一行还是历史手填”的标记与提示撤掉
            row.classList.remove('item-row-legacy');
            row.querySelectorAll('[name$="[legacy_name]"], [name$="[legacy_price]"]').forEach(function (el) { el.remove(); });
            const warn = row.querySelector('.form-text.text-warning');
            if (warn) warn.remove();
            calcTotal();
        });

        // 用户手改过名称/SKU 就不再自动覆盖
        container.addEventListener('input', function (ev) {
            const t = ev.target;
            if (t.classList.contains('item-name') || t.classList.contains('item-sku')) {
                if (t.dataset.auto === '1' && t.value === '') return;
                t.dataset.auto = '0';
            }
            if (t.classList.contains('item-qty') || t.classList.contains('item-price')) calcTotal();
        });

        container.addEventListener('click', function (ev) {
            const btn = ev.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.item-row');
            const rows = container.querySelectorAll('.item-row');
            if (rows.length > 1) {
                row.remove();
            } else {
                // 只剩一行时清空而不删掉：表单永远有的填
                row.querySelectorAll('input:not([readonly])').forEach(function (el) { el.value = ''; });
                row.querySelectorAll('[data-legacy], input[type=hidden]').forEach(function (el) { el.value = ''; });
                const picker = row.querySelector('.product-picker');
                if (picker) {
                    picker.removeAttribute('data-selected');
                    picker.removeAttribute('data-legacy');
                    const sel = picker.querySelector('[data-picker-select]');
                    if (sel) sel.value = '';
                    const input = picker.querySelector('.picker-search');
                    if (input) input.value = '';
                }
                const qty = row.querySelector('.item-qty'); if (qty) qty.value = '1';
                const price = row.querySelector('.item-price'); if (price) price.value = '0';
            }
            calcTotal();
        });

        if (addBtn && template) {
            addBtn.addEventListener('click', function () {
                const html = template.innerHTML.replace(/__IDX__/g, String(itemIndex));
                container.insertAdjacentHTML('beforeend', html);
                const row = container.lastElementChild;
                itemIndex++;
                row.querySelectorAll('input:not([readonly])').forEach(function (el) { el.value = ''; });
                const qty = row.querySelector('.item-qty'); if (qty) qty.value = '1';
                const price = row.querySelector('.item-price'); if (price) price.value = '0';
                // 克隆来的行还带着模板里的 data-selected，必须清掉再初始化，否则新行会预选中同一个商品
                row.querySelectorAll('.product-picker').forEach(function (picker) {
                    picker.removeAttribute('data-selected');
                    picker.removeAttribute('data-legacy');
                    picker.removeAttribute('__ready');
                    delete picker.dataset.selected;
                    delete picker.dataset.legacy;
                    if (window.CrmProductPicker) window.CrmProductPicker.init(picker);
                });
                calcTotal();
                const first = row.querySelector('.picker-search');
                if (first) first.focus();
            });
        }

        calcTotal();
    });
})();
</script>
