<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class BillingLedgerItem
{
    public function __construct(
        public ?int $id,
        public NoRawat $noRawat,
        public string $itemCode,
        public string $itemName,
        public string $category,
        public float $qty,
        public float $unitPrice,
        public float $totalAmount,
        public ClaimType $claimType,
        public LedgerStatus $status,
        public string $providerId = '',
        public string $createdAt = '',
    ) {
        if ($this->qty <= 0 || $this->totalAmount < 0) {
            throw new \InvalidArgumentException('Kuantitas harus > 0 dan total tagihan tidak boleh negatif.');
        }
    }

    public function isTunai(): bool
    {
        return $this->claimType->isTunai();
    }
}
