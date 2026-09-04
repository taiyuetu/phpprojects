<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('items-container');
    const totalEl = document.getElementById('items-total');
    const amountDisplay = document.getElementById('order-amount-display');
    const amountHidden = document.getElementById('order-amount-hidden');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = container.querySelectorAll('.item-row').length;

    const unitOptions = <?= json_encode(array_map(fn($u) => '<option value="' . e($u) . '">' . e($u) . '</option>', OrderItem::unitOptions())) ?>;

    // Calculate subtotal for a row
    function calcRow(row) {
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        const subtotal = (qty * price).toFixed(2);
        const subEl = row.querySelector('.item-subtotal');
        if (subEl) subEl.value = subtotal;
        return parseFloat(subtotal);
    }

    // Calculate total
    function calcTotal() {
        let total = 0;
        container.querySelectorAll('.item-row').forEach(row => {
            total += calcRow(row);
        });
        totalEl.textContent = '$' + total.toFixed(2);
        if (amountDisplay) amountDisplay.value = '$' + total.toFixed(2);
        if (amountHidden) amountHidden.value = total.toFixed(2);
    }

    // Event delegation for quantity/price changes and remove buttons
    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            calcTotal();
        }
    });

    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-item');
        if (btn) {
            const row = btn.closest('.item-row');
            if (container.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calcTotal();
            } else {
                // Clear the last row instead of removing
                row.querySelectorAll('input').forEach(input => {
                    if (!input.readOnly && input.type !== 'hidden') input.value = '';
                });
                row.querySelector('.item-qty').value = '1';
                row.querySelector('.item-price').value = '0';
                const select = row.querySelector('select');
                if (select) select.selectedIndex = 0;
                calcTotal();
            }
        }
    });

    // Add new item row
    addBtn.addEventListener('click', function() {
        const template = `
            <div class="item-row card card-table p-3 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">商品名称 *</label>
                        <input type="text" name="items[${itemIndex}][product_name]" class="form-control form-control-sm" placeholder="如：CRM企业版授权">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">规格/SKU</label>
                        <input type="text" name="items[${itemIndex}][sku]" class="form-control form-control-sm" placeholder="SKU-001">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">数量</label>
                        <input type="number" step="0.01" min="0" name="items[${itemIndex}][quantity]" class="form-control form-control-sm item-qty" value="1">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">单位</label>
                        <select name="items[${itemIndex}][unit]" class="form-select form-select-sm">
                            ${unitOptions.join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">单价</label>
                        <input type="number" step="0.01" min="0" name="items[${itemIndex}][unit_price]" class="form-control form-control-sm item-price" value="0">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">小计</label>
                        <input type="text" class="form-control form-control-sm item-subtotal" readonly value="0.00">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="删除">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <input type="text" name="items[${itemIndex}][notes]" class="form-control form-control-sm" placeholder="备注（可选）">
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', template);
        itemIndex++;
    });

    // Initial calculation
    calcTotal();
});
</script>
