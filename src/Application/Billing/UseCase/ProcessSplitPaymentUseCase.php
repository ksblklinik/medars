<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class ProcessSplitPaymentUseCase
{
    public function __construct(
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @return array{tunai_paid: float, bpjs_covered: float, piutang_covered: float, no_jurnal: string}
     */
    public function execute(NoRawat $noRawat, ?\DateTimeImmutable $paidAt = null): array
    {
        $now = $paidAt ?? new \DateTimeImmutable();

        return $this->tx->transaction(function () use ($noRawat, $now): array {
            $items = $this->ledgerRepo->findByNoRawat($noRawat);
            $pendingItems = array_filter($items, static fn($i): bool => $i->status->value() === 'Pending');

            if ($pendingItems === []) {
                throw new \DomainException("Tidak ada tagihan berstatus Pending untuk kunjungan {$noRawat->value()}.");
            }

            $tunaiTotal = 0.0;
            $bpjsTotal = 0.0;
            $piutangTotal = 0.0;
            $revenueServices = 0.0;
            $revenueDrugs = 0.0;

            foreach ($pendingItems as $item) {
                if ($item->claimType->isTunai()) {
                    $tunaiTotal += $item->totalAmount;
                } elseif ($item->claimType->isKapitasi() || $item->claimType->isNonKapitasi()) {
                    $bpjsTotal += $item->totalAmount;
                } else {
                    $piutangTotal += $item->totalAmount;
                }

                if ($item->category === 'Obat') {
                    $revenueDrugs += $item->totalAmount;
                } else {
                    $revenueServices += $item->totalAmount;
                }
            }

            $details = [];
            // Kas Fisik 1101.01 HANYA didebet untuk porsi Tunai [T]
            if ($tunaiTotal > 0) {
                $details[] = new JurnalDetail(JurnalEntry::COA_KAS_FISIK, $tunaiTotal, 0.0);
            }
            if ($bpjsTotal > 0) {
                $details[] = new JurnalDetail(JurnalEntry::COA_PIUTANG_BPJS, $bpjsTotal, 0.0);
            }
            if ($piutangTotal > 0) {
                $details[] = new JurnalDetail(JurnalEntry::COA_PIUTANG_REKANAN, $piutangTotal, 0.0);
            }
            if ($revenueServices > 0) {
                $details[] = new JurnalDetail(JurnalEntry::COA_PENDAPATAN_LAYANAN, 0.0, $revenueServices);
            }
            if ($revenueDrugs > 0) {
                $details[] = new JurnalDetail(JurnalEntry::COA_PENDAPATAN_OBAT, 0.0, $revenueDrugs);
            }

            $noJurnal = '';
            if ($details !== []) {
                $noJurnal = $this->ledgerRepo->getNextJurnalNumber($now);
                $jurnal = new JurnalEntry(
                    noJurnal: $noJurnal,
                    tglJurnal: $now->format('Y-m-d'),
                    jamJurnal: $now->format('H:i:s'),
                    noBukti: $noRawat->value(),
                    keterangan: "Pembayaran Split Billing Kasir ({$noRawat->value()})",
                    details: $details
                );
                $this->ledgerRepo->saveJurnal($jurnal);
            }

            $this->ledgerRepo->updateStatusByNoRawat($noRawat, LedgerStatus::fromString('Paid'));

            $reg = $this->regRepo->findByNoRawat($noRawat);
            if ($reg !== null) {
                $updatedReg = $reg->withPaymentStatus('Sudah Bayar');
                $this->regRepo->update($updatedReg);
            }

            return [
                'tunai_paid' => $tunaiTotal,
                'bpjs_covered' => $bpjsTotal,
                'piutang_covered' => $piutangTotal,
                'no_jurnal' => $noJurnal,
            ];
        });
    }
}
