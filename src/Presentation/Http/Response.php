<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use ArrayAccess;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class Response implements ArrayAccess
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($status, (string) $json, array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers));
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self($status, '', ['Location' => $url]);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }

    // ArrayAccess implementation for backward compatibility with router array format
    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['status', 'headers', 'body'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Readonly response object
    }

    public function offsetUnset(mixed $offset): void
    {
        // Readonly response object
    }
}
