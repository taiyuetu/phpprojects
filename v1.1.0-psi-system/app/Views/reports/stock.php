<?php use App\Core\Router; ?>
<div class="card">
    <h2>Stock Valuation Report</h2>
    <p class="text-muted">Current on-hand quantity valued at cost price, per product.</p>

    <?php if (empty($products)): ?>
        <p class="empty-state">No products yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th class="text-right">Qty</th><th class="text-right">Cost Price</th><th class="text-right">Value</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td class="text-right"><?= $p['quantity'] ?></td>
                <td class="text-right">$<?= number_format($p['cost_price'], 2) ?></td>
                <td class="text-right">$<?= number_format($p['quantity'] * $p['cost_price'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="text-right"><strong>Total Stock Value</strong></td><td class="text-right"><strong>$<?= number_format($totalValue, 2) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
