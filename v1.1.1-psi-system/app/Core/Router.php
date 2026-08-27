<?php
namespace App\Core;

/**
 * Minimal but complete router: register GET/POST routes with
 * {param} placeholders, dispatch to Controller@method.
 * Small enough to read top-to-bottom in a minute; that's the point —
 * new developers shouldn't need a manual to add a route.
 */
class Router
{
    private static array $routes = ['GET' => [], 'POST' => []];
    public static string $basePath = '';

    public static function get(string $path, string $handler): void
    {
        self::$routes['GET'][$path] = $handler;
    }

    public static function post(string $path, string $handler): void
    {
        self::$routes['POST'][$path] = $handler;
    }

    public static function url(string $path): string
    {
        return rtrim(self::$basePath, '/') . '/' . ltrim($path, '/');
    }

    public static function dispatch(string $uri, string $method): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        // Strip base path (in case the app lives in a sub-folder)
        if (self::$basePath !== '' && self::$basePath !== '/') {
            $uri = preg_replace('#^' . preg_quote(self::$basePath, '#') . '#', '', $uri);
        }
        $uri = '/' . trim($uri, '/');

        $routes = self::$routes[$method] ?? [];

        foreach ($routes as $pattern => $handler) {
            $regex = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', rtrim($pattern, '/') ?: '/');
            $regex = '#^' . ($regex === '' ? '/' : $regex) . '$#';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                self::call($handler, $matches);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../Views/errors/404.php';
    }

    private static function call(string $handler, array $params): void
    {
        [$controllerName, $method] = explode('@', $handler);
        $class = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($class)) {
            die("Controller {$class} not found.");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            die("Method {$method} not found on {$class}.");
        }

        call_user_func_array([$controller, $method], $params);
    }
}
