<?php
use App\Core\Router;
$customFields = $customFields ?? [];
$purchaseAttrs = json_decode($purchase['attributes'] ?? '{}', true) ?: [];
?>
<div class="card">
    <div style="display:flex;justify-content:space-between;">
        <div>
            <h2 style="margin-bottom:4px;">Purchase #<?= htmlspecialchars($purchase['invoice_no']) ?></h2>
            <p class="text-muted" style="margin-top:0;">Supplier: <?= htmlspecialchars($purchase['supplier_name']) ?> · Date: <?= htmlspecialchars($purchase['purchase_date']) ?></p>
        </div>
        <a href="<?= Router::url('/purchases') ?>" class="btn btn-secondary btn-sm">&larr; All Purchases</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;padding:14px;background:#f9fafb;border-radius:8px;">
        <div>
            <div style="font-size:.8rem;color:#6b7280;">Purchase Date (下单日期)</div>
            <div style="font-weight:500;"><?= htmlspecialchars($purchase['purchase_date']) ?></div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">Expected Arrival (预计到货)</div>
            <div style="font-weight:500;"><?= $purchase['expected_arrival_date'] ? htmlspecialchars($purchase['expected_arrival_date']) : '<span style="color:#9ca3af;">—</span>' ?></div>
        </div>
        <div>
            <div style="font-size:.8rem;color:#6b7280;">Total Arrived (累计到货)</div>
            <div style="font-weight:500;color:#059669;"><?= (int)$purchase['total_arrived_qty'] ?> units</div>
        </div>
        <?php if (!empty($customFields)): ?>
            <?php foreach ($customFields as $key => $def): ?>
                <?php $val = $purchaseAttrs[$key] ?? ''; if ($val === '') continue; ?>
                <div>
                    <div style="font-size:.8rem;color:#6b7280;"><?= htmlspecialchars($def['label']) ?></div>
                    <div style="font-weight:500;">
                        <?php if (($def['type'] ?? 'text') === 'upload'): ?>
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
        <?php endif; ?>
    </div>
    <?php if (!empty($purchase['notes'])): ?>
    <div style="margin-top:12px;padding:12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
        <div style="font-size:.8rem;color:#92400e;font-weight:600;margin-bottom:4px;">Notes (备注)</div>
        <div style="color:#78350f;"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Record New Arrival Form -->
    <div style="margin-top:20px;padding:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
        <h3 style="margin-top:0;margin-bottom:12px;font-size:1rem;color:#0369a1;">📦 Record Arrival (记录到货)</h3>
        <form method="post" action="<?= Router::url('/purchases/' . $purchase['id'] . '/arrival') ?>">
            <?= $this->csrfField() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">Arrival Date (到货日期)</label>
                    <input type="date" name="arrival_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">Arrival Qty (到货数量)</label>
                    <input type="number" name="qty" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label style="font-size:.85rem;color:#374151;">Notes (备注)</label>
                    <input type="text" name="notes" placeholder="Optional notes...">
                </div>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" class="btn btn-primary">Record Arrival</button>
            </div>
        </form>
    </div>

    <!-- Arrival History -->
    <?php if (!empty($purchase['arrivals'])): ?>
    <div style="margin-top:20px;">
        <h3 style="margin-bottom:12px;font-size:1rem;">📋 Arrival History (到货记录)</h3>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Arrival Date</th>
                    <th class="text-right">Qty</th>
                    <th>Notes</th>
                    <th>Recorded By</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($purchase['arrivals'] as $idx => $arrival): ?>
                <tr>
                    <td class="text-muted"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($arrival['arrival_date']) ?></td>
                    <td class="text-right" style="font-weight:500;color:#059669;">+<?= (int)$arrival['qty'] ?></td>
                    <td class="text-muted"><?= htmlspecialchars($arrival['notes'] ?? '') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($arrival['created_by_name'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.85rem;"><?= htmlspecialchars($arrival['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right"><strong>Total Arrived</strong></td>
                    <td class="text-right" style="font-weight:700;color:#059669;"><?= (int)$purchase['total_arrived_qty'] ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Purchase Items -->
    <div class="table-wrap" style="margin-top:20px;">
    <h3 style="margin-bottom:12px;font-size:1rem;">📝 Purchase Items (采购明细)</h3>
    <table>
        <thead><tr><th>SKU</th><th>Product</th><th class="text-right">Ordered Qty</th><th class="text-right">Unit Cost</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($purchase['items'] as $item): ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars($item['sku']) ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td class="text-right"><?= $item['qty'] ?></td>
                <td class="text-right">$<?= number_format($item['unit_cost'], 2) ?></td>
                <td class="text-right">$<?= number_format($item['subtotal'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4" class="text-right"><strong>Grand Total</strong></td><td class="text-right"><strong>$<?= number_format($purchase['total'], 2) ?></strong></td></tr>
        </tfoot>
    </table>
    </div>
</div>
