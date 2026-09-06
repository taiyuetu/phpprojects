<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<h4 class="mb-3 text-center">登录</h4>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= url('/login') ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="mb-3">
        <label class="form-label">邮箱</label>
        <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label">密码</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">保持登录状态</label>
        <div class="d-flex gap-4">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="remember" id="remember_no" value="0"
                       <?= empty($old['remember']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember_no">不记住</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="remember" id="remember_yes" value="1"
                       <?= !empty($old['remember']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember_yes">记住密码（30 天内免登录）</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-100">登录</button>
</form>

<p class="text-center mt-3 mb-0 small text-muted">
    还没有账号？<a href="<?= url('/register') ?>">立即注册</a>
</p>
<p class="text-center mt-2 mb-0 small text-muted">
    演示账号：<code>admin@example.com</code> / <code>password</code>
</p>
