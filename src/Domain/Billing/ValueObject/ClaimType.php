<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObject;

final readonly class ClaimType
{
    public const VALID_PILLARS = ['Tunai', 'Kapitasi', 'Non-Kapitasi', 'Piutang'];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        if (!in_array($trimmed, self::VALID_PILLARS, true)) {
            throw new \InvalidArgumentException("Claim type 4-Pilar tidak valid: '{$raw}'.");
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isTunai(): bool
    {
        return $this->value === 'Tunai';
    }

    public function isKapitasi(): bool
    {
        return $this->value === 'Kapitasi';
    }

    public function isNonKapitasi(): bool
    {
        return $this->value === 'Non-Kapitasi';
    }

    public function isPiutang(): bool
    {
        return $this->value === 'Piutang';
    }
}
