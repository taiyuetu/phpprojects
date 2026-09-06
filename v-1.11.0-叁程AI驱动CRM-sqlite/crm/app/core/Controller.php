<?php

/**
 * Base Controller
 *
 * Child controllers extend this to get view rendering, easy model
 * loading, redirects, flash messages and a simple auth guard.
 *
 * Copyright (c) 2026 wayne · 叁程 CRM (Triphase CRM) — 保留所有权利 / All rights reserved.
 */
abstract class Controller
{
    /** Instantiate a model by name, e.g. $this->model('Customer'). */
    public function model(string $name)
    {
        require_once APP_PATH . '/models/' . $name . '.php';
        return new $name();
    }

    /**
     * Render a view inside the main layout.
     *
     * @param string $view   e.g. 'customers/index'
     * @param array  $data   variables extracted into the view's scope
     * @param string $layout layout file in views/layouts (without .php)
     */
    public function view(string $view, array $data = [], string $layout = 'main'): void
    {
        $viewFile = APP_PATH . '/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View not found: {$view}");
        }

        extract($data);

        // Render the view into a buffer first so the layout can echo $content.
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = APP_PATH . '/views/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /** Render a view with no layout wrapper (e.g. partial/AJAX responses). */
    public function partial(string $view, array $data = []): void
    {
        $viewFile = APP_PATH . '/views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View not found: {$view}");
        }
        extract($data);
        require $viewFile;
    }

    public function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    public function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * 输出一个 UTF-8 CSV 下载（带 BOM：Excel 打开中文不乱码）。
     *
     * @param array<string,string> $columns 字段 => 表头（顺序即列序）
     * @param array<int,array<string,mixed>> $rows  行，键与 $columns 对应
     */
    protected function sendCsv(string $filename, array $columns, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $cell = static function ($v): string {
            $s = (string) $v;
            // 含逗号/引号/换行才加引号，双写引号转义
            return strpbrk($s, ",\"\r\n") !== false ? '"' . str_replace('"', '""', $s) . '"' : $s;
        };
        $line = static function (array $fields) use ($columns, $cell): string {
            $cells = [];
            foreach (array_keys($columns) as $key) {
                $cells[] = $cell($fields[$key] ?? '');
            }
            return implode(',', $cells) . "\r\n";
        };

        echo "\xEF\xBB\xBF";                                       // UTF-8 BOM
        echo $line(array_combine(array_keys($columns), array_values($columns)));  // 表头
        foreach ($rows as $row) {
            echo $line($row);
        }
        exit;
    }

    /** Set a one-time flash message shown on the next page load. */
    protected function setFlash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    /** Require the user to be logged in; redirects to /login otherwise. */
    protected function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->setFlash('error', '请先登录后再继续操作。');
            $this->redirect('/login');
        }
    }

    /** Require user to have specific role */
    protected function requireRole(string $role, string $fallbackUrl = '/'): void
    {
        $this->requireAuth();
        // Resolve through currentUser() so a role change in the users table is
        // honoured immediately instead of via the login-time session snapshot.
        $user = currentUser();
        if (($user['role'] ?? '') !== $role) {
            $this->setFlash('error', '您没有执行该操作的权限。');
            $this->redirect($fallbackUrl);
        }
    }

    /** Ensure current user is admin or the resource owner */
    protected function authorizeResource(?int $ownerId, string $fallbackUrl = '/'): bool
    {
        $this->requireAuth();
        if (!canManageResource($ownerId)) {
            $this->setFlash('error', '您没有权限操作此数据。');
            $this->redirect($fallbackUrl);
            return false;
        }
        return true;
    }

    /** Basic CSRF token helpers. */
    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die('表单提交无效（CSRF 验证失败），请返回后重试。');
        }
    }
}
