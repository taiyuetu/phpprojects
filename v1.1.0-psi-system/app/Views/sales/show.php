<?php use App\Core\Router; ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <h2 style="margin-bottom:4px;">Sale #<?= htmlspecialchars($sale['invoice_no']) ?></h2>
            <p class="text-muted" style="margin-top:0;">Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?> · Date: <?= htmlspecialchars($sale['sale_date']) ?></p>
        </div>
        <a href="<?= Router::url('/sales') ?>" class="btn btn-secondary btn-sm">&larr; All Sales</a>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
    <table>
        <thead><tr><th>SKU</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($sale['items'] as $item): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($item['sku']) ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-right"><?= $item['qty'] ?></td>
                <td class="text-right">$<?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-right">$<?= number_format($item['subtotal'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4" class="text-right"><strong>Grand Total</strong></td><td class="text-right"><strong>$<?= number_format($sale['total'], 2) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
</div>
