<?php
/** @var array $rows @var array $config @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$badge = [
    'pending'   => ['待确认', 'warning'],
    'executed'  => ['已执行', 'success'],
    'cancelled' => ['已取消', 'secondary'],
    'failed'    => ['失败', 'danger'],
    'invalid'   => ['校验未通过', 'danger'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">AI 操作记录</h3>
    <a href="<?= url('/ai') ?>" class="btn btn-primary"><i class="bi bi-magic"></i> 回到助手</a>
</div>

<div class="alert alert-light border small">
    每一次 AI 请求（含失败的）都会留档：谁发起、原始指令、AI 计划了什么、校验结果、最终执行了什么。
    当前配置：<?= e($config['label'] ?? '—') ?> · <?= e($config['model'] ?: '默认模型') ?> ·
    <?= !empty($config['auto_apply']) ? '自动执行' : '预览确认' ?>。
    <?php if (!isAdmin()): ?>
        <span class="text-muted">（仅显示你自己的记录）</span>
    <?php endif; ?>
</div>

<div class="card card-table">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr class="text-muted small">
                <th style="width:150px">时间</th>
                <?php if (isAdmin()): ?><th style="width:110px">发起人</th><?php endif; ?>
                <th>指令 / 计划</th>
                <th style="width:90px">操作数</th>
                <th style="width:110px">状态</th>
                <th style="width:200px">服务商 / 模型 / 耗时</th>
                <th class="text-end" style="width:80px">详情</th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted p-4">还没有 AI 请求记录。</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $plan = Ai::planOf($row);
                $count = count($plan['actions']);
                $results = Ai::resultsOf($row);
                [$label, $color] = $badge[$row['status']] ?? [$row['status'], 'secondary'];
                ?>
                <tr>
                    <td class="small text-muted"><?= e($row['created_at']) ?></td>
                    <?php if (isAdmin()): ?><td class="small"><?= e($row['user_name'] ?? '—') ?></td><?php endif; ?>
                    <td>
                        <div class="small"><?= e(textClip((string) $row['instruction'], 90)) ?></div>
                        <?php if ($count): ?>
                            <div class="small text-muted">
                                <?= e(implode('、', array_slice(array_map(
                                    static fn($a) => Ai::toolLabel((string) ($a['tool'] ?? '')),
                                    $plan['actions']
                                ), 0, 4))) ?><?= $count > 4 ? ' …' : '' ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($row['error'])): ?>
                            <div class="small text-danger"><?= e(textClip((string) $row['error'], 120)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $count ?><?php if ($results): ?> <span class="text-muted small">/ 执行 <?= count($results) ?></span><?php endif; ?></td>
                    <td><span class="badge text-bg-<?= e($color) ?>"><?= e($label) ?></span></td>
                    <td class="small text-muted"><?= e($row['provider'] ?: '—') ?> · <?= e($row['model'] ?: '—') ?> · <?= (int) $row['latency_ms'] ?>ms</td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('/ai') ?>?plan=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="查看计划详情">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php // 人删与 AI 删同一条规则：只能删自己发起的，admin 可删任意 ?>
                        <form method="POST" action="<?= url('/ai/history/' . (int) $row['id'] . '/delete') ?>"
                              class="d-inline"
                              onsubmit="return confirm('删除这条 AI 请求记录？删了就没法追责当时到底改了什么。');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="删除这条记录">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
