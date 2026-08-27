<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Customers</h2>
        <a href="<?= Router::url('/customers/create') ?>" class="btn btn-primary">+ Add Customer</a>
    </div>

    <?php if (empty($customers)): ?>
        <p class="empty-state">No customers yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                <td class="text-muted"><?= htmlspecialchars($c['address'] ?? '') ?></td>
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
