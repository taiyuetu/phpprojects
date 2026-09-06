<?php

/**
 * Application bootstrap.
 * Required once by public/index.php (the single front controller).
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
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
    // Cookie 加固：HttpOnly 防脚本偷会话；SameSite=Lax 让跨站 POST（含删除/退登）
    // 不携带会话 cookie，配合各控制器里的 CSRF 校验；HTTPS（或反代转发）下再加 Secure。
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- 记住密码：自动登录检查 ----
// 如果 session 中没有用户信息，但存在有效的 remember cookie，则自动登录。
// 每次成功使用后都把 token 轮换（旧 token 作废、签发新 token）：cookie 落在
// 公共电脑上被抄走也只是一次性凭据，无法反复复用。
if (empty($_SESSION['user_id']) && !empty($_COOKIE[RememberToken::COOKIE_NAME])) {
    $tokenModel = new RememberToken();
    $token = (string) $_COOKIE[RememberToken::COOKIE_NAME];
    $userId = $tokenModel->validateToken($token);

    if ($userId) {
        // Token 有效，自动登录
        $user = User::identity($userId);

        if ($user) {
            $tokenModel->deleteToken($token);          // 旧 token 一次性
            $fresh = $tokenModel->createToken($user['id']);
            $tokenModel->setCookie($fresh);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
        } else {
            // 用户不存在，清除无效 cookie
            $tokenModel->deleteToken($token);
            $tokenModel->deleteCookie();
        }
    } else {
        // Token 无效或已过期，清除 cookie
        $tokenModel->deleteCookie();
    }
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
