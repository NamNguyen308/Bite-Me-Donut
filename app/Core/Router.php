<?php

namespace App\Core;

use Throwable;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $this->normalizePath($request->path());

        try {
            foreach ($this->routes as $route) {
                if ($route['method'] !== $method) {
                    continue;
                }

                $params = $this->match($route['path'], $path);

                if ($params === null) {
                    continue;
                }

                $this->callHandler($route['handler'], $request, $params);
                return;
            }

            Response::error(
                'NOT_FOUND',
                'API endpoint not found',
                404
            );
        } catch (Throwable $e) {
            Response::error(
                'INTERNAL_SERVER_ERROR',
                'Internal server error',
                500,
                [
                    'detail' => $e->getMessage()
                ]
            );
        }
    }

    private function callHandler(callable|array $handler, Request $request, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;

            $controller = new $class();

            $controller->$method($request, $params);
            return;
        }

        $handler($request, $params);
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($requestParts)) {
            return null;
        }

        $params = [];

        foreach ($routeParts as $index => $routePart) {
            $requestPart = $requestParts[$index];

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $routePart, $matches)) {
                $params[$matches[1]] = $requestPart;
                continue;
            }

            if ($routePart !== $requestPart) {
                return null;
            }
        }

        return $params;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}