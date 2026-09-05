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
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-diagram-3-fill"></i> <?= e(appName()) ?>
        </div>
        <?php if ($tagline = appSetting('app_tagline')): ?>
            <div class="px-3 pb-2 small text-white-50"><?= e($tagline) ?></div>
        <?php endif; ?>
        <nav class="sidebar-nav">
            <a href="<?= url('/') ?>" class="<?= ($_SERVER['REQUEST_URI'] === url('/')) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> 仪表盘
            </a>
            <a href="<?= url('/customers') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/customers') ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> 客户
            </a>
            <a href="<?= url('/leads') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/leads') ? 'active' : '' ?>">
                <i class="bi bi-magnet-fill"></i> 线索
            </a>
            <a href="<?= url('/deals') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/deals') ? 'active' : '' ?>">
                <i class="bi bi-currency-dollar"></i> 商机
            </a>
            <a href="<?= url('/orders') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/orders') ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> 订单
            </a>
            <a href="<?= url('/help') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/help') ? 'active' : '' ?>">
                <i class="bi bi-question-circle"></i> 使用说明
            </a>
            <a href="<?= url('/ai') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/ai') ? 'active' : '' ?>">
                <i class="bi bi-robot"></i> AI 助手
            </a>
            <a href="<?= url('/settings') ?>" class="<?= str_contains($_SERVER['REQUEST_URI'], '/settings') ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i> 设置
            </a>
        </nav>
        <div class="p-3 text-muted small mt-auto border-top border-secondary border-opacity-25">
            <?php if ($company = appSetting('company_name')): ?>
                <div class="text-truncate" title="<?= e($company) ?>"><?= e($company) ?></div>
            <?php endif; ?>
            <div class="text-truncate" title="<?= e(appCopyright()) ?> <?= e(APP_RIGHTS) ?>"><?= e(appCopyright()) ?></div>
            <span class="badge bg-secondary-subtle text-secondary"><?= e(APP_NAME_EN) ?> v<?= e(APP_VERSION) ?></span>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-title"></div>
            <div class="topbar-user dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= e(currentUser()['name'] ?? '账户') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item px-3 py-1" href="<?= url('/settings') ?>?tab=profile">
                            <i class="bi bi-person-gear"></i> 个人信息
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item px-3 py-1" href="<?= url('/settings') ?>?tab=password">
                            <i class="bi bi-shield-lock"></i> 修改密码
                        </a>
                    </li>
                    <?php if (isAdmin()): ?>
                        <li>
                            <a class="dropdown-item px-3 py-1" href="<?= url('/settings') ?>?tab=app">
                                <i class="bi bi-sliders"></i> 应用信息
                            </a>
                        </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="<?= url('/logout') ?>" method="POST" class="px-3 py-1">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
                            <button type="submit" class="btn btn-link p-0 text-decoration-none">
                                <i class="bi bi-box-arrow-right"></i> 退出登录
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= e($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= e($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
