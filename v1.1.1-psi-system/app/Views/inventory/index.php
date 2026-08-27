<?php use App\Core\Router; ?>
<div class="card">
    <h2>Full Stock Ledger</h2>
    <p class="text-muted">Every stock-changing event across all products — purchases, sales, and manual adjustments — most recent first.</p>

    <?php if (empty($transactions)): ?>
        <p class="empty-state">No stock movements recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>SKU</th><th>Product</th><th>Type</th><th>Change</th><th>Balance After</th><th>Reference</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['sku']) ?></td>
                <td><a href="<?= Router::url('/products/' . $t['product_id']) ?>"><?= htmlspecialchars($t['product_name']) ?></a></td>
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
