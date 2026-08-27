<?php
use App\Core\Auth;
use App\Core\Router;
$currentPath = '/' . trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$isActive = fn(string $prefix) => str_starts_with($currentPath, $prefix) ? 'active' : '';
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'PSI System') ?> · PSI System</title>
    <link rel="stylesheet" href="<?= Router::url('/assets/css/style.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">PSI<span>·</span>System</div>
        <nav>
            <a href="<?= Router::url('/dashboard') ?>" class="<?= $isActive('/dashboard') ?>">📊 Dashboard</a>

            <div class="section-title">Catalog</div>
            <a href="<?= Router::url('/products') ?>" class="<?= $isActive('/products') ?>">📦 Products</a>
            <a href="<?= Router::url('/categories') ?>" class="<?= $isActive('/categories') ?>">🏷️ Categories</a>

            <div class="section-title">Transactions</div>
            <a href="<?= Router::url('/purchases') ?>" class="<?= $isActive('/purchases') ?>">🛒 Purchases</a>
            <a href="<?= Router::url('/sales') ?>" class="<?= $isActive('/sales') ?>">💵 Sales</a>

            <div class="section-title">Inventory</div>
            <a href="<?= Router::url('/inventory') ?>" class="<?= $currentPath === '/inventory' ? 'active' : '' ?>">📒 Stock Ledger</a>
            <a href="<?= Router::url('/inventory/low-stock') ?>" class="<?= $isActive('/inventory/low-stock') ?>">⚠️ Low Stock</a>

            <div class="section-title">Contacts</div>
            <a href="<?= Router::url('/suppliers') ?>" class="<?= $isActive('/suppliers') ?>">🏭 Suppliers</a>
            <a href="<?= Router::url('/customers') ?>" class="<?= $isActive('/customers') ?>">🧑‍💼 Customers</a>

            <div class="section-title">Reports</div>
            <a href="<?= Router::url('/reports/sales') ?>" class="<?= $isActive('/reports/sales') ?>">📈 Sales Report</a>
            <a href="<?= Router::url('/reports/purchases') ?>" class="<?= $isActive('/reports/purchases') ?>">📉 Purchase Report</a>
            <a href="<?= Router::url('/reports/stock') ?>" class="<?= $isActive('/reports/stock') ?>">🧮 Stock Valuation</a>
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <h1><?= htmlspecialchars($title ?? '') ?></h1>
            <div class="user-menu">
                <span><?= htmlspecialchars($user['name'] ?? '') ?> <span class="badge badge-gray"><?= htmlspecialchars($user['role'] ?? '') ?></span></span>
                <a href="<?= Router::url('/logout') ?>" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </div>

        <div class="content">
            <?php if (!empty($_SESSION['flash']['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']['success']) ?></div>
                <?php unset($_SESSION['flash']['success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash']['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']['error']) ?></div>
                <?php unset($_SESSION['flash']['error']); ?>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</div>
</body>
</html>
