<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($uri, '/');

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path === '/' ? '/' : rtrim($path, '/'),
            query: $_GET,
            post: $_POST,
            server: $_SERVER
        );
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;
        if (is_array($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function post(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;
        if (is_array($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}