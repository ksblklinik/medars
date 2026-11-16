<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObject;

final readonly class PosPaymentMethod
{
    public const VALID_METHODS = [
        'Tunai',
        'QRIS',
        'Debit EDC',
        'Transfer',
        'Piutang Rekanan',
    ];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        if (!in_array($trimmed, self::VALID_METHODS, true)) {
            throw new \InvalidArgumentException("Metode bayar POS Apotek Umum tidak valid: '{$raw}'.");
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
