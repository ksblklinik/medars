<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Repository\DokterTindakanRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveTindakanDokterUseCase
{
    public function __construct(
        private DokterTindakanRepositoryInterface $tindakanRepo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $claimType = 'Tunai',
        ?\DateTimeImmutable $at = null,
    ): array {
        $tarif = $this->tindakanRepo->findTarifByKode($kdJenisPrw);
        if ($tarif === null) {
            throw new \DomainException("Tarif tindakan '{$kdJenisPrw}' tidak ditemukan.");
        }

        $claim = ClaimType::fromString($claimType);
        $now = $at ?? new \DateTimeImmutable();
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');
        $material = (float) ($tarif['material'] ?? 0);
        $tarifDr = (float) ($tarif['tarif_tindakandr'] ?? 0);
        $biaya = (float) ($tarif['total_byrdr'] ?? ($material + $tarifDr));
        $providerKey = self::providerKey($noRawat->value(), $kdJenisPrw, $tgl, $jam);

        $this->tx->transaction(function () use ($noRawat, $kdJenisPrw, $kdDokter, $tgl, $jam, $material, $tarifDr, $biaya, $claim, $tarif, $providerKey): void {
            $this->tindakanRepo->saveTindakan(
                $noRawat,
                $kdJenisPrw,
                $kdDokter,
                $tgl,
                $jam,
                $material,
                $tarifDr,
                $biaya,
                $claim->value()
            );

            $this->ledgerRepo->addItem(new BillingLedgerItem(
                id: null,
                noRawat: $noRawat,
                itemCode: $kdJenisPrw,
                itemName: (string) ($tarif['nm_perawatan'] ?? $kdJenisPrw),
                category: 'Tindakan',
                qty: 1.0,
                unitPrice: $biaya,
                totalAmount: $biaya,
                claimType: $claim,
                status: LedgerStatus::fromString('Pending'),
                providerId: $providerKey,
                createdAt: $tgl . ' ' . $jam,
            ));
        });

        return [
            'no_rawat' => $noRawat->value(),
            'kd_jenis_prw' => $kdJenisPrw,
            'nm_perawatan' => (string) ($tarif['nm_perawatan'] ?? $kdJenisPrw),
            'kd_dokter' => $kdDokter,
            'tgl_perawatan' => $tgl,
            'jam_rawat' => $jam,
            'biaya_rawat' => $biaya,
            'jenis_klaim' => $claim->value(),
        ];
    }

    public static function providerKey(string $noRawat, string $kdJenisPrw, string $tgl, string $jam): string
    {
        return "TND|{$noRawat}|{$kdJenisPrw}|{$tgl}|{$jam}";
    }
}
