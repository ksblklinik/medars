<?php

declare(strict_types=1);

namespace App\Domain\Bridging\ValueObject;

final readonly class BridgingCredentials
{
    private const DUMMY_VALUES = ['', '-', '0', '12345', 'dummy', 'null'];

    public function __construct(
        public string $consId,
        public string $secretKey,
        public string $userKey,
        public string $baseUrl,
    ) {}

    public function isActive(): bool
    {
        foreach ([$this->consId, $this->secretKey, $this->userKey, $this->baseUrl] as $val) {
            $normalized = strtolower(trim($val));
            if (in_array($normalized, self::DUMMY_VALUES, true)) {
                return false;
            }
        }

        return str_starts_with(strtolower(trim($this->baseUrl)), 'http');
    }
}
