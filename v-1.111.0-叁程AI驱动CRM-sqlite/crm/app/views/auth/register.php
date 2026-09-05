<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<h4 class="mb-3 text-center">注册账号</h4>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($closed)): ?>
    <p class="text-muted small mb-3">新账号注册由部署方管理（未开放）。<a href="<?= url('/login') ?>">返回登录</a></p>
<?php else: ?>
<form method="POST" action="<?= url('/register') ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="mb-3">
        <label class="form-label">姓名</label>
        <input type="text" name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label">邮箱</label>
        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">密码</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">确认密码</label>
        <input type="password" name="password_confirm" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">注册</button>
</form>
<?php endif; ?>

<p class="text-center mt-3 mb-0 small text-muted">
    已有账号？<a href="<?= url('/login') ?>">去登录</a>
</p>
