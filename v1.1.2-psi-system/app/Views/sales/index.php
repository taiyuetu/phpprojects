<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$filters = $filters ?? [];
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">Sales Invoices</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <form method="get" action="<?= Router::url('/sales') ?>" class="search-form" style="display:flex;gap:8px;align-items:center;">
                <input type="search" name="q" placeholder="Search sales..." value="<?= htmlspecialchars($q ?? '') ?>">
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from ?? '') ?>" title="From date">
                <span style="color:#9ca3af;">–</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to ?? '') ?>" title="To date">
                <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                <?php if ($hasFilter): ?>
                    <a href="<?= Router::url('/sales') ?>" class="btn btn-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>
            <a href="<?= Router::url('/sales/create') ?>" class="btn btn-primary">+ New Sale</a>
        </div>
    </div>

    <?php if (empty($sales)): ?>
        <p class="empty-state">No sales recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><?php if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_headers.php'; } ?><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <?php $saleAttrs = json_decode($s['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>"><?= htmlspecialchars($s['invoice_no']) ?></a></td>
                <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['sale_date']) ?></td>
                <?php $attrs = $saleAttrs; if (!empty($customFields)) { include __DIR__ . '/../partials/custom_fields_cells.php'; } ?>
                <td class="text-right">$<?= number_format($s['total'], 2) ?></td>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
