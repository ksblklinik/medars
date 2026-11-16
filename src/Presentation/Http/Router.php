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
        $body = (string) $handler();

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }
}
