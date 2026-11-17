<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SettlePiutangUseCase
{
    public function __construct(
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @return array{settled_amount: float, no_jurnal: string}
     */
    public function execute(NoRawat $noRawat, ?\DateTimeImmutable $settledAt = null): array
    {
        $now = $settledAt ?? new \DateTimeImmutable();
        $noBukti = $noRawat->value() . '-PIUTANG';

        return $this->tx->transaction(function () use ($noRawat, $now, $noBukti): array {
            if ($this->ledgerRepo->findJurnalsByNoBukti($noBukti) !== []) {
                throw new \DomainException("Piutang untuk kunjungan {$noRawat->value()} sudah dilunasi sebelumnya.");
            }

            $items = $this->ledgerRepo->findByNoRawat($noRawat);
            $piutangTotal = 0.0;
            foreach ($items as $item) {
                if ($item->claimType->value() === 'Piutang' && $item->status->value() === 'Paid') {
                    $piutangTotal += $item->totalAmount;
                }
            }

            if ($piutangTotal <= 0.0) {
                throw new \DomainException("Tidak ada saldo Piutang terverifikasi untuk dilunasi pada {$noRawat->value()}.");
            }

            $noJurnal = $this->ledgerRepo->getNextJurnalNumber($now);
            $this->ledgerRepo->saveJurnal(new JurnalEntry(
                noJurnal: $noJurnal,
                tglJurnal: $now->format('Y-m-d'),
                jamJurnal: $now->format('H:i:s'),
                noBukti: $noBukti,
                keterangan: "Pelunasan Piutang Kasir ({$noRawat->value()})",
                details: [
                    new JurnalDetail(JurnalEntry::COA_KAS_FISIK, $piutangTotal, 0.0),
                    new JurnalDetail(JurnalEntry::COA_PIUTANG_REKANAN, 0.0, $piutangTotal),
                ]
            ));

            return [
                'settled_amount' => $piutangTotal,
                'no_jurnal' => $noJurnal,
            ];
        });
    }
}
