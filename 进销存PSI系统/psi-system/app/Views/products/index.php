<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Products</h2>
        <a href="<?= Router::url('/products/create') ?>" class="btn btn-primary">+ Add Product</a>
    </div>

    <?php if (empty($products)): ?>
        <p class="empty-state">No products yet. Add your first one to get started.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th>SKU</th><th>Name</th><th>Category</th><th>Cost</th><th>Price</th><th>Qty</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($p['sku']) ?></td>
                <td><a href="<?= Router::url('/products/' . $p['id']) ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td>$<?= number_format($p['cost_price'], 2) ?></td>
                <td>$<?= number_format($p['sale_price'], 2) ?></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td>
                    <?php if ($p['quantity'] <= $p['reorder_level']): ?>
                        <span class="badge badge-red">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-green">In Stock</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a href="<?= Router::url('/products/' . $p['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/products/' . $p['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
