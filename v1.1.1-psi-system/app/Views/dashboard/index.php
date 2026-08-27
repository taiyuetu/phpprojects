<?php use App\Core\Router; ?>

<div class="stat-grid">
    <div class="stat-card accent-blue">
        <div class="label">Total Products</div>
        <div class="value"><?= $totalProducts ?></div>
    </div>
    <div class="stat-card accent-green">
        <div class="label">Sales Revenue</div>
        <div class="value">$<?= number_format($salesTotal, 2) ?></div>
    </div>
    <div class="stat-card accent-amber">
        <div class="label">Purchase Spend</div>
        <div class="value">$<?= number_format($purchaseTotal, 2) ?></div>
    </div>
    <div class="stat-card accent-red">
        <div class="label">Low Stock Items</div>
        <div class="value"><?= count($lowStock) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Stock Value (cost)</div>
        <div class="value">$<?= number_format($stockValue, 2) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Orders (Purchases / Sales)</div>
        <div class="value"><?= $totalPurchases ?> / <?= $totalSales ?></div>
    </div>
</div>

<div class="form-row">
    <div class="card">
        <h2>⚠️ Low Stock Alerts</h2>
        <?php if (empty($lowStock)): ?>
            <p class="empty-state">All products are above reorder level. 🎉</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Qty</th><th>Reorder Level</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($lowStock, 0, 8) as $p): ?>
                    <tr>
                        <td><a href="<?= Router::url('/products/' . $p['id']) ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                        <td><span class="badge badge-red"><?= $p['quantity'] ?></span></td>
                        <td><?= $p['reorder_level'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><a href="<?= Router::url('/inventory/low-stock') ?>">View all &rarr;</a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>📒 Recent Stock Movements</h2>
        <?php if (empty($recentTransactions)): ?>
            <p class="empty-state">No stock movements yet.</p>
        <?php else: ?>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Type</th><th>Change</th><th>Balance</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($recentTransactions, 0, 8) as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['product_name']) ?></td>
                        <td>
                            <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber'][$t['type']] ?? 'badge-gray'; ?>
                            <span class="badge <?= $badge ?>"><?= htmlspecialchars($t['type']) ?></span>
                        </td>
                        <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                        <td><?= $t['balance_after'] ?></td>
                        <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p><a href="<?= Router::url('/inventory') ?>">View full ledger &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>
