<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\ValueObject;

final readonly class JenisKlaim
{
    public const VALID_TYPES = ['Tunai', 'Kapitasi', 'Non-Kapitasi'];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        if (!in_array($trimmed, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Jenis klaim tidak valid: '{$raw}'.");
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
