<?php

declare(strict_types=1);

namespace App\Presentation\Http;

final class Router
{
    /**
     * @var array<string, array<string, callable(): string>>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function dispatch(string $method, string $uri): array
    {
        $upperMethod = strtoupper($method);
        $path = $this->normalizePath((string) parse_url($uri, PHP_URL_PATH));

        if (!isset($this->routes[$upperMethod][$path])) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h1>404 - Halaman Tidak Ditemukan</h1>',
            ];
        }

        $handler = $this->routes[$upperMethod][$path];
        $result = $handler();

        if ($result instanceof Response) {
            return $result->toArray();
        }

        if (is_array($result) && isset($result['status'], $result['body'])) {
            return [
                'status' => (int) $result['status'],
                'headers' => array_merge(['Content-Type' => 'text/html; charset=utf-8'], $result['headers'] ?? []),
                'body' => (string) $result['body'],
            ];
        }

        if (is_array($result)) {
            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => json_encode($result, JSON_THROW_ON_ERROR),
            ];
        }

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => (string) $result,
        ];
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }
}
