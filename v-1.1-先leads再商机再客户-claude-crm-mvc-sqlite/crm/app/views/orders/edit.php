<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">编辑订单</h3>
    <a href="<?= url('/orders/' . $order['id']) ?>" class="btn btn-outline-secondary btn-sm">返回详情</a>
</div>

<div class="card card-table p-4" style="max-width:1000px;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/orders/' . $order['id']) ?>" id="order-form">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <?php include __DIR__ . '/_form.php'; ?>
        <button type="submit" class="btn btn-primary mt-3">保存修改</button>
    </form>
</div>

<?php include __DIR__ . '/_items_js.php'; ?>
