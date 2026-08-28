<?php use App\Core\Router; ?>
<div class="card">
    <h2>Low Stock Alerts</h2>
    <p class="text-muted">Products at or below their configured reorder level.</p>

    <?php if (empty($products)): ?>
        <p class="empty-state">All products are sufficiently stocked. 🎉</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Qty</th><th>Reorder Level</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td><span class="badge badge-red"><?= $p['quantity'] ?></span></td>
                <td><?= $p['reorder_level'] ?></td>
                <td><a href="<?= Router::url('/purchases/create') ?>" class="btn btn-primary btn-sm">Reorder</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
