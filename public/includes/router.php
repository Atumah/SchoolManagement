<?php

declare(strict_types=1);

/**
 * Simple Router for School Management System
 * Handles routing and prevents conflicts
 */

namespace App\Router;

class Router
{
    private static array $routes = [];
    private static ?string $basePath = null;

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function get(string $path, string $handler): void
    {
        self::addRoute('GET', $path, $handler);
    }

    public static function post(string $path, string $handler): void
    {
        self::addRoute('POST', $path, $handler);
    }

    private static function addRoute(string $method, string $path, string $handler): void
    {
        self::$routes[] = [
            'method' => $method,
            'path' => self::normalizePath($path),
            'handler' => $handler
        ];
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    public static function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestPath = self::normalizePath($requestUri);

        foreach (self::$routes as $route) {
            if ($route['method'] === $requestMethod && $route['path'] === $requestPath) {
                if (file_exists($route['handler'])) {
                    require $route['handler'];
                    return;
                }
            }
        }

        // 404 if no route matches
        http_response_code(404);
        require __DIR__ . '/../errors/404.php';
    }

    public static function url(string $path = ''): string
    {
        $base = self::$basePath ?? '';
        return $base . self::normalizePath($path);
    }
}
