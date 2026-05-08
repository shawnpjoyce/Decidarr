<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function __construct(private readonly Database $database)
    {
    }

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(Request $request): bool
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;
        if ($handler === null) {
            return false;
        }

        [$controllerClass, $method] = $handler;
        $controller = new $controllerClass($this->database, $request);
        $controller->{$method}();

        return true;
    }
}