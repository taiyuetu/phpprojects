<?php
use App\Core\Router;
$filters = $filters ?? [];
$customFields = $customFields ?? [];
$exportParams = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$exportUrl = Router::url('/customers/export') . ($exportParams !== '' ? '?' . $exportParams : '');
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Customers</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= Router::url('/customers/import') ?>" class="btn btn-secondary">Import CSV</a>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">Export CSV</a>
            <a href="<?= Router::url('/customers/create') ?>" class="btn btn-primary">+ Add Customer</a>
        </div>
    </div>

    <form method="get" action="<?= Router::url('/customers') ?>" class="filter-form">
        <input type="search" name="q" placeholder="Search customers..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/customers') ?>" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($customers)): ?>
        <p class="empty-state"><?= $hasFilter ? 'No customers match your filters.' : 'No customers yet.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <?php $attrs = json_decode($c['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars($c['address'] ?? '') ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td class="actions">
                    <a href="<?= Router::url('/customers/' . $c['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/customers/' . $c['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
