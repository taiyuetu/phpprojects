<?php

/**
 * Class autoloader.
 *
 * All application classes are global (no namespaces) and live in one of three
 * folders with a file name matching the class name:
 *   app/core/        e.g. Database.php   -> class Database
 *   app/models/      e.g. Order.php      -> class Order
 *   app/controllers/ e.g. DealController.php -> class DealController
 *
 * Views call model static helpers directly (Order::statusLabel(), etc.) and
 * controllers may reference models by class name without instantiating them.
 * Previously this forced every controller action to "pre-load" model classes
 * before rendering a view, which was fragile. This autoloader removes that
 * requirement: the first reference to any class loads its file automatically.
 *
 * Registered by bootstrap.php (and tests/bootstrap.php). PHP built-ins such as
 * PDO / finfo are untouched because the autoloader only fires for classes PHP
 * has not already loaded.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'controllers'] as $dir) {
        $file = APP_PATH . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
