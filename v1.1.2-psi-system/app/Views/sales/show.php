<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$saleAttrs = json_decode($sale['attributes'] ?? '{}', true) ?: [];
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <h2 style="margin-bottom:4px;">Sale #<?= htmlspecialchars($sale['invoice_no']) ?></h2>
            <p class="text-muted" style="margin-top:0;">Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?> · Date: <?= htmlspecialchars($sale['sale_date']) ?></p>
        </div>
        <a href="<?= Router::url('/sales') ?>" class="btn btn-secondary btn-sm">&larr; All Sales</a>
    </div>

    <?php if (!empty($customFields)): ?>
    <div style="margin-top:16px;padding:14px;background:#f9fafb;border-radius:8px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <?php foreach ($customFields as $key => $def): ?>
                <div>
                    <div style="font-size:.8rem;color:#6b7280;"><?= htmlspecialchars($def['label']) ?></div>
                    <div style="font-weight:500;">
                        <?php $val = $saleAttrs[$key] ?? ''; ?>
                        <?php if ($val === ''): ?>
                            <span style="color:#9ca3af;">—</span>
                        <?php elseif (($def['type'] ?? 'text') === 'upload'): ?>
                            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $val)): ?>
                                <img src="<?= Router::url('/' . $val) ?>" alt="" style="max-width:80px;max-height:50px;border-radius:3px;">
                            <?php else: ?>
                                <a href="<?= Router::url('/' . $val) ?>" target="_blank">📎 <?= htmlspecialchars(basename($val)) ?></a>
                            <?php endif; ?>
                        <?php elseif (($def['type'] ?? 'text') === 'textarea'): ?>
                            <?= nl2br(htmlspecialchars($val)) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($val) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
