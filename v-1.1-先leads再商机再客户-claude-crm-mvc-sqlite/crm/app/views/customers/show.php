<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0"><?= e($customer['name']) ?></h3>
        <div class="text-muted"><?= e($customer['company'] ?: '') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/customers/' . $customer['id'] . '/edit') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> 编辑
        </a>
        <a href="<?= url('/customers') ?>" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <!-- 联系信息 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase">联系信息</h6>
            <p class="mb-1"><i class="bi bi-envelope me-2"></i><?= e($customer['email'] ?: '—') ?></p>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i><?= e($customer['phone'] ?: '—') ?></p>
            <?php if (!empty($customer['whatsapp'])): ?>
                <p class="mb-1"><i class="bi bi-whatsapp me-2"></i><?= e($customer['whatsapp']) ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['facebook'])): ?>
                <p class="mb-1"><i class="bi bi-facebook me-2"></i><a href="<?= e($customer['facebook']) ?>" target="_blank">Facebook</a></p>
            <?php endif; ?>
            <?php if (!empty($customer['tiktok'])): ?>
                <p class="mb-1"><i class="bi bi-tiktok me-2"></i><a href="<?= e($customer['tiktok']) ?>" target="_blank">TikTok</a></p>
            <?php endif; ?>
            <?php if (!empty($customer['website'])): ?>
                <p class="mb-1"><i class="bi bi-globe me-2"></i><a href="<?= e($customer['website']) ?>" target="_blank">官方网站</a></p>
            <?php endif; ?>
            <p class="mb-1"><i class="bi bi-geo-alt me-2"></i><?= e($customer['address'] ?: '—') ?></p>
            <?php if (!empty($customer['source_country']) || !empty($customer['source_city'])): ?>
                <p class="mb-1"><i class="bi bi-map me-2"></i><?= e($customer['source_country'] ?: '') ?><?= $customer['source_city'] ? ' · ' . e($customer['source_city']) : '' ?></p>
            <?php endif; ?>
            <p class="mb-1"><i class="bi bi-person-badge me-2"></i>负责人：<?= e($customer['owner_name'] ?? '—') ?></p>
            <p class="mb-1"><?= statusBadge($customer['status']) ?></p>
            <p class="mb-1"><i class="bi bi-clock-history me-2"></i>
                <?php if (!empty($customer['conversion_time'])): ?>
                    转化时间：<?= formatDate($customer['conversion_time'], 'Y-m-d H:i') ?>
                <?php else: ?>
                    创建时间：<?= formatDate($customer['created_at'], 'Y-m-d H:i') ?>
                <?php endif; ?>
            </p>
            <div class="mt-2">
                <?php if (!empty($customer['first_purchase_from_china'])): ?>
                    <span class="badge bg-info-subtle text-info me-1">第一次从中国采购</span>
                <?php endif; ?>
                <?php if (!empty($customer['has_import_capability'])): ?>
                    <span class="badge bg-success-subtle text-success me-1">有进口能力</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($customer['notes'])): ?>
                <hr>
                <h6 class="text-muted small text-uppercase">备注</h6>
                <p class="mb-0 small"><?= nl2br(e($customer['notes'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- 商机列表 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-lightning me-1"></i>商机记录
                <span class="badge bg-primary ms-1"><?= count($deals) ?></span>
            </h6>
            <?php if (!$deals): ?>
                <p class="text-muted small mb-0">该客户暂无商机。</p>
            <?php else: ?>
                <?php foreach ($deals as $d): ?>
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= e($d['title']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-cash me-1"></i><?= money($d['value']) ?>
                                    <?php if ($d['close_date']): ?>
                                        <br><i class="bi bi-calendar me-1"></i>预计成交：<?= formatDate($d['close_date'], 'Y-m-d') ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?= statusBadge($d['stage']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 订单列表 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-receipt me-1"></i>订单记录
                <span class="badge bg-success ms-1"><?= count($orders) ?></span>
            </h6>
            <?php if (!$orders): ?>
                <p class="text-muted small mb-0">该客户暂无订单。</p>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <a href="<?= url('/orders/' . $o['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($o['order_number']) ?>
                                </a>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-cash me-1"></i><?= money($o['amount']) ?>
                                    <?php if ($o['deal_title']): ?>
                                        <br><i class="bi bi-lightning me-1"></i><?= e($o['deal_title']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?= statusBadge($o['status']) ?>
                                <br>
                                <?php
                                $paymentBadge = [
                                    'unpaid'  => 'bg-danger',
                                    'partial' => 'bg-warning text-dark',
                                    'paid'    => 'bg-success',
                                ];
                                $paymentLabel = Order::paymentStatusLabel($o['payment_status']);
                                ?>
                                <span class="badge <?= $paymentBadge[$o['payment_status']] ?? 'bg-secondary' ?>">
                                    <?= e($paymentLabel) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 来源线索信息 -->
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-diagram-3 me-1"></i>来源线索
            </h6>
            <?php if (!$convertedLead): ?>
                <p class="text-muted small mb-0">该客户非线索转化创建。</p>
            <?php else: ?>
                <div class="py-2">
                    <div class="d-flex align-items-center mb-2">
                        <strong><?= e($convertedLead['title']) ?></strong>
                        <span class="badge bg-success-subtle text-success ms-2">
                            <i class="bi bi-check-circle"></i> 已转化
                        </span>
                    </div>
                    <?php if ($convertedLead['contact_name']): ?>
                        <p class="mb-1 small">
                            <i class="bi bi-person me-1"></i><?= e($convertedLead['contact_name']) ?>
                            <?php if ($convertedLead['contact_email']): ?>
                                · <i class="bi bi-envelope me-1"></i><?= e($convertedLead['contact_email']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <p class="mb-1 small">
                        <i class="bi bi-tag me-1"></i>来源：<?= e($convertedLead['source'] ?: '—') ?>
                        <?php if ($convertedLead['value'] > 0): ?>
                            · <i class="bi bi-cash me-1"></i>原始价值：<?= money($convertedLead['value']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($convertedLead['notes']): ?>
                        <p class="mb-0 small text-muted">
                            <i class="bi bi-chat-left-text me-1"></i><?= e($convertedLead['notes']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- 跟进记录 -->
        <div class="card card-table p-3 mb-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-clock-history me-1"></i>跟进记录
                <span class="badge bg-warning text-dark ms-1"><?= count($followUps) ?></span>
            </h6>

            <!-- 添加跟进记录表单 -->
            <form method="POST" action="<?= url('/customers/' . $customer['id'] . '/follow-ups') ?>" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="price_comparison">比价询价</option>
                            <option value="no_response">无回复</option>
                            <option value="follow_up">跟进中</option>
                            <option value="other">其他</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="跟进标题*" required>
                    </div>
                    <div class="col-md-4">
                        <input type="date" name="next_date" class="form-control form-control-sm" placeholder="下次跟进日期">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-8">
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="详细描述（客户比价情况、无采购意愿说明等）"></textarea>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="next_action" class="form-control form-control-sm mb-1" placeholder="下一步行动">
                        <button type="submit" class="btn btn-warning btn-sm w-100"><i class="bi bi-plus-lg"></i> 添加跟进</button>
                    </div>
                </div>
            </form>

            <?php if (!$followUps): ?>
                <p class="text-muted small">暂无跟进记录。</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="15%">类型</th>
                                <th width="20%">标题</th>
                                <th width="30%">描述</th>
                                <th width="15%">下一步行动</th>
                                <th width="10%">下次跟进</th>
                                <th width="10%">记录时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followUps as $f): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $typeLabels = [
                                            'price_comparison' => '<span class="badge bg-warning text-dark">比价询价</span>',
                                            'no_response' => '<span class="badge bg-secondary">无回复</span>',
                                            'follow_up' => '<span class="badge bg-info">跟进中</span>',
                                            'other' => '<span class="badge bg-light text-dark">其他</span>',
                                        ];
                                        echo $typeLabels[$f['type']] ?? '<span class="badge bg-light text-dark">未知</span>';
                                        ?>
                                    </td>
                                    <td><?= e($f['title']) ?></td>
                                    <td><small><?= e($f['description'] ?: '—') ?></small></td>
                                    <td><small><?= e($f['next_action'] ?: '—') ?></small></td>
                                    <td><small><?= $f['next_date'] ? formatDate($f['next_date'], 'Y-m-d') : '—' ?></small></td>
                                    <td><small><?= formatDate($f['created_at'], 'm-d H:i') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 活动记录 -->
        <div class="card card-table p-3">
            <h6 class="text-muted small text-uppercase mb-3">
                <i class="bi bi-chat-left-text me-1"></i>活动记录
            </h6>

            <form method="POST" action="<?= url('/customers/' . $customer['id'] . '/notes') ?>" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="input-group">
                    <input type="text" name="description" class="form-control" placeholder="记录电话、会议或备注…" required>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 添加</button>
                </div>
            </form>

            <?php if (!$activities): ?>
                <p class="text-muted small">暂无活动记录。</p>
            <?php endif; ?>

            <ul class="list-unstyled">
                <?php foreach ($activities as $a): ?>
                    <li class="d-flex mb-3">
                        <div class="me-3">
                            <span class="stat-icon bg-secondary" style="width:34px;height:34px;font-size:1rem;">
                                <i class="bi bi-chat-left-text"></i>
                            </span>
                        </div>
                        <div>
                            <div><?= e($a['description']) ?></div>
                            <div class="small text-muted">
                                <?= e($a['user_name'] ?? '未知') ?> · <?= formatDate($a['created_at'], 'M j, Y g:i A') ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
