<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;

interface BillingLedgerRepositoryInterface
{
    public function addItem(BillingLedgerItem $item): void;

    /**
     * @return list<BillingLedgerItem>
     */
    public function findByNoRawat(NoRawat $noRawat): array;

    public function updateStatusByNoRawat(NoRawat $noRawat, LedgerStatus $status): void;

    public function markPendingAsPaid(NoRawat $noRawat): void;

    public function getNextJurnalNumber(\DateTimeInterface $date): string;

    public function saveJurnal(JurnalEntry $entry): void;

    /**
     * @return list<JurnalEntry>
     */
    public function findJurnalsByNoBukti(string $noBukti): array;
}
