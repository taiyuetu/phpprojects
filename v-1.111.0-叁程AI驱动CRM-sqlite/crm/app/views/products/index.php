<?php
/** @var array $products @var string $q @var string $status @var string $category
 * @var array $categories @var int $page @var int $totalPages @var int $total @var int $unlinked
 * @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-box-seam me-2"></i>商品库</h3>
    <a href="<?= url('/products/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新增商品</a>
</div>

<?php if ($unlinked > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
        <div class="small">
            <strong><?= (int) $unlinked ?></strong> 条订单明细还没关联商品
            （升级前手挨名字留下的历史数据）。收编后它们就能在商品库里被搜索、统计和复用。
        </div>
        <form method="POST" action="<?= url('/products/import-items') ?>" class="mb-0">
            <input type="hidden" name="csrf_token" value="<?= e($csrf ?? '') ?>">
            <button class="btn btn-sm btn-warning">收编为商品</button>
        </form>
    </div>
<?php endif; ?>

<div class="card card-table">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" action="<?= url('/products') ?>" class="d-flex gap-2 flex-wrap mb-0">
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm"
                   style="max-width:240px" placeholder="搜索名称 / SKU / 规格 / 品牌">
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <?php $statuses = ['' => '全部状态'] + Product::statusOptions(); ?>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category" class="form-select form-select-sm" style="width:auto">
                <option value="">全部分类</option>
                <?php foreach ($categories as $name => $n): ?>
                    <option value="<?= e($name) ?>" <?= $category === $name ? 'selected' : '' ?>>
                        <?= e($name) ?>（<?= (int) $n ?>）
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-secondary">筛选</button>
            <?php if ($q !== '' || $status !== '' || $category !== ''): ?>
                <a href="<?= url('/products') ?>" class="btn btn-sm btn-outline-secondary">清除</a>
            <?php endif; ?>
        </form>
        <span class="small text-muted ms-auto">共 <?= (int) $total ?> 个商品</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr class="text-muted small">
                    <th>编号</th>
                    <th>商品</th>
                    <th>分类 / 品牌</th>
                    <th class="text-end">单价</th>
                    <th>单位</th>
                    <th class="text-end">被用 / 售出额</th>
                    <th>状态</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$products): ?>
                <tr><td colspan="8" class="text-center text-muted p-4">
                    <?= $q !== '' || $status !== '' || $category !== '' ? '没有符合筛选条件的商品。' : '商品库还是空的，先「新增商品」，之后商机与订单的明细就从这里选。' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($products as $p): ?>
                <tr class="<?= $p['status'] !== 'active' ? 'table-light' : '' ?>">
                    <td><span class="badge bg-light text-dark border"><?= e((string) ($p['public_code'] ?: 'PROD-' . sprintf('%06d', (int) $p['id']))) ?></span></td>
                    <td>
                        <a href="<?= url('/products/' . (int) $p['id']) ?>" class="fw-semibold text-decoration-none"><?= e((string) $p['name']) ?></a>
                        <?php if (($p['sku'] ?? '') !== ''): ?>
                            <div class="small text-muted">SKU：<?= e((string) $p['sku']) ?></div>
                        <?php endif; ?>
                        <?php if (($p['spec'] ?? '') !== ''): ?>
                            <div class="small text-muted"><?= e((string) $p['spec']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?= e((string) ($p['category'] ?: '—')) ?>
                        <?php if (($p['brand'] ?? '') !== ''): ?><span class="text-muted">/<?= e((string) $p['brand']) ?></span><?php endif; ?>
                    </td>
                    <td class="text-end"><?= money((float) $p['price']) ?></td>
                    <td><?= e((string) $p['unit']) ?></td>
                    <td class="text-end small">
                        <?= (int) ($p['used_count'] ?? 0) ?> 次
                        <?php if ((float) ($p['sold_amount'] ?? 0) > 0): ?>
                            <div class="text-muted"><?= money((float) $p['sold_amount']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $p['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                            <?= e(Product::statusLabel((string) $p['status'])) ?>
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('/products/' . (int) $p['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">编辑</a>
                        <form method="POST" action="<?= url('/products/' . (int) $p['id'] . '/delete') ?>" class="d-inline"
                              onsubmit="return confirm('删除商品「<?= e((string) $p['name']) ?>」？已被订单明细引用的商品只会改成「停用」，历史订单不受影响。');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf ?? '') ?>">
                            <button class="btn btn-sm btn-outline-danger">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$qs = http_build_query(array_filter(['q' => $q, 'status' => $status, 'category' => $category], static fn($v) => $v !== ''));
$baseUrl = url('/products?page=') . ($qs ? '&' . $qs : '');
include APP_PATH . '/views/partials/_pagination.php';
?>
