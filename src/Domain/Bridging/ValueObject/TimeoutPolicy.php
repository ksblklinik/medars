<?php

declare(strict_types=1);

namespace App\Domain\Bridging\ValueObject;

final readonly class TimeoutPolicy
{
    public const MAX_CONNECT_SECONDS = 2;
    public const MAX_EXECUTION_SECONDS = 4;

    public function __construct(
        public int $connectTimeoutSeconds = 2,
        public int $executionTimeoutSeconds = 4,
    ) {
        if ($this->connectTimeoutSeconds < 1 || $this->connectTimeoutSeconds > self::MAX_CONNECT_SECONDS) {
            throw new \InvalidArgumentException(
                'Connect timeout bridging wajib antara 1 s.d. ' . self::MAX_CONNECT_SECONDS . ' detik.'
            );
        }

        if ($this->executionTimeoutSeconds < 1 || $this->executionTimeoutSeconds > self::MAX_EXECUTION_SECONDS) {
            throw new \InvalidArgumentException(
                'Execution timeout bridging wajib antara 1 s.d. ' . self::MAX_EXECUTION_SECONDS . ' detik.'
            );
        }
    }
}
