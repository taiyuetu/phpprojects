<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="margin:0;">Sales Invoices</h2>
        <a href="<?= Router::url('/sales/create') ?>" class="btn btn-primary">+ New Sale</a>
    </div>

    <?php if (empty($sales)): ?>
        <p class="empty-state">No sales recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
            <tr>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>"><?= htmlspecialchars($s['invoice_no']) ?></a></td>
                <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                <td class="text-muted"><?= htmlspecialchars($s['sale_date']) ?></td>
                <td class="text-right">$<?= number_format($s['total'], 2) ?></td>
                <td><a href="<?= Router::url('/sales/' . $s['id']) ?>" class="btn btn-secondary btn-sm">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
