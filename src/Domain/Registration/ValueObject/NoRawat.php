<?php

declare(strict_types=1);

namespace App\Domain\Registration\ValueObject;

final readonly class NoRawat implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}\/\d{6}$/', $trimmed)) {
            throw new \InvalidArgumentException("Format Nomor Rawat (no_rawat) wajib YYYY/MM/DD/000001. Diterima: '{$value}'");
        }

        $this->value = $trimmed;
    }

    public static function generate(\DateTimeInterface $date, int $sequence): self
    {
        if ($sequence < 1 || $sequence > 999999) {
            throw new \InvalidArgumentException('Urutan Nomor Rawat harus antara 1 sampai 999999.');
        }

        $datePrefix = $date->format('Y/m/d');
        $seqStr = sprintf('%06d', $sequence);

        return new self("{$datePrefix}/{$seqStr}");
    }

    public function sequenceNumber(): int
    {
        return (int) substr($this->value, -6);
    }

    public function datePrefix(): string
    {
        return substr($this->value, 0, 10);
    }

    public function next(): self
    {
        $nextSeq = $this->sequenceNumber() + 1;
        $seqStr = sprintf('%06d', $nextSeq);

        return new self($this->datePrefix() . '/' . $seqStr);
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
