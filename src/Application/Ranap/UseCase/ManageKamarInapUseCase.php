<?php

declare(strict_types=1);

namespace App\Application\Ranap\UseCase;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Ranap\Repository\RawatInapRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class ManageKamarInapUseCase
{
    public function __construct(
        private RawatInapRepositoryInterface $repo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function checkIn(NoRawat $noRawat, string $kdKamar, ?\DateTimeImmutable $at = null): void
    {
        $kamar = $this->repo->findKamar($kdKamar);
        if ($kamar === null) {
            throw new \DomainException("Kamar '{$kdKamar}' tidak ditemukan.");
        }
        if (strtoupper((string) ($kamar['status'] ?? '')) === 'ISI') {
            throw new \DomainException("Kamar '{$kdKamar}' sedang terisi.");
        }

        $now = $at ?? new \DateTimeImmutable();
        $trf = (float) ($kamar['trf_kamar'] ?? 0);

        $this->tx->transaction(function () use ($noRawat, $kdKamar, $now, $trf): void {
            $this->repo->insertKamarInap(
                $noRawat->value(),
                $kdKamar,
                $now->format('Y-m-d'),
                $now->format('H:i:s'),
                $trf
            );
            $this->repo->setKamarStatus($kdKamar, 'ISI');
            $this->repo->markRegRanap($noRawat->value());
        });
    }

    /**
     * @return array{lama: int, ttl_biaya: float}
     */
    public function checkOut(
        NoRawat $noRawat,
        string $kdKamar,
        string $sttsPulang = 'Membaik',
        string $claimType = 'Tunai',
        ?\DateTimeImmutable $at = null,
    ): array {
        $stay = $this->repo->findActiveStay($noRawat->value(), $kdKamar);
        if ($stay === null) {
            throw new \DomainException("Pasien '{$noRawat->value()}' tidak tercatat aktif di kamar '{$kdKamar}'.");
        }

        $now = $at ?? new \DateTimeImmutable();
        $tglMasuk = (string) $stay['tgl_masuk'];
        $jamMasuk = (string) $stay['jam_masuk'];
        $inDate = new \DateTimeImmutable($tglMasuk);
        $outDate = new \DateTimeImmutable($now->format('Y-m-d'));
        $days = (int) $inDate->diff($outDate)->days;
        $lama = max(1, $days);
        $trf = (float) ($stay['trf_kamar'] ?? 0);
        $ttlBiaya = $lama * $trf;
        $claim = ClaimType::fromString($claimType);

        $this->tx->transaction(function () use ($noRawat, $kdKamar, $tglMasuk, $jamMasuk, $now, $lama, $trf, $ttlBiaya, $sttsPulang, $claim): void {
            $this->repo->updateCheckoutStay(
                $noRawat->value(),
                $kdKamar,
                $tglMasuk,
                $jamMasuk,
                $now->format('Y-m-d'),
                $now->format('H:i:s'),
                $lama,
                $ttlBiaya,
                $sttsPulang
            );
            $this->repo->setKamarStatus($kdKamar, 'KOSONG');

            if ($ttlBiaya > 0) {
                $this->ledgerRepo->addItem(new BillingLedgerItem(
                    id: null,
                    noRawat: $noRawat,
                    category: 'Kamar',
                    itemCode: $kdKamar,
                    itemName: "Sewa Kamar {$kdKamar} ({$lama} Hari)",
                    qty: (float) $lama,
                    unitPrice: $trf,
                    totalAmount: $ttlBiaya,
                    claimType: $claim,
                    status: LedgerStatus::fromString('Pending'),
                    providerId: 'KMR-' . $noRawat->value() . '-' . $kdKamar
                ));
            }
        });

        return ['lama' => $lama, 'ttl_biaya' => $ttlBiaya];
    }
}
