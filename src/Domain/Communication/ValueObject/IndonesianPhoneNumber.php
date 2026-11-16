<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObject;

final readonly class IndonesianPhoneNumber
{
    private string $value;

    public function __construct(string $raw)
    {
        $cleaned = preg_replace('/[^0-9]/', '', trim($raw)) ?? '';

        if (str_starts_with($cleaned, '08')) {
            $cleaned = '628' . substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '628' . substr($cleaned, 1);
        }

        if (preg_match('/^628[1-9][0-9]{7,12}$/', $cleaned) !== 1) {
            throw new \InvalidArgumentException("Format nomor WhatsApp tidak valid: '{$raw}'. Wajib format seluler Indonesia (628...).");
        }

        $this->value = $cleaned;
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
