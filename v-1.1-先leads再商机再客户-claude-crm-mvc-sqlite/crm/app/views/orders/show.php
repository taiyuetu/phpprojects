<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
// Set variables for attachment partial
$relatedType = 'order';
$relatedId = (int) $order['id'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0"><?= e($order['order_number']) ?></h3>
        <div class="text-muted"><?= e($order['title']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?= url('/orders') ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <!-- 订单信息 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">订单信息</h6>
            <p class="mb-1"><i class="bi bi-receipt me-2"></i>订单编号：<?= e($order['order_number']) ?></p>
            <p class="mb-1"><i class="bi bi-tag me-2"></i>状态：<?= statusBadge($order['status']) ?></p>
            <p class="mb-1">
                <i class="bi bi-credit-card me-2"></i>付款状态：
                <?php
                $paymentBadge = [
                    'unpaid'  => 'bg-danger',
                    'partial' => 'bg-warning text-dark',
                    'paid'    => 'bg-success',
                ];
                $paymentLabel = Order::paymentStatusLabel($order['payment_status']);
                ?>
                <span class="badge <?= $paymentBadge[$order['payment_status']] ?? 'bg-secondary' ?>">
                    <?= e($paymentLabel) ?>
                </span>
            </p>
            <p class="mb-1"><i class="bi bi-cash me-2"></i>订单金额：<strong class="text-primary"><?= money($order['amount']) ?></strong></p>
            <p class="mb-1"><i class="bi bi-calendar me-2"></i>下单日期：<?= formatDate($order['order_date'], 'Y-m-d') ?></p>
            <?php if ($order['delivery_date']): ?>
                <p class="mb-1"><i class="bi bi-truck me-2"></i>交付日期：<?= formatDate($order['delivery_date'], 'Y-m-d') ?></p>
            <?php endif; ?>
            <?php if ($order['shipping_address']): ?>
                <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>收货地址：<?= e($order['shipping_address']) ?></p>
            <?php endif; ?>
            <?= ownerBlock($order['owner_id'] ?? null) ?>
            <p class="mb-1"><i class="bi bi-clock-history me-2"></i>创建时间：<?= formatDate($order['created_at'], 'Y-m-d H:i') ?></p>
            <?php if (!empty($order['notes'])): ?>
                <hr>
                <h6 class="text-muted small text-uppercase">备注</h6>
                <p class="mb-0 small"><?= nl2br(e($order['notes'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- 关联客户 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-person me-1"></i>关联客户
            </h6>
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-primary me-3" style="width:40px;height:40px;font-size:1.2rem;">
                    <i class="bi bi-person"></i>
                </div>
                <div>
                    <a href="<?= url('/customers/' . $order['customer_id']) ?>" class="fw-semibold text-decoration-none">
                        <?= e($order['customer_name']) ?>
                    </a>
                    <?php if (!empty($order['customer_company'])): ?>
                        <div class="small text-muted"><?= e($order['customer_company']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($order['customer_email'])): ?>
                        <div class="small"><i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($order['customer_phone'])): ?>
                        <div class="small"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 关联商机 -->
        <?php if ($order['deal_id']): ?>
            <div class="card card-table p-3">
                <h6 class="text-muted small text-uppercase mb-3">
                    <i class="bi bi-lightning me-1"></i>关联商机
                </h6>
                <div class="d-flex align-items-center">
                    <div class="stat-icon bg-warning me-3" style="width:40px;height:40px;font-size:1.2rem;">
                        <i class="bi bi-lightning"></i>
                    </div>
                    <div>
                        <div class="fw-semibold"><?= e($order['deal_title'] ?? '—') ?></div>
                        <?php if (!empty($order['deal_stage'])): ?>
                            <div class="small"><?= statusBadge($order['deal_stage']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- 附件 -->
        <?php include APP_PATH . '/views/partials/_attachments.php'; ?>

        <!-- 商品明细 -->
        <div class="card card-table p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="text-muted small text-uppercase mb-0">
                    <i class="bi bi-box-seam me-1"></i>商品明细
                    <span class="badge bg-primary ms-1"><?= count($items) ?></span>
                </h6>
                <a href="<?= url('/orders/' . $order['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> 编辑明细
                </a>
            </div>

            <?php if (empty($items)): ?>
                <p class="text-muted small mb-0">暂无商品明细。</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th width="5%">#</th>
                                <th width="30%">商品名称</th>
                                <th width="12%">规格/SKU</th>
                                <th width="8%">数量</th>
                                <th width="8%">单位</th>
                                <th width="12%">单价</th>
                                <th width="12%">小计</th>
                                <th width="13%">备注</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td class="fw-semibold"><?= e($item['product_name']) ?></td>
                                    <td><small><?= e($item['sku'] ?: '—') ?></small></td>
                                    <td><?= e(rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.')) ?></td>
                                    <td><?= e($item['unit']) ?></td>
                                    <td><?= money($item['unit_price']) ?></td>
                                    <td class="fw-semibold"><?= money($item['subtotal']) ?></td>
                                    <td><small class="text-muted"><?= e($item['notes'] ?: '—') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="6" class="text-end">合计：</td>
                                <td class="text-primary"><?= money($order['amount']) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 订单时间线 -->
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-clock-history me-1"></i>订单时间线
            </h6>

            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-marker bg-primary"></div>
                    <div class="timeline-content">
                        <div class="fw-semibold">订单创建</div>
                        <div class="small text-muted"><?= formatDate($order['created_at'], 'Y-m-d H:i') ?></div>
                    </div>
                </div>

                <?php if ($order['status'] !== 'pending'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">订单确认</div>
                            <div class="small text-muted">订单状态更新为：<?= Order::statusLabel('confirmed') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($order['status'], ['processing', 'shipped', 'delivered', 'completed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">订单处理中</div>
                            <div class="small text-muted">订单状态更新为：<?= Order::statusLabel('processing') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($order['status'], ['shipped', 'delivered', 'completed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">已发货</div>
                            <div class="small text-muted">订单状态更新为：<?= Order::statusLabel('shipped') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($order['status'], ['delivered', 'completed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">已送达</div>
                            <div class="small text-muted">订单状态更新为：<?= Order::statusLabel('delivered') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($order['status'] === 'completed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">订单完成</div>
                            <div class="small text-muted">订单状态更新为：<?= Order::statusLabel('completed') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($order['payment_status'] === 'paid'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="fw-semibold">付款完成</div>
                            <div class="small text-muted">付款状态更新为：<?= Order::paymentStatusLabel('paid') ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
