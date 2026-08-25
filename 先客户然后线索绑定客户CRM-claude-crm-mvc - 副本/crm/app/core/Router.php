<?php

/**
 * Router
 *
 * Small, explicit route table (no magic URL-to-controller guessing).
 * Register routes in app/routes.php using ->get() / ->post(), then
 * call ->dispatch() once from the front controller.
 *
 * Supports {param} placeholders, e.g. '/customers/{id}/edit'.
 */
class Router
{
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
    ];

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, string $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, string $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    /** Allow HTML forms to fake PUT/DELETE via a hidden _method field. */
    private function resolveMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }
        return $method;
    }

    public function dispatch(string $uri): void
    {
        $method = $this->resolveMethod();
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $pattern => $handler) {
            $params = $this->match($pattern, $uri);
            if ($params !== null) {
                $this->callHandler($handler, $params);
                return;
            }
        }

        $this->notFound();
    }

    /** Return matched params (possibly empty array) or null if no match. */
    private function match(string $pattern, string $uri): ?array
    {
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }

        $paramNames = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches);
            return array_combine($paramNames, $matches) ?: [];
        }

        return null;
    }

    private function callHandler(string $handler, array $params): void
    {
        [$controllerName, $action] = explode('@', $handler);
        $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';

        if (!file_exists($controllerFile)) {
            $this->serverError("Controller not found: {$controllerName}");
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerName)) {
            $this->serverError("Controller class not found: {$controllerName}");
            return;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            $this->notFound();
            return;
        }

        call_user_func_array([$controller, $action], array_values($params));
    }

    private function notFound(): void
    {
        http_response_code(404);
        $viewFile = APP_PATH . '/views/errors/404.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '404 Not Found';
        }
    }

    private function serverError(string $message): void
    {
        http_response_code(500);
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<h1>500 Server Error</h1><p>' . htmlspecialchars($message) . '</p>';
        } else {
            echo '500 Server Error';
        }
    }
}
