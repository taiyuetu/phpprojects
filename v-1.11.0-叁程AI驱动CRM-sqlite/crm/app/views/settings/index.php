<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
$tab = $tab ?? 'profile';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">设置</h3>
    <span class="text-muted small">v<?= e(APP_VERSION) ?></span>
</div>

<div class="card card-table">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?= url('/settings?tab=profile') ?>" role="tab"
                   class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>">
                    <i class="bi bi-person-circle me-1"></i>个人信息
                </a>
            </li>
            <?php if (isAdmin()): ?>
                <li class="nav-item" role="presentation">
                    <a href="<?= url('/settings?tab=app') ?>" role="tab"
                       class="nav-link <?= $tab === 'app' ? 'active' : '' ?>">
                        <i class="bi bi-sliders me-1"></i>应用信息
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="<?= url('/settings?tab=ai') ?>" role="tab"
                       class="nav-link <?= $tab === 'ai' ? 'active' : '' ?>">
                        <i class="bi bi-robot me-1"></i>AI 助手
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <a href="<?= url('/settings?tab=password') ?>" role="tab"
                   class="nav-link <?= $tab === 'password' ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock me-1"></i>修改密码
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body tab-content">
        <!-- ============ 个人信息 ============ -->
        <div class="tab-pane fade <?= $tab === 'profile' ? 'show active' : '' ?>" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-7">
                    <h6 class="text-muted mb-3">我的资料</h6>
                    <form method="POST" action="<?= url('/settings/profile') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">姓名 <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required maxlength="60"
                                       value="<?= e($user['name'] ?? '') ?>">
                                <div class="form-text">客户 / 商机 / 订单的“负责人”即显示此姓名。</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">邮箱 <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required maxlength="120"
                                       value="<?= e($user['email'] ?? '') ?>">
                                <div class="form-text">同时用于登录。</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">职位</label>
                                <input type="text" name="job_title" class="form-control" maxlength="60"
                                       value="<?= e($user['job_title'] ?? '') ?>" placeholder="如：销售经理">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">电话</label>
                                <input type="text" name="phone" class="form-control" maxlength="40"
                                       value="<?= e($user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" name="whatsapp" class="form-control" maxlength="40"
                                       value="<?= e($user['whatsapp'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">角色</label>
                                <input type="text" class="form-control" value="<?= e(($user['role'] ?? '') === 'admin' ? '管理员' : '销售') ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">备注</label>
                                <textarea name="notes" class="form-control" rows="2"
                                          maxlength="500" placeholder="负责区域、交接说明等（仅自己可见）"><?= e($user['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>保存个人信息</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <h6 class="text-muted mb-3">信息同步范围</h6>
                    <p class="small text-muted">
                        各业务表只保存“用户 ID”，姓名等资料在读取时从本账号取，因此保存后以下记录会<strong>立即同步</strong>显示新资料。
                    </p>
                    <table class="table table-sm mb-2">
                        <tbody>
                        <?php foreach ($references as $ref): ?>
                            <tr>
                                <td><a href="<?= url($ref['url']) ?>" class="text-decoration-none"><?= e($ref['label']) ?></a></td>
                                <td class="text-end">
                                    <span class="badge <?= $ref['count'] ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?>">
                                        <?= (int) $ref['count'] ?> 条
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($user['updated_at'])): ?>
                        <p class="small text-muted mb-0">上次更新：<?= e($user['updated_at']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php /* ============ 应用信息 (admin only) ============ */ if (isAdmin()): ?>
        <div class="tab-pane fade <?= $tab === 'app' ? 'show active' : '' ?>" role="tabpanel">
            <h6 class="text-muted mb-3">应用信息</h6>
            <p class="small text-muted">这些设置对所有登录用户生效，保存后立即刷新界面。</p>
            <form method="POST" action="<?= url('/settings/app') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="row g-3">
                    <?php foreach ($definitions as $key => $def): ?>
                        <?php if (($def['group'] ?? 'app') !== 'app') { continue; } ?>
                        <div class="col-md-6">
                            <label class="form-label"><?= e($def['label']) ?></label>
                            <?php $value = $settings[$key] ?? $def['default']; ?>
                            <?php if (($def['type'] ?? 'text') === 'select'): ?>
                                <select name="<?= e($key) ?>" class="form-select">
                                    <?php foreach (Setting::definitionOptions($key) as $opt): ?>
                                        <option value="<?= e($opt['value']) ?>" <?= $value === $opt['value'] ? 'selected' : '' ?>>
                                            <?= e($opt['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= e($key) ?>" class="form-control"
                                       maxlength="<?= (int) ($def['max'] ?? 255) ?>"
                                       value="<?= e($value) ?>" placeholder="<?= e($def['placeholder'] ?? '') ?>">
                            <?php endif; ?>
                            <?php if (!empty($def['hint'])): ?>
                                <div class="form-text"><?= e($def['hint']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($changes[$key]['updated_at'])): ?>
                                <div class="form-text">
                                    由 <?= e($changes[$key]['updated_by_name'] ?? '系统') ?>
                                    于 <?= e($changes[$key]['updated_at']) ?> 修改
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>保存应用信息</button>
                </div>
            </form>

            <div class="mt-4">
                <p class="small text-muted mb-1">源码文件头部的固定声明（不随界面设置变化）：</p>
                <code class="small"><?= e(APP_COPYRIGHT . ' — ' . APP_RIGHTS) ?></code>
            </div>

            <div class="mt-4">
                <h6 class="text-muted">恢复默认</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($definitions as $key => $def): ?>
                        <?php if (($def['group'] ?? 'app') !== 'app') { continue; } ?>
                        <form method="POST" action="<?= url('/settings/app/reset') ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <input type="hidden" name="setting_key" value="<?= e($key) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <?= e($def['label']) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <form method="POST" action="<?= url('/settings/app/reset') ?>"
                          onsubmit="return confirm('确定将全部应用信息恢复为默认值？');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="setting_key" value="all">
                        <button type="submit" class="btn btn-sm btn-outline-danger">全部恢复默认</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php /* ============ AI 助手（仅管理员） ============ */ if (isAdmin()): ?>
        <div class="tab-pane fade <?= $tab === 'ai' ? 'show active' : '' ?>" role="tabpanel">
            <h6 class="text-muted mb-3">AI 助手</h6>
            <?php
            $providers = AiClient::providers();
            $current   = $settings['ai_provider'] ?? 'mock';
            $models    = $providers[$current]['models'] ?? [];
            $keyState  = $secrets['ai_api_key'] ?? ['set' => false, 'masked' => ''];
            $cfg       = $aiConfig ?? AiClient::config();
            $aiDiag     = $aiDiag ?? AiClient::diagnostics();
            ?>
            <div class="alert alert-light border small">
                <strong>它会做什么：</strong>把你要的改动返回成一份“操作计划”（只能建线索/改状态/加跟进/建客户・商机等白名单里的几种），
                参数与权限都经服务端复查；默认模式下仍需你点“确认执行”才写库，全过程留在 <code>ai_actions</code> 记录表。
                <a href="<?= url('/ai') ?>">去 AI 助手页</a>·<a href="<?= url('/ai/history') ?>">操作记录</a>
            </div>

            <?php if (!($aiDiag['https'] ?? true)): ?>
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= e($aiDiag['https_hint']) ?><br>
                    当前 PHP <?= e($aiDiag['php']) ?>，可用出站协议：<?= e($aiDiag['transports']) ?>。
                </div>
            <?php endif; ?>
            <div class="alert <?= !empty($cfg['enabled']) ? 'alert-success' : 'alert-secondary' ?> py-2 small">
                当前状态：<?= !empty($cfg['enabled']) ? '<strong>已启用</strong>' : '<strong>未启用</strong>' ?>
                · <?= e($cfg['label']) ?>
                · 模型 <?= e($cfg['model'] ?: '默认') ?>
                · <?= !empty($cfg['auto_apply']) ? '自动执行' : '预览确认' ?>
                · Key：<?= $cfg['key_from_env'] ? '由 .env 的 AI_API_KEY 提供（优先于下方填写值）'
                    : ($keyState['set'] ? '已保存 ' . e($keyState['masked']) : '未设置') ?>
            </div>

            <form method="POST" action="<?= url('/settings/app') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="row g-3">
                    <?php foreach ($definitions as $key => $def): ?>
                        <?php if (($def['group'] ?? 'app') !== 'ai') { continue; } ?>
                        <div class="col-md-6">
                            <label class="form-label"><?= e($def['label']) ?></label>
                            <?php $value = $settings[$key] ?? $def['default']; ?>

                            <?php if (($def['type'] ?? 'text') === 'select'): ?>
                                <select name="<?= e($key) ?>" class="form-select">
                                    <?php foreach (Setting::definitionOptions($key) as $opt): ?>
                                        <option value="<?= e($opt['value']) ?>" <?= $value === $opt['value'] ? 'selected' : '' ?>>
                                            <?= e($opt['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (($def['type'] ?? '') === 'password'): ?>
                                <input type="password" name="<?= e($key) ?>" class="form-control" autocomplete="new-password"
                                       value="" placeholder="<?= $keyState['set']
                                            ? '已保存 ' . e($keyState['masked']) . '（留空保持不变）'
                                            : '粘贴 API Key' ?>">
                                <?php if (!empty($keyState['set'])): ?>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="ai_api_key_clear" value="1" id="clearKey">
                                        <label class="form-check-label small" for="clearKey">清除已保存的 API Key</label>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <input type="text" name="<?= e($key) ?>" class="form-control" list="ai-models"
                                       maxlength="<?= (int) ($def['max'] ?? 255) ?>"
                                       value="<?= e($value) ?>" placeholder="<?= e($def['placeholder'] ?? '') ?>">
                            <?php endif; ?>

                            <?php if (!empty($def['hint'])): ?>
                                <div class="form-text"><?= e($def['hint']) ?></div>
                            <?php endif; ?>
                            <?php if ($key === 'ai_model' && $models): ?>
                                <div class="form-text">该服务商常用模型：<?= e(implode(', ', $models)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <datalist id="ai-models">
                        <?php foreach ($providers as $list): foreach ($list['models'] ?? [] as $m): ?>
                            <option value="<?= e($m) ?>"></option>
                        <?php endforeach; endforeach; ?>
                    </datalist>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>保存 AI 设置</button>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <form method="POST" action="<?= url('/ai/test') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button type="submit" class="btn btn-outline-success">
                        <i class="bi bi-plug me-1"></i>测试连接（并拉取模型列表）
                    </button>
                </form>
                <form method="POST" action="<?= url('/settings/app/reset') ?>"
                      onsubmit="return confirm('确定把 AI 设置恢复为默认值？（不会清除 API Key）');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="setting_group" value="ai">
                    <input type="hidden" name="setting_key" value="all">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>恢复 AI 默认
                    </button>
                </form>
                <?php foreach ($providers as $pk => $p): ?>
                    <?php if ($p['base'] === '' && $pk !== 'mock') { continue; } ?>
                    <form method="POST" action="<?= url('/settings/app') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="ai_provider" value="<?= e($pk) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e($p['label']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-warning mt-4 small mb-0">
                <strong>用云端服务商前请注意：</strong>指令内容会连同“当前用户可见的客户/线索/商机 ID 快照”一起发送给第三方 API。
                不想外发数据就选<strong>本地 Ollama</strong>（需本机已跑 <code>ollama serve</code>）或
                <strong>内置演示模型</strong>（完全离线，可用于熟悉流程）。
                建议把密钥写在 <code>.env</code> 的 <code>AI_API_KEY</code>，它会优先于这里填写的值且不存入数据库。
            </div>
        </div>
        <?php endif; ?>

        <!-- ============ 修改密码 ============ -->
        <div class="tab-pane fade <?= $tab === 'password' ? 'show active' : '' ?>" role="tabpanel">
            <h6 class="text-muted mb-3">修改密码</h6>
            <form method="POST" action="<?= url('/settings/password') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="row g-3" style="max-width:560px">
                    <div class="col-12">
                        <label class="form-label">当前密码 <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">新密码 <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                        <div class="form-text">至少 6 个字符。</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">确认新密码 <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirm" class="form-control" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>更新密码</button>
                </div>
            </form>
        </div>
    </div>
</div>
