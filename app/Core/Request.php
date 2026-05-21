<?php

namespace App\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($uri === null || $uri === false) {
            return '/';
        }

        $uri = '/' . trim($uri, '/');

        if (str_starts_with($uri, '/api.php')) {
            $uri = substr($uri, strlen('/api.php'));
            $uri = $uri === '' ? '/' : $uri;
        }

        return $uri === '' ? '/' : $uri;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    public function body(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            $data = json_decode($rawBody, true);

            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();

        return $body[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $headers = $this->headers();
        $normalizedName = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower($key) === $normalizedName) {
                return $value;
            }
        }

        return $default;
    }

    public function headers(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return trim(substr($authorization, 7));
    }

    public function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}