<?php

/**
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        $this->view('auth/login', ['csrf' => $this->csrfToken()], 'auth');
    }

    public function login(): void
    {
        $this->verifyCsrf();

        // Check login throttling
        $throttle = $_SESSION['login_throttle'] ?? ['attempts' => 0, 'locked_until' => 0];
        if (!empty($throttle['locked_until']) && $throttle['locked_until'] > time()) {
            $remaining = $throttle['locked_until'] - time();
            $this->view('auth/login', [
                'csrf'   => $this->csrfToken(),
                'errors' => ["尝试登录失败次数过多，已被临时锁定，请 {$remaining} 秒后再试。"],
                'old'    => ['email' => trim($_POST['email'] ?? '')],
            ], 'auth');
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if ($email === '' || $password === '') {
            $errors[] = '请输入邮箱和密码。';
        }

        if (!$errors) {
            $userModel = $this->model('User');
            $user = $userModel->findByEmail($email);

            if ($user && $userModel->verifyPassword($password, $user['password'])) {
                // Clear throttling upon successful login
                unset($_SESSION['login_throttle']);

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                    'role'  => $user['role'],
                ];
                $this->setFlash('success', '欢迎回来，' . $user['name'] . '！');
                $this->redirect('/');
            }

            // Track failed attempts
            $attempts = ($throttle['attempts'] ?? 0) + 1;
            if ($attempts >= 5) {
                $_SESSION['login_throttle'] = [
                    'attempts'     => 0,
                    'locked_until' => time() + 60, // lock for 60 seconds
                ];
                $errors[] = '连续登录失败 5 次，账号登录已被临时锁定 60 秒。';
            } else {
                $_SESSION['login_throttle'] = [
                    'attempts'     => $attempts,
                    'locked_until' => 0,
                ];
                $errors[] = '邮箱或密码不正确。（剩余重试次数：' . (5 - $attempts) . ' 次）';
            }
        }

        $this->view('auth/login', [
            'csrf'   => $this->csrfToken(),
            'errors' => $errors,
            'old'    => ['email' => $email],
        ], 'auth');
    }

    public function showRegister(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        $this->view('auth/register', ['csrf' => $this->csrfToken()], 'auth');
    }

    public function register(): void
    {
        $this->verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $errors = [];
        if ($name === '' || $email === '' || $password === '') {
            $errors[] = '所有字段均为必填。';
        }
        if ($password !== $confirm) {
            $errors[] = '两次输入的密码不一致。';
        }
        if (strlen($password) > 0 && strlen($password) < 6) {
            $errors[] = '密码至少需要6个字符。';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        $userModel = $this->model('User');

        if (!$errors && $userModel->findByEmail($email)) {
            $errors[] = '该邮箱已被注册。';
        }

        if ($errors) {
            $this->view('auth/register', [
                'csrf'   => $this->csrfToken(),
                'errors' => $errors,
                'old'    => ['name' => $name, 'email' => $email],
            ], 'auth');
            return;
        }

        $userId = $userModel->register($name, $email, $password);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'sales'];
        $this->setFlash('success', '注册成功，欢迎，' . $name . '！');
        $this->redirect('/');
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('/login'));
        exit;
    }
}
