<?php

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

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        }

        if (!$errors) {
            $userModel = $this->model('User');
            $user = $userModel->findByEmail($email);

            if ($user && $userModel->verifyPassword($password, $user['password'])) {
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

            $errors[] = '邮箱或密码不正确。';
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
