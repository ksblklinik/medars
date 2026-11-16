<?php

declare(strict_types=1);

namespace App\Domain\Registration\ValueObject;

final readonly class NoReg implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 8 || !ctype_digit($trimmed)) {
            throw new \InvalidArgumentException('Nomor Antrean (no_reg) harus berupa digit angka.');
        }

        if ((int) $trimmed < 1) {
            throw new \InvalidArgumentException('Nomor Antrean (no_reg) minimal bernilai 1.');
        }

        $this->value = $trimmed;
    }

    public static function fromSequence(int $sequence, int $padLength = 3): self
    {
        if ($sequence < 1) {
            throw new \InvalidArgumentException('Urutan Nomor Antrean minimal 1.');
        }

        return new self(str_pad((string) $sequence, $padLength, '0', STR_PAD_LEFT));
    }

    public function sequenceNumber(): int
    {
        return (int) ltrim($this->value, '0');
    }

    public function next(): self
    {
        return self::fromSequence($this->sequenceNumber() + 1);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
