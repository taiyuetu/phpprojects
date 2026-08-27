<?php

/**
 * Front Controller — every request enters the app through here.
 * Responsibilities: bootstrap, autoload, session, route table, dispatch.
 */

error_reporting(E_ALL);

$config = require __DIR__ . '/../config/config.php';
ini_set('display_errors', !empty($config['debug']) ? '1' : '0');

session_name($config['session_name']);
session_start();

// ---- Simple PSR-4-ish autoloader (no Composer needed) ----
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Router;
use App\Core\Auth;

Router::$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// ---- Route table ----
require __DIR__ . '/../routes/web.php';

// ---- Auth gate: everything except /login requires a session ----
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPaths = ['/login'];
$normalized = '/' . trim(str_replace(Router::$basePath, '', $uri), '/');

if (!in_array($normalized, $publicPaths) && !Auth::check()) {
    header('Location: ' . Router::url('/login'));
    exit;
}

try {
    Router::dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
} catch (\Throwable $e) {
    error_log('[PSI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($config['debug'])) {
        echo '<h1 style="font-family:sans-serif">500 — Server error</h1>'
            . '<pre style="font-family:monospace">' . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1 style="font-family:sans-serif">500 — Server error</h1><p>Something went wrong. Please try again.</p>';
    }
}
