<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Repository\DokterTindakanRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteTindakanDokterUseCase
{
    public function __construct(
        private DokterTindakanRepositoryInterface $tindakanRepo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {}

    public function execute(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
    ): void {
        $row = $this->tindakanRepo->findTindakanRow($noRawat, $kdJenisPrw, $kdDokter, $tglPerawatan, $jamRawat);
        if ($row === null) {
            throw new \DomainException('Data tindakan dokter tidak ditemukan.');
        }

        if ((string) ($row['stts_bayar'] ?? '') === 'Sudah') {
            throw new \DomainException('Tindakan sudah dilunasi di Kasir dan tidak dapat dihapus.');
        }

        $providerKey = SaveTindakanDokterUseCase::providerKey($noRawat->value(), $kdJenisPrw, $tglPerawatan, $jamRawat);

        foreach ($this->ledgerRepo->findByNoRawat($noRawat) as $item) {
            if ($item->providerId === $providerKey && in_array($item->status->value(), ['Paid', 'Approved', 'Bundled'], true)) {
                throw new \DomainException('Tindakan sudah dilunasi/disetujui di Kasir dan tidak dapat dihapus.');
            }
        }

        $this->tx->transaction(function () use ($noRawat, $kdJenisPrw, $kdDokter, $tglPerawatan, $jamRawat, $providerKey): void {
            $this->tindakanRepo->deleteTindakan($noRawat, $kdJenisPrw, $kdDokter, $tglPerawatan, $jamRawat);
            $this->ledgerRepo->cancelPendingByProviderId($providerKey);
        });
    }
}
