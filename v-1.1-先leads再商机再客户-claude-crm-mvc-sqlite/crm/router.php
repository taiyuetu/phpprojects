<?php
/**
 * PHP Built-in Server Router
 * 
 * 用法: php -S 127.0.0.1:3500 -t public router.php
 * 
 * 这个文件让 PHP 内置服务器能正确处理静态文件（CSS、JS、图片等）
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 如果是真实存在的静态文件，直接返回
$filePath = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($filePath)) {
    // 设置正确的 Content-Type
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    return false; // 让 PHP 内置服务器处理这个文件
}

// 其他请求交给 index.php 处理
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/public/index.php';
