<?php use App\Core\Router; ?>
<div class="card">
    <h2>New Sale</h2>
    <form method="post" action="<?= Router::url('/sales') ?>" id="sale-form">
        <?= $this->csrfField() ?>

        <div class="form-row">
            <div class="form-group">
                <label>Invoice Number</label>
                <input type="text" name="invoice_no" required value="<?= htmlspecialchars($nextInvoice) ?>">
            </div>
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id">
                    <option value="">Walk-in Customer</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Sale Date</label>
                <input type="date" name="sale_date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <h3>Line Items</h3>
        <div class="table-wrap">
        <table class="line-items" id="items-table">
            <thead>
                <tr><th style="width:35%;">Product</th><th>Available</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="items-body"></tbody>
        </table>
        </div>

        <button type="button" class="btn btn-secondary btn-sm" id="add-row" style="margin-top:10px;">+ Add Line</button>

        <div style="text-align:right;margin-top:16px;font-size:1.1rem;">
            <strong>Grand Total: $<span id="grand-total">0.00</span></strong>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Sale</button>
            <a href="<?= Router::url('/sales') ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id' => $p['id'], 'name' => $p['name'], 'price' => (float)$p['sale_price'], 'stock' => (int)$p['quantity']
], $products)) ?>;

const body = document.getElementById('items-body');

function productOptions(selected = '') {
    let html = '<option value="">Select…</option>';
    PRODUCTS.forEach(p => {
        html += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}" ${String(p.id) === String(selected) ? 'selected' : ''}>${p.name}</option>`;
    });
    return html;
}

function addRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="product_id[]" class="product-select" required>${productOptions()}</select></td>
        <td class="stock-cell text-muted">—</td>
        <td><input type="number" name="qty[]" min="1" value="1" class="qty-input" required></td>
        <td><input type="number" name="unit_price[]" min="0" step="0.01" value="0" class="price-input" required></td>
        <td class="line-subtotal">$0.00</td>
        <td><span class="remove-row" title="Remove line">&times;</span></td>
    `;
    body.appendChild(row);
    attachRowEvents(row);
    recalcTotal();
}

function attachRowEvents(row) {
    const select = row.querySelector('.product-select');
    const qty = row.querySelector('.qty-input');
    const price = row.querySelector('.price-input');
    const remove = row.querySelector('.remove-row');
    const stockCell = row.querySelector('.stock-cell');

    select.addEventListener('change', () => {
        const opt = select.options[select.selectedIndex];
        price.value = opt.dataset.price || 0;
        const stock = opt.dataset.stock || 0;
        stockCell.textContent = stock;
        qty.setAttribute('max', stock);
        recalcRow(row);
    });
    qty.addEventListener('input', () => recalcRow(row));
    price.addEventListener('input', () => recalcRow(row));
    remove.addEventListener('click', () => { row.remove(); recalcTotal(); });
}

function recalcRow(row) {
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value) || 0;
    row.querySelector('.line-subtotal').textContent = '$' + (qty * price).toFixed(2);

    // gentle client-side warning if over available stock (server still enforces it)
    const select = row.querySelector('.product-select');
    const stock = parseFloat(select.options[select.selectedIndex]?.dataset.stock) || 0;
    row.querySelector('.qty-input').style.borderColor = (qty > stock && stock > 0) ? '#dc2626' : '';

    recalcTotal();
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
        total += qty * price;
    });
    document.getElementById('grand-total').textContent = total.toFixed(2);
}

document.getElementById('add-row').addEventListener('click', addRow);
addRow(); // start with one line
</script>
