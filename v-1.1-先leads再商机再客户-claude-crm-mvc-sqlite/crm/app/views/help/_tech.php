<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 *
 * 使用说明 — 页面本身保持简单，技术参考区（结构 / 数据 / 路由 / 设置 / AI / 约定）
 * 由 AppMap 实时计算后传进来，所以本文档不会和代码脱节。
 *
 * @var array $map  AppMap::all()
 */
$php = $map['php'];
$bad = [];
foreach ($php['extensions'] as $ext => $on) {
    if (!$on) { $bad[] = $ext; }
}
?>
<div class="card card-table p-4 mb-4" id="tech">
    <h5 class="mb-1"><i class="bi bi-binary-code me-2"></i>技术参考（实时生成，非手写）</h5>
    <p class="small text-muted">
        本页下面的表、路由、设置项与 AI 工具清单都由 <code>app/core/AppMap.php</code> 从
        <strong>运行中的代码和数据库</strong>读出（<code>Router::all()</code> /
        <code>pragma_table_info</code> / <code>Setting::definitions()</code> / <code>Ai::tools()</code>），
        只有“流程与规则”一节是人工撰写的意图说明。
        需要整份纯文本喂给模型时用 <a href="<?= url('/help/context') ?>">/help/context</a>。
    </p>

    <div class="d-flex flex-wrap gap-2 small mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="#tech-flows">流程与规则</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-schema">数据字典</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-routes">路由总表</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-settings">设置项</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-ai">AI 工具与服务商</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-conv">约定与已知坑</a>
        <a class="btn btn-sm btn-outline-secondary" href="#tech-tests">测试</a>
    </div>

    <!-- 运行环境 -->
    <h6 class="text-muted">运行环境</h6>
    <div class="table-responsive mb-3">
        <table class="table table-sm mb-0">
            <tbody>
            <tr>
                <td style="width:170px">应用</td>
                <td><?= e($map['app']['name']) ?> (<?= e($map['app']['name_en']) ?>) v<?= e($map['app']['version']) ?>
                    · 环境 <?= e($map['app']['env']) ?> · <?= e($map['app']['copyright']) ?></td>
            </tr>
            <tr>
                <td>PHP / SQLite</td>
                <td>PHP <?= e($php['version']) ?> · SQLite <?= e($php['sqlite']) ?>
                    · 时区 <?= e($php['timezone']) ?>（比 UTC 快 <?= (int) $php['utc_offset'] ?> 小时）</td>
            </tr>
            <tr>
                <td>扩展</td>
                <td>
                    <?php foreach ($php['extensions'] as $ext => $on): ?>
                        <span class="badge text-bg-<?= $on ? 'success' : 'secondary' ?>"><?= e($ext) ?>: <?= $on ? '有' : '无' ?></span>
                    <?php endforeach; ?>
                    <?php if ($bad): ?>
                        <div class="small text-muted mt-1">未启用 <?= e(implode(', ', $bad)) ?>；
                            本项目刻意不依赖 curl / mbstring，但 <strong>openssl 缺失会让 AI 无法访问 https 服务商</strong>。</div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>HTTPS 出站</td>
                <td>
                    <?php if ($php['https']): ?>
                        <span class="badge text-bg-success">可用</span>
                    <?php else: ?>
                        <span class="badge text-bg-danger">不可用</span>
                        <span class="small"><?= e(AiClient::httpsFixHint()) ?></span>
                    <?php endif; ?>
                    <span class="small text-muted">协议：<?= e(implode(', ', $php['transports'])) ?></span>
                </td>
            </tr>
            <tr>
                <td>数据库文件</td>
                <td><code>database/<?= e(basename($map['app']['db'])) ?></code>
                    （约 <?= number_format($map['app']['db_size'] / 1024) ?> KB，已被 .gitignore 忽略）
                    · 迁移：<code>php database/migrate.php</code></td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- 流程与规则 -->
    <h6 class="text-muted" id="tech-flows">流程与规则（人工撰写的意图层）</h6>
    <?php foreach ($map['flows'] as $flow): ?>
        <div class="border rounded p-3 mb-2">
            <strong class="small"><?= e($flow['title']) ?></strong>
            <ol class="small mb-2 ps-4">
                <?php foreach ($flow['steps'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
            </ol>
            <ul class="small mb-0 ps-4">
                <?php foreach ($flow['rules'] as $r): ?>
                    <li class="<?= str_starts_with($r, '⚠') ? 'text-danger' : 'text-muted' ?>"><?= e($r) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <!-- 数据字典 -->
    <h6 class="text-muted mt-4" id="tech-schema">数据字典（<?= count($map['schema']) ?> 张表，实时读取）</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-3">
            <thead><tr class="text-muted small"><th style="width:130px">表</th><th>列（名:类型，标 * 为 NOT NULL，标 PK 为主键）</th></tr></thead>
            <tbody>
            <?php foreach ($map['schema'] as $table => $info): ?>
                <?php
                if (in_array($table, ['_migrations'], true)) { continue; }
                $cells = [];
                foreach ($info['columns'] as $c) {
                    $txt = $c['name'] . ':' . strtolower((string) $c['type']);
                    if ((int) $c['pk'] > 0) { $txt .= ' PK'; }
                    elseif ((int) $c['notnull'] > 0) { $txt .= ' *'; }
                    $cells[] = $txt;
                }
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($table) ?><div class="small text-muted"><?= (int) $info['rows'] ?> 行</div></td>
                    <td class="small">
                        <?= e(implode('，', $cells)) ?>
                        <?php if ($info['foreign']): ?>
                            <div class="text-muted mt-1">外键：<?= e(implode('；', $info['foreign'])) ?></div>
                        <?php endif; ?>
                        <?php if ($info['checks']): ?>
                            <div class="text-success mt-1">枚举约束：<?= e(implode('；', $info['checks'])) ?></div>
                        <?php endif; ?>
                        <?php if ($info['indexes']): ?>
                            <div class="text-muted">索引：<?= e(implode(', ', $info['indexes'])) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h6 class="text-muted mt-4">枚举一览（由 CHECK 约束解析，写值时只能用这些）</h6>
    <ul class="small mb-3">
        <?php foreach (AppMap::enums() as $column => $values): ?>
            <?php if ($column === 'ai_actions.status') { continue; } ?>
            <li><code><?= e($column) ?></code> = <?= e(str_replace('|', ' ｜ ', $values)) ?></li>
        <?php endforeach; ?>
    </ul>

    <!-- 路由总表 -->
    <h6 class="text-muted" id="tech-routes">路由总表（app/routes.php 实际注册 <?= count($map['routes']) ?> 条）</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-3">
            <thead><tr class="text-muted small"><th style="width:70px">方法</th><th style="width:300px">路径</th><th>处理器</th></tr></thead>
            <tbody>
            <?php foreach ($map['routes'] as $r): ?>
                <tr>
                    <td><span class="badge text-bg-<?= $r['method'] === 'GET' ? 'info' : ($r['method'] === 'POST' ? 'success' : 'warning') ?>"><?= e($r['method']) ?></span></td>
                    <td><code><?= e($r['path']) ?></code></td>
                    <td class="small"><?= e($r['handler']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted">
        PUT / DELETE 由表单里的隐藏字段 <code>_method</code> 伪造（浏览器不直接发这两种方法）。
        除 <code>/login</code>、<code>/register</code> 外全部要求登录；
        <code>/settings/app</code>、<code>/settings/app/reset</code>、<code>/ai/test</code> 额外要求 admin。
    </p>

    <!-- 设置项 -->
    <h6 class="text-muted mt-4" id="tech-settings">设置项（app_settings 键值表，同名环境变量优先）</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead><tr class="text-muted small"><th style="width:160px">key</th><th style="width:130px">界面标签</th><th style="width:90px">页签</th><th style="width:120px">环境变量</th><th>当前值 / 默认</th></tr></thead>
            <tbody>
            <?php foreach ($map['settings'] as $s): ?>
                <tr>
                    <td><code><?= e($s['key']) ?></code><?php if ($s['secret']): ?> <span class="badge text-bg-danger">密钥</span><?php endif; ?></td>
                    <td class="small"><?= e($s['label']) ?></td>
                    <td class="small"><?= $s['group'] === 'ai' ? 'AI 助手' : '应用信息' ?></td>
                    <td class="small text-muted"><?= e($s['env']) ?><?= $s['type'] === 'password' ? '' : '' ?></td>
                    <td class="small">当前：<?= e($s['current']) ?>
                        <?php if ($s['options']): ?>｜可选：<?= e(implode(', ', $s['options'])) ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted">
        读取入口：<code>appSetting()</code> / <code>appName()</code> / <code>money()</code> / <code>appCopyright()</code>；
        密钥只回显掩码，留空保存 = 保持原值；“恢复默认”按页签分组，且绝不带走密钥。
    </p>

    <!-- AI -->
    <h6 class="text-muted mt-4" id="tech-ai">AI 助手：工具白名单与服务商预设</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead><tr class="text-muted small"><th style="width:190px">工具（唯一可执行动作）</th><th>参数（标 * 必填；枚举值写在括号里）</th></tr></thead>
            <tbody>
            <?php foreach ($map['ai_tools'] as $t): ?>
                <tr<?= $t['destructive'] ? ' class="table-danger"' : '' ?>>
                    <td><code><?= e($t['name']) ?></code>
                        <span class="badge <?= $t['kind'] === 'delete' ? 'bg-danger' : ($t['kind'] === 'read' ? 'bg-secondary' : 'bg-primary') ?>"><?= e($t['kind_label']) ?></span>
                        <div class="small text-muted"><?= e($t['label']) ?><?= $t['hint'] !== '' ? ' — ' . e($t['hint']) : '' ?></div>
                    </td>
                    <td class="small">
                        <?php foreach ($t['params'] as $p): ?>
                            <span class="me-2"><?= e($p['name']) ?>:<?= e($p['type']) ?><?= $p['required'] ? '*' : '' ?><?= $p['options'] ? '（' . e(implode('|', $p['options'])) . '）' : '' ?></span>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead><tr class="text-muted small"><th style="width:100px">服务商</th><th style="width:290px">端点</th><th>模型（默认值在前）</th></tr></thead>
            <tbody>
            <?php foreach ($map['ai_models'] as $p): ?>
                <tr>
                    <td><code><?= e($p['key']) ?></code><div class="small text-muted"><?= e($p['label']) ?></div></td>
                    <td class="small"><code><?= e($p['base'] ?: '（自定义时由你填写）') ?></code><?= $p['needs_key'] ? '<span class="text-danger"> · 需 API Key</span>' : '' ?></td>
                    <td class="small"><strong><?= e($p['default'] ?: '—') ?></strong><?= $p['models'] ? '；可选：' . e(implode(', ', $p['models'])) : '' ?>
                        <?= $p['fast'] !== '' ? '<div class="text-muted">快速模式（关掉模型思考）发送：<code>' . e($p['fast']) . '</code></div>' : '<div class="text-muted">无思考开关</div>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mb-0">
        参数里的 <code>*_id</code>（<code>lead_id</code> / <code>customer_id</code> / <code>deal_id</code> / <code>order_id</code>）同时接受<strong>稳定编号</strong>与数字 ID：
        客户 <code>CUS-000007</code>、线索 <code>LEAD-000007</code>、商机 <code>DEAL-000007</code>（存在各表的 <code>public_code</code> 列，上有唯一索引），订单沿用 <code>order_number</code>。
        编号由 <code>Model::publicCode()</code> 在新增时按 id 派生写入（不可手工指定，也不在任何 <code>update_*</code> 的参数里），
        <code>Model::idFromReference()</code> 把 <code>CUS-000007</code> / <code>cus 7</code> / <code>#7</code> / <code>7</code> 统一解析成行 id；
        编出来的编号必然解析失败并被拒，而不是命中另一条记录。<br>
        完整链路：<code>/ai</code> → <code>AiController@plan</code> → <code>Ai::complete()</code> →
        模型返回 JSON → <code>Ai::validatePlan()</code>（白名单 + 参数 + <code>canManageResource()</code> 归属）→
        预览确认（默认）或自动执行 → <code>Ai::execute()</code> 走既有模型写库 → 全程记 <code>ai_actions</code>。
        离线可用：内置演示模型（不联网）、本地 Ollama（http）。云端需要 openssl 与 https。
    </p>

    <!-- 约定 -->
    <h6 class="text-muted mt-4" id="tech-conv">约定与已知坑</h6>
    <?php foreach ($map['conventions'] as $c): ?>
        <div class="mb-2">
            <strong class="small <?= str_contains($c['title'], '⚠') ? 'text-danger' : '' ?>"><?= e($c['title']) ?></strong>
            <div class="small text-muted"><?= e($c['body']) ?></div>
        </div>
    <?php endforeach; ?>

    <!-- 测试 -->
    <h6 class="text-muted mt-4" id="tech-tests">测试（tests/cases · <?= count($map['tests']) ?> 个文件 ·
        <?= array_sum(array_column($map['tests'], 'tests')) ?> 个用例函数）</h6>
    <p class="small text-muted">
        <code>php tests/run.php</code> 全量；<code>php tests/run.php Order</code> 按名过滤；单个用例文件可直接执行。
        每个用例文件跑在自己的进程和自己的临时 SQLite（用真实的 <code>migrate.php</code> 建库，所以迁移工具本身每次都被测）。
        测试不联网、不需要密钥：HTTP 用注入的假 transport，AI 用内置演示模型。
    </p>
    <ul class="small mb-0">
        <?php foreach ($map['tests'] as $t): ?>
            <li><code><?= e($t['name']) ?></code> — <?= (int) $t['tests'] ?> 项（<code><?= e($t['file']) ?></code>）</li>
        <?php endforeach; ?>
    </ul>
</div>
