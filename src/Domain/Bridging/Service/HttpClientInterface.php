<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

use App\Domain\Bridging\ValueObject\TimeoutPolicy;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        TimeoutPolicy $timeout,
    ): array;
}
