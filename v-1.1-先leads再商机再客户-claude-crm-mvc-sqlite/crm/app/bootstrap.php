<?php

/**
 * Application bootstrap.
 * Required once by public/index.php (the single front controller).
 */

define('APP_PATH', __DIR__);
define('BASE_PATH', dirname(__DIR__));

// Set timezone to Asia/Shanghai (UTC+8)
date_default_timezone_set('Asia/Shanghai');

// ---- Class autoloader (core / models / controllers) ----
require APP_PATH . '/core/autoloader.php';

require APP_PATH . '/config/config.php';

// ---- Detect URL_ROOT (the sub-path the app is served from) ----
if (URL_ROOT_OVERRIDE !== '') {
    define('URL_ROOT', URL_ROOT_OVERRIDE);
} else {
    // e.g. /var/www/html/crm/public/index.php -> scriptDir = /crm/public
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    define('URL_ROOT', $scriptDir === '/' ? '' : $scriptDir);
}

// ---- Core classes ----
require APP_PATH . '/core/helpers.php';
require APP_PATH . '/core/Database.php';
require APP_PATH . '/core/Model.php';
require APP_PATH . '/core/Controller.php';
require APP_PATH . '/core/Router.php';

// ---- Session ----
session_name(SESSION_NAME);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ---- Routes ----
$router = new Router();
require APP_PATH . '/routes.php';

// ---- Dispatch ----
$uri = $_SERVER['REQUEST_URI'] ?? '/';
// Strip the sub-path prefix so route patterns stay root-relative.
if (URL_ROOT !== '' && str_starts_with($uri, URL_ROOT)) {
    $uri = substr($uri, strlen(URL_ROOT));
    if ($uri === '') {
        $uri = '/';
    }
}

$router->dispatch($uri);
