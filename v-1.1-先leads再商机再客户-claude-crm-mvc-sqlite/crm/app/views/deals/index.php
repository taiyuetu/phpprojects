<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">商机</h3>
    <div class="d-flex gap-2">
        <a href="<?= url('/deals/archived') ?>" class="btn btn-outline-secondary"><i class="bi bi-archive"></i> 已归档</a>
        <a href="<?= url('/deals/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新建商机</a>
    </div>
</div>

<?php
// 看板列：丢单(closed_lost)商机会自动归档，不占用看板列。
$columns = [
    'open'         => '进行中',
    'proposal'     => '方案阶段',
    'negotiation'  => '谈判中',
    'closed_won'   => '成交',
];
?>

<div class="row g-3 flex-nowrap overflow-auto pb-2">
    <?php foreach ($columns as $key => $label): ?>
        <div class="col" style="min-width:250px;">
            <div class="kanban-col">
                <h6 class="mb-3"><?= e($label) ?> <span class="text-muted">(<?= count($stages[$key]) ?>)</span></h6>

                <?php if (!$stages[$key]): ?>
                    <p class="text-muted small">暂无商机。</p>
                <?php endif; ?>

                <?php foreach ($stages[$key] as $d): ?>
                    <div class="deal-card">
                        <div class="fw-semibold"><?= e($d['title']) ?></div>
                        <div class="small text-muted mb-2"><?= e($d['customer_name'] ?? '—') ?></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold"><?= money($d['value']) ?></span>
                            <div>
                                <?php if ($d['stage'] === 'closed_won'): ?>
                                    <form method="POST" action="<?= url('/deals/' . $d['id'] . '/create-order') ?>" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success py-0 px-1" title="创建订单">
                                            <i class="bi bi-receipt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= url('/deals/' . $d['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary py-0 px-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="<?= url('/deals/' . $d['id']) ?>" class="d-inline"
                                      onsubmit="return confirm('确定删除此商机？');">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <?php if (!empty($d['close_date'])): ?>
                            <div class="small text-muted mt-1">预计成交：<?= formatDate($d['close_date']) ?></div>
                        <?php endif; ?>
                        <?php
                        $stageTimeCol = 'stage_' . $d['stage'] . '_at';
                        if (!empty($d[$stageTimeCol])): ?>
                            <div class="small text-muted"><i class="bi bi-clock me-1"></i><?= formatDate($d[$stageTimeCol], 'm-d H:i') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
