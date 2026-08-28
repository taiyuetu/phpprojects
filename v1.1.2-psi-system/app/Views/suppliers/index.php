<?php
use App\Core\Router;
$filters = $filters ?? [];
$customFields = $customFields ?? [];
$exportParams = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$exportUrl = Router::url('/suppliers/export') . ($exportParams !== '' ? '?' . $exportParams : '');
$hasFilter = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Suppliers</h2>
        <div style="display:flex;gap:10px;align-items:center;">
            <a href="<?= Router::url('/suppliers/import') ?>" class="btn btn-secondary">Import CSV</a>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">Export CSV</a>
            <a href="<?= Router::url('/suppliers/create') ?>" class="btn btn-primary">+ Add Supplier</a>
        </div>
    </div>

    <form method="get" action="<?= Router::url('/suppliers') ?>" class="filter-form">
        <input type="search" name="q" placeholder="Search suppliers..." value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        <?php include __DIR__ . '/../partials/custom_fields_filters.php'; ?>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($hasFilter): ?>
            <a href="<?= Router::url('/suppliers') ?>" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($suppliers)): ?>
        <p class="empty-state"><?= $hasFilter ? 'No suppliers match your filters.' : 'No suppliers yet.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><?php include __DIR__ . '/../partials/custom_fields_headers.php'; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
            <?php $attrs = json_decode($s['attributes'] ?? '{}', true) ?: []; ?>
            <tr>
                <td><?= htmlspecialchars($s['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['address'] ?? '') ?></td>
                <?php include __DIR__ . '/../partials/custom_fields_cells.php'; ?>
                <td class="actions">
                    <a href="<?= Router::url('/suppliers/' . $s['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/suppliers/' . $s['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php include __DIR__ . '/../partials/pagination.php'; ?>
    <?php endif; ?>
</div>
