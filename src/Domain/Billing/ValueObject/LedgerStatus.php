<?php

declare(strict_types=1);

namespace App\Domain\Billing\ValueObject;

final readonly class LedgerStatus
{
    public const VALID_STATUSES = [
        'Pending',
        'Paid',
        'Approved',
        'Bundled',
        'Cancelled',
        'Expired',
    ];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        if (!in_array($trimmed, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Status billing ledger tidak valid: '{$raw}'.");
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
