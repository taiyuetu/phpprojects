<?php
/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="<?= e(APP_AUTHOR) ?>">
    <meta name="copyright" content="<?= e(appCopyright()) ?> <?= e(APP_RIGHTS) ?>">
    <title><?= e(appName()) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-brand">
            <i class="bi bi-diagram-3-fill"></i> <?= e(appName()) ?>
        </div>
        <?php if ($tagline = appSetting('app_tagline')): ?>
            <div class="auth-tagline"><?= e($tagline) ?></div>
        <?php endif; ?>
        <?php if ($company = appSetting('company_name')): ?>
            <div class="auth-company"><?= e($company) ?></div>
        <?php endif; ?>
        <div class="card auth-card shadow-sm">
            <div class="card-body p-4">
                <?= $content ?>
            </div>
        </div>
        <div class="auth-footer">
            <?= e(appCopyright()) ?> · <?= e(APP_RIGHTS) ?>
        </div>
    </div>
</body>
</html>
