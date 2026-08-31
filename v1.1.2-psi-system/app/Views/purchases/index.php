<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$filters = $filters ?? [];
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">Purchase Orders</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <form method="get" action="<?= Router::url('/purchases') ?>" class="search-form" style="display:flex;gap:8px;align-items:center;">
                <input type="search" name="q" placeholder="Search purchases..." value="<?= htmlspecialchars($q ?? '') ?>">
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from ?? '') ?>" title="From date">
                <span style="color:#9ca3af;">–</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to ?? '') ?>" title="To date">
                <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <?php if ($hasFilter): ?>
                    <a href="<?= Router::url('/purchases') ?>" class="btn btn-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>
            <a href="<?= Router::url('/purchases/create') ?>" class="btn btn-primary">+ New Purchase</a>
        </div>
    </div>

    <?php if (empty($purchases)): ?>
        <p class="empty-state">No purchases recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Invoice #</th><th>Supplier</th><th>Date</th><th>Arrived Qty</th><?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_headers.php'; } ?><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <?php $purchaseAttrs = json_decode($p['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><a href="<?= Router::url('/purchases/' . $p['id']) ?>"><?= htmlspecialchars($p['invoice_no']) ?></a></td>
                <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['purchase_date']) ?></td>
                <td class="text-muted"><?= $p['total_arrived_qty'] ? (int)$p['total_arrived_qty'] . ' units' : '—' ?></td>
                <?php $attrs = $purchaseAttrs; if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_cells.php'; } ?>
                <td class="text-right">$<?= number_format($p['total'], 2) ?></td>
                <td><a href="<?= Router::url('/purchases/' . $p['id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
