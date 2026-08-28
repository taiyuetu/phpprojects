<?php
/**
 * Router script for PHP's built-in development server.
 * 
 * Usage: php -S 127.0.0.1:8000 router.php
 * 
 * This script routes all requests to index.php, except for
 * existing static files (CSS, JS, images, etc.) which are served directly.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let the built-in server handle the static file
}

// Route everything else to the front controller
require __DIR__ . '/index.php';