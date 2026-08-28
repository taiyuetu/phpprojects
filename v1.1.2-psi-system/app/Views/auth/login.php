<?php use App\Core\Router; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · PSI System</title>
    <link rel="stylesheet" href="<?= Router::url('/assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>PSI System</h1>
        <p class="subtitle">Purchase · Sales · Inventory Management</p>

        <?php if (!empty($_SESSION['flash']['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']['error']) ?></div>
            <?php unset($_SESSION['flash']['error']); ?>
        <?php endif; ?>

        <form method="post" action="<?= Router::url('/login') ?>">
            <?= $this->csrfField() ?>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus value="admin@psi.local">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required value="admin123">
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <p class="login-hint">Default admin: <strong>admin@psi.local</strong> / <strong>admin123</strong><br>Run <code>php database/setup.php</code> first if you haven't.</p>
    </div>
</div>
</body>
</html>
