<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">Purchase Orders</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <form method="get" action="<?= Router::url('/purchases') ?>" class="search-form">
                <input type="search" name="q" placeholder="Search purchases..." value="<?= htmlspecialchars($q ?? '') ?>">
            </form>
            <a href="<?= Router::url('/purchases/create') ?>" class="btn btn-primary">+ New Purchase</a>
        </div>
    </div>

    <?php if (empty($purchases)): ?>
        <p class="empty-state">No purchases recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Invoice #</th><th>Supplier</th><th>Date</th><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td><a href="<?= Router::url('/purchases/' . $p['id']) ?>"><?= htmlspecialchars($p['invoice_no']) ?></a></td>
                <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['purchase_date']) ?></td>
                <td class="text-right">$<?= number_format($p['total'], 2) ?></td>
                <td><a href="<?= Router::url('/purchases/' . $p['id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
