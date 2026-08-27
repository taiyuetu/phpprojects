<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
        <div>
            <h2 style="margin-bottom:4px;"><?= htmlspecialchars($product['name']) ?></h2>
            <p class="text-muted" style="margin-top:0;">SKU: <?= htmlspecialchars($product['sku']) ?></p>
        </div>
        <a href="<?= Router::url('/products/' . $product['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit Product</a>
    </div>

    <div class="stat-grid" style="margin-top:16px;">
        <div class="stat-card"><div class="label">Current Stock</div><div class="value"><?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></div></div>
        <div class="stat-card"><div class="label">Cost Price</div><div class="value">$<?= number_format($product['cost_price'], 2) ?></div></div>
        <div class="stat-card"><div class="label">Sale Price</div><div class="value">$<?= number_format($product['sale_price'], 2) ?></div></div>
        <div class="stat-card"><div class="label">Reorder Level</div><div class="value"><?= $product['reorder_level'] ?></div></div>
    </div>
</div>

<div class="card">
    <h3>Stock Movement History</h3>
    <?php if (empty($transactions)): ?>
        <p class="empty-state">No stock movements recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Type</th><th>Change</th><th>Balance After</th><th>Reference</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                <td>
                    <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber'][$t['type']] ?? 'badge-gray'; ?>
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($t['type']) ?></span>
                </td>
                <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                <td><?= $t['balance_after'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['reference']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
