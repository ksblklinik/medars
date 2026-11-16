<?php

declare(strict_types=1);

namespace App\Domain\Registration\ValueObject;

final readonly class NoRkmMedis implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Nomor Rekam Medis (no_rkm_medis) tidak boleh kosong.');
        }

        if (strlen($trimmed) > 15) {
            throw new \InvalidArgumentException('Nomor Rekam Medis maksimal 15 karakter.');
        }

        $this->value = $trimmed;
    }

    public static function fromSequence(int $sequence, int $padLength = 6): self
    {
        if ($sequence < 1) {
            throw new \InvalidArgumentException('Urutan Nomor Rekam Medis minimal 1.');
        }

        return new self(str_pad((string) $sequence, $padLength, '0', STR_PAD_LEFT));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
