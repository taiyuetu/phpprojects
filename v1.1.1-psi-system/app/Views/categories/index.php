<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">All Categories</h2>
        <a href="<?= Router::url('/categories/create') ?>" class="btn btn-primary">+ Add Category</a>
    </div>

    <?php if (empty($categories)): ?>
        <p class="empty-state">No categories yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars(substr($c['created_at'], 0, 10)) ?></td>
                <td class="actions">
                    <a href="<?= Router::url('/categories/' . $c['id'] . '/edit') ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <?php $deleteUrl = Router::url('/categories/' . $c['id'] . '/delete'); include __DIR__ . '/../partials/delete_button.php'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
