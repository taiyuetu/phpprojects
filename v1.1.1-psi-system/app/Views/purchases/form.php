<?php use App\Core\Router; ?>
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

        <h3>Line Items</h3>
        <div class="table-wrap">
        <table class="line-items" id="items-table">
            <thead>
                <tr><th style="width:35%;">Product</th><th>Qty</th><th>Unit Cost</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="items-body"></tbody>
        </table>
        </div>

        <button type="button" class="btn btn-secondary btn-sm" id="add-row" style="margin-top:10px;">+ Add Line</button>

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
const PRODUCTS = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'cost' => (float)$p['cost_price']], $products)) ?>;

const body = document.getElementById('items-body');

function productOptions(selected = '') {
    let html = '<option value="">Select…</option>';
    PRODUCTS.forEach(p => {
        html += `<option value="${p.id}" data-cost="${p.cost}" ${String(p.id) === String(selected) ? 'selected' : ''}>${p.name}</option>`;
    });
    return html;
}

function addRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><select name="product_id[]" class="product-select" required>${productOptions()}</select></td>
        <td><input type="number" name="qty[]" min="1" value="1" class="qty-input" required></td>
        <td><input type="number" name="unit_cost[]" min="0" step="0.01" value="0" class="cost-input" required></td>
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
    const cost = row.querySelector('.cost-input');
    const remove = row.querySelector('.remove-row');

    select.addEventListener('change', () => {
        const opt = select.options[select.selectedIndex];
        cost.value = opt.dataset.cost || 0;
        recalcRow(row);
    });
    qty.addEventListener('input', () => recalcRow(row));
    cost.addEventListener('input', () => recalcRow(row));
    remove.addEventListener('click', () => { row.remove(); recalcTotal(); });
}

function recalcRow(row) {
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    row.querySelector('.line-subtotal').textContent = '$' + (qty * cost).toFixed(2);
    recalcTotal();
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input')?.value) || 0;
        total += qty * cost;
    });
    document.getElementById('grand-total').textContent = total.toFixed(2);
}

document.getElementById('add-row').addEventListener('click', addRow);
addRow(); // start with one line
</script>
