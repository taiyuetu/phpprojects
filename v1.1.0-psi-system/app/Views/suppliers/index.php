<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Suppliers</h2>
        <a href="<?= Router::url('/suppliers/create') ?>" class="btn btn-primary">+ Add Supplier</a>
    </div>

    <?php if (empty($suppliers)): ?>
        <p class="empty-state">No suppliers yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= htmlspecialchars($s['phone']) ?></td>
                <td><?= htmlspecialchars($s['email']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['address']) ?></td>
                <td class="actions">
                    <a href="<?= Router::url('/suppliers/' . $s['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/suppliers/' . $s['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
