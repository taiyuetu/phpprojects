<?php
namespace App\Core;

/**
 * Base Controller.
 * Handles view rendering (inside the shared layout), redirects,
 * flash messages and simple input access — everything a child
 * controller needs so it can stay focused on business logic.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            die("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout) {
            require __DIR__ . '/../Views/' . $layout . '.php';
        } else {
            echo $content;
        }
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . Router::url($path));
        exit;
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function all(): array
    {
        return $_POST;
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /** CSRF token helpers — every state-changing form should include this. */
    protected function csrfField(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
        }
    }
}
