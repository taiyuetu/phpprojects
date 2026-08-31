<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$filters = $filters ?? [];
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">Full Stock Ledger</h2>
            <p class="text-muted" style="margin:4px 0 0;">Every stock-changing event across all products — purchases, sales, and manual adjustments — most recent first.</p>
        </div>
        <form method="get" action="<?= Router::url('/inventory') ?>" class="search-form" style="display:flex;gap:8px;align-items:center;">
            <input type="search" name="q" placeholder="Search ledger..." value="<?= htmlspecialchars($q ?? '') ?>">
            <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (($q ?? '') !== ''): ?>
                <a href="<?= Router::url('/inventory') ?>" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($transactions)): ?>
        <p class="empty-state">No stock movements recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>SKU</th><th>Product</th><th>Type</th><th>Change</th><th>Balance After</th><th>Reference</th><?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_headers.php'; } ?></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
            <?php $attrs = json_decode($t['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars(substr($t['created_at'], 0, 16)) ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['sku']) ?></td>
                <td><a href="<?= Router::url('/products/' . $t['product_id']) ?>"><?= htmlspecialchars($t['product_name']) ?></a></td>
                <td>
                    <?php $badge = ['purchase' => 'badge-blue', 'sale' => 'badge-green', 'adjustment' => 'badge-amber', 'purchase_arrival' => 'badge-blue'][$t['type']] ?? 'badge-gray'; ?>
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars($t['type']) ?></span>
                </td>
                <td><?= $t['qty_change'] > 0 ? '+' . $t['qty_change'] : $t['qty_change'] ?></td>
                <td><?= $t['balance_after'] ?></td>
                <td class="text-muted"><?= htmlspecialchars($t['reference']) ?></td>
                <?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_cells.php'; } ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
