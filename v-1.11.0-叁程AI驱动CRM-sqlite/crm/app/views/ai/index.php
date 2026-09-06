<?php
/** @var array $config @var array $tools @var array|null $plan @var array $actions @var array $recent @var string $csrf
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$enabled = (bool) ($config['enabled'] ?? false);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-robot me-2"></i>AI 助手</h3>
    <div class="text-end small">
        <span class="badge <?= $enabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
            <?= $enabled ? '已启用' : '未启用' ?>
        </span>
        <span class="badge bg-light text-dark border"><?= e($config['label'] ?? '—') ?></span>
        <span class="badge bg-light text-dark border"><?= e($config['model'] ?: '默认模型') ?></span>
        <span class="badge bg-light text-dark border">
            <?= ($config['auto_apply'] ?? false) ? '自动执行' : '预览确认' ?>
        </span>
        <?php $ctxMin = Ai::contextMinutes(); ?>
        <span class="badge <?= $ctxMin > 0 ? 'bg-primary-subtle text-primary border' : 'bg-secondary-subtle text-secondary' ?>"
              title="窗口内你自己发起的历史请求（含 AI 的回答与涉及的记录编号）会一并送进模型，所以“刚才那条”“上次那个客户”能接得上；历史直接读审计表 ai_actions">
            上下文 <?= $ctxMin > 0 ? e(Ai::contextWindowLabel($ctxMin)) : '已关闭' ?>
        </span>
        <?php if (!empty($config['fast_mode'])): ?>
            <span class="badge bg-success-subtle text-success border" title="让模型直接产出计划，而不是先写一段推理（提速首选）">快速模式</span>
        <?php endif; ?>
        <span class="badge bg-light text-dark border" title="模型响应超过这个时间会给出提示，不会把页面拖到 Fatal">
            超时 <?= (int) ($config['timeout'] ?? 45) ?>s
        </span>
        <?php if (!empty($config['max_tokens'])): ?>
            <span class="badge bg-light text-dark border" title="限制模型输出长度，是提速的首选">
                ≤<?= (int) $config['max_tokens'] ?> tokens
            </span>
        <?php endif; ?>
        <a href="<?= url('/ai/history') ?>" class="ms-2">操作记录</a>
    </div>
</div>

<?php if (!$enabled): ?>
    <div class="alert alert-warning">
        <strong>AI 助手当前未启用。</strong>
        开启后可以把邮件、WhatsApp、会议记录等原文粘进来，让它整理成线索、更新状态或补跟进记录。
        <?php if (isAdmin()): ?>
            <a href="<?= url('/settings') ?>?tab=ai" class="alert-link">前往 设置 → AI 助手 配置</a>
        <?php else: ?>
            请联系管理员在「设置 → AI 助手」中配置服务商与 API Key。
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($enabled): ?>
<div class="card card-table p-4 mb-4">
    <h6 class="text-muted mb-3">把素材交给它</h6>
    <form method="POST" action="<?= url('/ai/plan') ?>" id="ai-plan-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <div class="mb-2">
            <label class="form-label">指令 / 原文</label>
            <textarea name="instruction" class="form-control" rows="6" required maxlength="6000"
                      placeholder="例：客户 Robert Fox（robert@globex.com，+1-555-0102）今天来信，想采购 200 套轴承，预计 3 万美元，来源 WhatsApp。请建线索并安排下周跟进。"><?= e($_POST['instruction'] ?? '') ?></textarea>
            <div class="form-text">
                内容会发送给所选 AI 服务商<?php if (($config['provider'] ?? '') !== 'mock' && ($config['provider'] ?? '') !== 'ollama'): ?>（当前：<?= e($config['label']) ?>）<?php endif; ?>；
                本机演示模型与本地 Ollama 不会外发数据。
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary" data-budget="<?= (int) ($config['timeout'] ?? 45) ?>" data-fast="<?= (int) ($config['max_tokens'] ?? 0) ?>">
                <i class="bi bi-magic me-1"></i><?= ($config['auto_apply'] ?? false) ? '让 AI 处理' : '生成计划（不写库）' ?>
            </button>
            <span class="text-muted small">可用操作（<?= count($tools) ?> 项）：<?= e(implode(' / ', array_column($tools, 'label'))) ?></span><br>
            <?php $writable = array_sum(array_map(static fn($t) => count(Ai::fieldsFor($t)),
                ['leads', 'customers', 'deals', 'orders', 'follow_ups', 'products'])); ?>
            <span class="text-muted small">字段清单由表结构生成：线索/客户/商机/订单/跟进/商品合计 <strong><?= (int) $writable ?></strong> 个可写项；
                一句指令最多删 <?= (int) Ai::MAX_DELETES ?> 条，删除永远要你点“确认执行”。</span>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($plan): ?>
    <?php
    $planData = Ai::planOf($plan);
    $results  = Ai::resultsOf($plan);
    $statusMap = ['pending' => ['待确认', 'warning'], 'executed' => ['已执行', 'success'],
                  'cancelled' => ['已取消', 'secondary'], 'failed' => ['失败', 'danger'], 'invalid' => ['校验未通过', 'danger']];
    [$statusLabel, $statusColor] = $statusMap[$plan['status']] ?? [$plan['status'], 'secondary'];
    ?>
    <div class="card card-table p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="text-muted mb-0">计划 #<?= (int) $plan['id'] ?></h6>
            <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        </div>
        <p class="small text-muted mb-2">指令：<?= nl2br(e(textClip((string) $plan['instruction'], 400))) ?></p>
        <?php if (!empty($planData['reply'])): ?>
            <p class="mb-3"><i class="bi bi-chat-left-text me-1"></i><?= nl2br(e((string) $planData['reply'])) ?></p>
        <?php endif; ?>
        <?php $reads = (array) ($planData['read_results'] ?? []); ?>
        <?php if ($reads): ?>
            <?php // 查询不写库，所以生成计划时就已经跑完了，结果直接给你看 ?>
            <div class="card bg-light border-0 p-3 mb-3">
                <div class="small text-muted mb-2">
                    <i class="bi bi-search me-1"></i>查询结果（已执行，本次未改动任何数据）
                </div>
                <?php foreach ($reads as $r): ?>
                    <div class="small mb-2"><strong><?= e(Ai::toolLabel((string) ($r['tool'] ?? ''))) ?></strong>：
                        <?= nl2br(e((string) ($r['message'] ?? ''))) ?></div>
                    <?php foreach ((array) ($r['rows'] ?? []) as $row): ?>
                        <div class="small ms-3">
                            · <?= e((string) ($row['type'] ?? '')) ?> #<?= (int) ($row['id'] ?? 0) ?>
                            <?= e((string) ($row['detail'] ?? '')) ?>
                            <span class="text-muted">｜负责人：<?= e((string) ($row['owner'] ?? '—')) ?>
                                ｜你可操作：<?= empty($row['writable']) ? '否' : '是' ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($actions && Ai::hasDestructive($actions)): $sum = (array) ($planData['summary'] ?? []); ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <?php if (!empty($sum['delete'])): ?>
                    <strong>将删除 <?= (int) $sum['delete'] ?> 条记录</strong>；
                    <?php $cbits = [];
                    foreach ((array) ($sum['cascade'] ?? []) as $cname => $cn) {
                        if ((int) $cn > 0) { $cbits[] = $cname . ' ' . (int) $cn; }
                    }
                    if ($cbits): ?>连带 <?= e(implode('、', $cbits)) ?>；<?php endif; ?>
                    合计约 <strong><?= (int) ($sum['total'] ?? 0) ?> 行数据</strong>。<br>
                <?php endif; ?>
                请逐条核对下面的「将删除」与连带数量。即使开了自动执行，删除也必须你手动点确认；
                被删内容会在“操作记录”里留快照。
            </div>
        <?php endif; ?>
        <?php $rounds = (array) ($planData['rounds'] ?? []); ?>
        <?php if ($rounds): ?>
            <?php // 多轮查询的轨迹亮出来：人不信黑盒，要能看到“它查了什么、查到几条” ?>
            <div class="small text-muted mb-3">
                <i class="bi bi-arrow-repeat me-1"></i>本计划共 <?= count($rounds) + 1 ?> 轮，其中查询 <?= count($rounds) ?> 轮：
                <?php foreach ($rounds as $r): ?>
                    <?php $rows = 0; foreach ((array) ($r['results'] ?? []) as $x) { $rows += count((array) ($x['rows'] ?? [])); } ?>
                    <?php foreach ((array) ($r['asked'] ?? []) as $ask): ?>
                        第 <?= (int) ($r['round'] ?? 0) ?> 轮 <?= e(Ai::toolLabel((string) ($ask['tool'] ?? ''))) ?>
                       （<?= e(textClip(json_encode((array) ($ask['args'] ?? []), JSON_UNESCAPED_UNICODE) ?: '', 64)) ?>）
                        → 查到 <?= (int) $rows ?> 条；
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($plan['error'])): ?>
            <div class="alert alert-danger py-2 small mb-3"><?= e($plan['error']) ?></div>
        <?php endif; ?>

        <?php if ($actions): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr class="text-muted small">
                        <th style="width:150px">操作</th><th>参数</th><th style="width:200px">理由</th><th style="width:220px">校验</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($actions as $a): ?>
                        <?php $kind = (string) ($a['kind'] ?? 'write'); ?>
                        <tr<?= !empty($a['destructive']) ? ' class="table-danger"' : '' ?>>
                            <td class="fw-semibold">
                                <?= e($a['label'] ?? Ai::toolLabel((string) $a['tool'])) ?>
                                <span class="badge <?= $kind === 'delete' ? 'bg-danger' : ($kind === 'read' ? 'bg-secondary' : 'bg-primary') ?>"><?= $kind === 'delete' ? '删除' : ($kind === 'read' ? '查询' : '写入') ?></span>
                            </td>
                            <td class="small">
                                <?php foreach ((array) $a['args'] as $key => $value): ?>
                                    <div><span class="text-muted"><?= e($key) ?>：</span><?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></div>
                                <?php endforeach; ?>
                                <?php if (!empty($a['impact'])): $impact = (array) $a['impact']; ?>
                                    <div class="mt-1 text-danger">
                                        将删除：<?= e((string) ($impact['target'] ?? '')) ?>
                                        <?php foreach ((array) ($impact['who'] ?? []) as $zh => $val): ?>
                                            <span class="badge bg-body-secondary text-muted border"><?= e((string) $zh) ?>：<?= e((string) $val) ?></span>
                                        <?php endforeach; ?>
                                        <?php foreach ((array) ($impact['cascade'] ?? []) as $cname => $n): ?>
                                            <?php if ((int) $n > 0) ?><span class="badge bg-danger-subtle text-danger"><?= e($cname) ?> <?= (int) $n ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e($a['reason'] ?? '') ?></td>
                            <td class="small">
                                <?php if (!empty($a['errors'])): ?>
                                    <span class="text-danger"><?= e(implode('；', (array) $a['errors'])) ?></span>
                                <?php else: ?>
                                    <span class="text-success"><i class="bi bi-check-circle"></i> 通过</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($plan['status'] === 'pending'): ?>
            <p class="text-muted small mb-0">AI 认为不需要改动数据。</p>
        <?php endif; ?>

        <?php if ($results): ?>
            <hr>
            <h6 class="text-muted">执行结果</h6>
            <ul class="small mb-0">
                <?php foreach ($results as $r): ?>
                    <li class="<?= !empty($r['ok']) ? 'text-success' : 'text-danger' ?>">
                        <?= e($r['label'] ?? ($r['tool'] ?? '')) ?>：<?= e($r['message'] ?? '') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($plan['status'] === 'pending' && $actions): ?>
            <div class="d-flex gap-2 mt-3">
                <form method="POST" action="<?= url('/ai/apply') ?>"
                     onsubmit="return confirm('<?= Ai::hasDestructive($actions)
                        ? '这个计划包含删除操作，数据将无法恢复。确定执行？'
                        : '确认执行以上 ' . count($actions) . ' 项操作？数据将被写入。' ?>');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i><?= Ai::hasDestructive($actions) ? '确认执行（含删除）' : '确认执行' ?></button>
                </form>
                <form method="POST" action="<?= url('/ai/cancel') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                    <button type="submit" class="btn btn-outline-secondary">放弃此计划</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="mt-3 small text-muted">
            服务商 <?= e((string) $plan['provider']) ?> · 模型 <?= e((string) $plan['model']) ?>
            · 耗时 <?= (int) $plan['latency_ms'] ?>ms · 生成于 <?= e((string) $plan['created_at']) ?>
        </div>
    </div>
<?php endif; ?>

<div class="card card-table">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>最近请求</span>
        <a href="<?= url('/ai/history') ?>" class="btn btn-sm btn-outline-secondary">查看全部</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr class="text-muted small">
                <th>时间</th><th>指令</th><th>操作数</th><th>状态</th><th class="text-end">查看</th>
            </tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="5" class="text-center text-muted p-4">还没有 AI 请求记录。</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $row): ?>
                <?php $count = count(Ai::planOf($row)['actions']); ?>
                <tr>
                    <td class="small text-muted"><?= e($row['created_at']) ?></td>
                    <td class="small"><?= e(textClip((string) $row['instruction'], 60)) ?></td>
                    <td><?= $count ?></td>
                    <td><span class="badge text-bg-<?= $row['status'] === 'executed' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e($row['status']) ?></span></td>
                    <td class="text-end"><a href="<?= url('/ai') ?>?plan=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    // A model call can take tens of seconds, and a page that just sits there reads as
    // broken. Show a live wait counter against the configured budget, and stop
    // the double submit that a impatient second click would otherwise cause.
    (function () {
        var form = document.getElementById('ai-plan-form');
        if (!form) { return; }
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type=submit]');
            if (!btn || btn.dataset.pending) { return; }
            var budget = parseInt(btn.dataset.budget || '45', 10);
            setTimeout(function () {
                btn.dataset.pending = '1';
                btn.disabled = true;
                var t0 = Date.now();
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>'
                    + '生成中 <span id="ai-wait">0</span> 秒 ／ 预算 ' + budget + ' 秒';
                window.setInterval(function () {
                    var el = document.getElementById('ai-wait');
                    if (el) { el.textContent = Math.round((Date.now() - t0) / 1000); }
                }, 1000);
            }, 0);
        });
    })();
</script>