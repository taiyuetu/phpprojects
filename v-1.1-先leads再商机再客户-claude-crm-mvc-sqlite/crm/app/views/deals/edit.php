<?php
// Set variables for attachment partial
$relatedType = 'deal';
$relatedId = (int) $deal['id'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">编辑商机</h3>
    <a href="<?= url('/deals') ?>" class="btn btn-outline-secondary btn-sm">返回看板</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-table p-4">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2">
                    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/deals/' . $deal['id']) ?>" id="deal-form">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <?php include __DIR__ . '/_form.php'; ?>
                <button type="submit" class="btn btn-primary mt-3">保存修改</button>
            </form>
        </div>

        <!-- 附件 -->
        <?php include APP_PATH . '/views/partials/_attachments.php'; ?>
    </div>

    <div class="col-lg-4">
        <!-- 关联订单 -->
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-receipt me-1"></i>关联订单
                <span class="badge bg-success ms-1"><?= count($orders) ?></span>
            </h6>
            <?php if (!$orders): ?>
                <p class="text-muted small mb-0">该商机暂无订单。</p>
                <?php if ($deal['stage'] === 'closed_won'): ?>
                    <form method="POST" action="<?= url('/deals/' . $deal['id'] . '/create-order') ?>" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-plus-lg"></i> 创建订单
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <a href="<?= url('/orders/' . $o['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($o['order_number']) ?>
                                </a>
                                <br>
                                <small class="text-muted"><?= money($o['amount']) ?></small>
                            </div>
                            <?= statusBadge($o['status']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stageSelect = document.getElementById('deal-stage-select');
    const itemsSection = document.getElementById('items-section');
    const container = document.getElementById('items-container');
    const totalEl = document.getElementById('items-total');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = container.querySelectorAll('.item-row').length;

    // Show/hide items section based on stage
    function toggleItemsSection() {
        if (stageSelect.value === 'closed_won') {
            itemsSection.style.display = '';
        } else {
            itemsSection.style.display = 'none';
        }
    }
    stageSelect.addEventListener('change', toggleItemsSection);

    // Calculate subtotal for a row
    function calcRow(row) {
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        const subtotal = (qty * price).toFixed(2);
        const subEl = row.querySelector('.item-subtotal');
        if (subEl) subEl.value = subtotal;
        return parseFloat(subtotal);
    }

    // Calculate total
    function calcTotal() {
        let total = 0;
        container.querySelectorAll('.item-row').forEach(row => {
            total += calcRow(row);
        });
        totalEl.textContent = '$' + total.toFixed(2);
    }

    // Event delegation for quantity/price changes and remove buttons
    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            calcTotal();
        }
    });

    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-remove-item');
        if (btn) {
            const row = btn.closest('.item-row');
            if (container.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calcTotal();
            } else {
                // Clear the last row instead of removing
                row.querySelectorAll('input').forEach(input => {
                    if (!input.readOnly) input.value = input.tagName === 'SELECT' ? input.options[0].value : '';
                });
                row.querySelector('.item-qty').value = '1';
                row.querySelector('.item-price').value = '0';
                calcTotal();
            }
        }
    });

    // Add new item row
    addBtn.addEventListener('click', function() {
        const template = `
            <div class="item-row card card-table p-3 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">商品名称 *</label>
                        <input type="text" name="items[${itemIndex}][product_name]" class="form-control form-control-sm" placeholder="如：CRM企业版授权">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">规格/SKU</label>
                        <input type="text" name="items[${itemIndex}][sku]" class="form-control form-control-sm" placeholder="SKU-001">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">数量</label>
                        <input type="number" step="0.01" min="0" name="items[${itemIndex}][quantity]" class="form-control form-control-sm item-qty" value="1">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">单位</label>
                        <select name="items[${itemIndex}][unit]" class="form-select form-select-sm">
                            ${<?= json_encode(array_map(fn($u) => '<option value="' . e($u) . '">' . e($u) . '</option>', OrderItem::unitOptions())) ?>.join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">单价</label>
                        <input type="number" step="0.01" min="0" name="items[${itemIndex}][unit_price]" class="form-control form-control-sm item-price" value="0">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">小计</label>
                        <input type="text" class="form-control form-control-sm item-subtotal" readonly value="0.00">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" title="删除">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <input type="text" name="items[${itemIndex}][notes]" class="form-control form-control-sm" placeholder="备注（可选）">
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', template);
        itemIndex++;
    });

    // Initial calculation
    toggleItemsSection();
    calcTotal();
});
</script>
