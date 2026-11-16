<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\ValueObject;

final readonly class NoResep
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 14) {
            throw new \InvalidArgumentException("Nomor resep tidak valid: '{$value}'.");
        }
        $this->value = $trimmed;
    }

    public function value(): string
    {
        return $this->value;
    }
}
