<?php $tab = $tab ?? 'profile'; ?>

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
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
                        <div class="col-md-6">
                            <label class="form-label"><?= e($def['label']) ?></label>
                            <?php $value = $settings[$key] ?? $def['default']; ?>
                            <?php if (($def['type'] ?? 'text') === 'select'): ?>
                                <select name="<?= e($key) ?>" class="form-select">
                                    <?php foreach ($def['options'] as $opt): ?>
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
