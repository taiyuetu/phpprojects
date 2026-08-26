<?php
/**
 * Application configuration.
 * In production, load these from environment variables instead of hardcoding.
 */

// Load local .env values when present. Real environment variables still win.
$envPath = BASE_PATH . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// ---- Database ----
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '10036');
define('DB_NAME', getenv('DB_NAME') ?: 'crm_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
define('APP_NAME', 'MiniCRM');
define('APP_VERSION', '1.1.1');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // development | production
define('APP_DEBUG', APP_ENV === 'development');

// Base URL path of the app (e.g. '' if served from domain root, or '/crm/public' etc).
// URL_ROOT is auto-detected in bootstrap.php but can be forced here.
define('URL_ROOT_OVERRIDE', ''); // leave blank to auto-detect

// Session name
define('SESSION_NAME', 'minicrm_session');

// ---- Error reporting ----
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
