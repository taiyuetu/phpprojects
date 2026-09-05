<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">客户</h3>
    <a href="<?= url('/customers/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> 新建客户</a>
</div>

<div class="card card-table">
    <div class="card-header bg-white">
        <form method="GET" action="<?= url('/customers') ?>" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:280px"
                   placeholder="搜索姓名、公司、邮箱、手机号码、微信号码、WhatsApp号码、国家、备注…" value="<?= e($search) ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit">搜索</button>
            <?php if ($search): ?>
                <a href="<?= url('/customers') ?>" class="btn btn-sm btn-link">清除</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr class="text-muted small">
                    <th>姓名</th>
                    <th>国家</th>
                    <th>公司</th>
                    <th>电话</th>
                    <th>备注</th>
                    <th>状态</th>
                    <th>负责人</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$customers): ?>
                <tr><td colspan="8" class="text-center text-muted p-4">未找到客户。</td></tr>
            <?php endif; ?>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><a href="<?= url('/customers/' . $c['id']) ?>" class="fw-semibold text-decoration-none"><?= e($c['name']) ?></a>
                        <div class="small text-muted" title="稳定编号：对 AI 说这个，报表里找这个"><?= e((new Customer())->codeOf($c)) ?></div></td>
                    <td><?= e($c['source_country'] ?: '—') ?></td>
                    <td><?= e($c['company'] ?: '—') ?></td>
                    <td><?= e($c['phone'] ?: '—') ?></td>
                    <td title="<?= e($c['notes'] ?? '') ?>" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['notes'] ?: '—') ?></td>
                    <td><?= statusBadge($c['status']) ?></td>
                    <td><?= e($c['owner_name'] ?? '—') ?></td>
                    <td class="text-end">
                        <a href="<?= url('/customers/' . $c['id']) ?>" class="btn btn-sm btn-outline-primary" title="查看">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= url('/customers/' . $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary" title="编辑">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= url('/customers/' . $c['id']) ?>" class="d-inline"
                              onsubmit="return confirm('确定删除此客户？相关商机也将一并删除。');">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="删除"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$baseUrl = url('/customers?page=') . ($search ? '&q=' . urlencode($search) : '');
// Clean up: if no search, just /customers?page=
if (!$search) $baseUrl = url('/customers?page=');
include APP_PATH . '/views/partials/_pagination.php';
?>
