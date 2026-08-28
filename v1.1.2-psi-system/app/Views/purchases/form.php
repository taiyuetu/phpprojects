<?php use App\Core\Router; ?>
<style>
/* Add-item area */
.add-item-area {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 20px;
}
.add-item-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.add-item-row .form-group { margin-bottom: 0; }
.autocomplete-wrap {
    position: relative;
    width: 260px;
}
.autocomplete-wrap input {
    width: 100%;
}
.autocomplete-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 260px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 6px 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
    z-index: 100;
    display: none;
}
.autocomplete-list .ac-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: .9rem;
}
.autocomplete-list .ac-item:hover,
.autocomplete-list .ac-item.active {
    background: #e8f0fe;
}
.autocomplete-list .ac-item .ac-sku {
    color: #6b7280;
    font-size: .8rem;
    margin-left: 6px;
}
.autocomplete-list .ac-empty {
    padding: 10px 12px;
    color: #9ca3af;
    font-size: .85rem;
}
.add-item-select {
    width: 260px;
}
/* Read-only product name cell in list */
.product-name-cell {
    font-weight: 500;
}
.product-name-cell .sku-label {
    color: #6b7280;
    font-size: .8rem;
}
</style>

<div class="card">
    <h2>New Purchase</h2>
    <form method="post" action="<?= Router::url('/purchases') ?>" id="purchase-form">
        <?= $this->csrfField() ?>

        <div class="form-row">
            <div class="form-group">
                <label>Invoice / PO Number</label>
                <input type="text" name="invoice_no" required value="<?= htmlspecialchars($nextInvoice) ?>">
            </div>
            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id" required>
                    <option value="">Select supplier…</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <h3>Add Item</h3>
        <div class="add-item-area">
            <div class="add-item-row">
                <div class="autocomplete-wrap">
                    <input type="text" id="product-search" placeholder="Search by name or SKU…" autocomplete="off">
                    <div class="autocomplete-list" id="ac-list"></div>
                </div>
                <div class="form-group">
                    <input type="number" id="add-qty" min="1" value="1" placeholder="Qty" style="width:80px;">
                </div>
                <div class="form-group">
                    <input type="number" id="add-cost" min="0" step="0.01" value="0" placeholder="Unit Cost" style="width:110px;">
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="add-item-btn">Add</button>
            </div>
            <select id="product-select" class="add-item-select" style="margin-top:8px;">
                <option value="">— or select from list —</option>
            </select>
        </div>

        <h3>Items</h3>
        <div class="table-wrap">
        <table class="line-items" id="items-table">
            <thead>
                <tr><th style="width:40%;">Product</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="items-body"></tbody>
        </table>
        </div>
        <p id="no-items-msg" style="color:#9ca3af;font-size:.9rem;margin-top:8px;">No items added yet. Search and add products above.</p>

        <div style="text-align:right;margin-top:16px;font-size:1.1rem;">
            <strong>Grand Total: $<span id="grand-total">0.00</span></strong>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Purchase</button>
            <a href="<?= Router::url('/purchases') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const PRODUCTS = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'cost' => (float)$p['cost_price']], $products)) ?>;

const body = document.getElementById('items-body');
const searchInput = document.getElementById('product-search');
const acList = document.getElementById('ac-list');
const productSelect = document.getElementById('product-select');
const addQty = document.getElementById('add-qty');
const addCost = document.getElementById('add-cost');
const addBtn = document.getElementById('add-item-btn');
const noItemsMsg = document.getElementById('no-items-msg');

let acIndex = -1;
let selectedProduct = null;

// ── Populate the <select> dropdown ──
PRODUCTS.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.id;
    opt.textContent = p.name + ' [' + p.sku + ']';
    opt.dataset.cost = p.cost;
    productSelect.appendChild(opt);
});

// ── Select dropdown change ──
productSelect.addEventListener('change', () => {
    const id = productSelect.value;
    if (!id) { selectedProduct = null; return; }
    const p = PRODUCTS.find(pr => pr.id == id);
    if (p) {
        selectedProduct = p;
        searchInput.value = p.name;
        addCost.value = p.cost.toFixed(2);
        addQty.focus();
        addQty.select();
    }
});

// ── Autocomplete search ──
searchInput.addEventListener('input', () => {
    selectedProduct = null;
    productSelect.value = '';
    const q = searchInput.value.trim().toLowerCase();
    if (q.length === 0) { hideAc(); return; }

    const matches = PRODUCTS.filter(p =>
        p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)
    ).slice(0, 20);

    if (matches.length === 0) {
        acList.innerHTML = '<div class="ac-empty">No products found</div>';
        acList.style.display = 'block';
        acIndex = -1;
        return;
    }

    acList.innerHTML = matches.map((p, i) =>
        `<div class="ac-item" data-idx="${i}" data-id="${p.id}">${escHtml(p.name)}<span class="ac-sku">[${escHtml(p.sku)}]</span></div>`
    ).join('');
    acList.style.display = 'block';
    acIndex = -1;

    acList.querySelectorAll('.ac-item').forEach(el => {
        el.addEventListener('mousedown', (e) => {
            e.preventDefault();
            pickProduct(PRODUCTS.find(p => p.id == el.dataset.id));
        });
    });
});

searchInput.addEventListener('keydown', (e) => {
    const items = acList.querySelectorAll('.ac-item');
    if (!items.length) {
        if (e.key === 'Enter') e.preventDefault();
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        acIndex = Math.min(acIndex + 1, items.length - 1);
        updateAcHighlight(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        acIndex = Math.max(acIndex - 1, 0);
        updateAcHighlight(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (acIndex >= 0 && items[acIndex]) {
            pickProduct(PRODUCTS.find(p => p.id == items[acIndex].dataset.id));
        } else if (items.length === 1) {
            pickProduct(PRODUCTS.find(p => p.id == items[0].dataset.id));
        }
    } else if (e.key === 'Escape') {
        hideAc();
    }
});

searchInput.addEventListener('blur', () => { setTimeout(hideAc, 150); });

function updateAcHighlight(items) {
    items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
    if (items[acIndex]) items[acIndex].scrollIntoView({ block: 'nearest' });
}

function hideAc() {
    acList.style.display = 'none';
    acIndex = -1;
}

// Shared: called from search or select
function pickProduct(product) {
    if (!product) return;
    selectedProduct = product;
    productSelect.value = product.id;
    searchInput.value = product.name;
    addCost.value = product.cost.toFixed(2);
    hideAc();
    addQty.focus();
    addQty.select();
}

// ── Add item to list ──
function addItemToList() {
    if (!selectedProduct) {
        searchInput.focus();
        return;
    }
    const qty = parseInt(addQty.value) || 1;
    const cost = parseFloat(addCost.value) || 0;

    // Check if product already in list — increment qty instead
    const existingRow = body.querySelector(`tr[data-product-id="${selectedProduct.id}"]`);
    if (existingRow) {
        const qtyInput = existingRow.querySelector('.qty-input');
        const costInput = existingRow.querySelector('.cost-input');
        qtyInput.value = parseInt(qtyInput.value) + qty;
        costInput.value = cost.toFixed(2);
        recalcRow(existingRow);
    } else {
        addRow(selectedProduct, qty, cost);
    }

    // Reset add form
    searchInput.value = '';
    selectedProduct = null;
    selectedIdInput.value = '';
    addQty.value = '1';
    addCost.value = '0';
    searchInput.focus();
    updateNoItemsMsg();
}

addBtn.addEventListener('click', addItemToList);
addQty.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addItemToList(); } });
addCost.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addItemToList(); } });

// ── List row management ──
function addRow(product, qty, cost) {
    const row = document.createElement('tr');
    row.dataset.productId = product.id;
    row.innerHTML = `
        <td class="product-name-cell">
            <input type="hidden" name="product_id[]" value="${product.id}">
            ${escHtml(product.name)} <span class="sku-label">[${escHtml(product.sku)}]</span>
        </td>
        <td><input type="number" name="qty[]" min="1" value="${qty}" class="qty-input" required></td>
        <td><input type="number" name="unit_cost[]" min="0" step="0.01" value="${cost.toFixed(2)}" class="cost-input" required></td>
        <td class="line-subtotal">$${(qty * cost).toFixed(2)}</td>
        <td><span class="remove-row" title="Remove item">&times;</span></td>
    `;
    body.appendChild(row);
    attachRowEvents(row);
    recalcTotal();
}

function attachRowEvents(row) {
    const qty = row.querySelector('.qty-input');
    const cost = row.querySelector('.cost-input');
    const remove = row.querySelector('.remove-row');
    qty.addEventListener('input', () => recalcRow(row));
    cost.addEventListener('input', () => recalcRow(row));
    remove.addEventListener('click', () => { row.remove(); recalcTotal(); updateNoItemsMsg(); });
}

function recalcRow(row) {
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    row.querySelector('.line-subtotal').textContent = '$' + (qty * cost).toFixed(2);
    recalcTotal();
}

function recalcTotal() {
    let total = 0;
    body.querySelectorAll('tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input')?.value) || 0;
        total += qty * cost;
    });
    document.getElementById('grand-total').textContent = total.toFixed(2);
}

function updateNoItemsMsg() {
    noItemsMsg.style.display = body.children.length === 0 ? '' : 'none';
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

updateNoItemsMsg();
</script>
