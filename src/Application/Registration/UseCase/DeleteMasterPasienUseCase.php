<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteMasterPasienUseCase
{
    public function __construct(
        private PasienRepositoryInterface $pasienRepo,
        private TransactionManagerInterface $tx,
    ) {}

    public function execute(NoRkmMedis $noRkmMedis): void
    {
        if ($this->pasienRepo->findByNoRkmMedis($noRkmMedis) === null) {
            throw new \DomainException("Pasien dengan No. RM '{$noRkmMedis->value()}' tidak ditemukan.");
        }

        if ($this->pasienRepo->hasRegistrations($noRkmMedis)) {
            throw new \DomainException(
                "Pasien No. RM '{$noRkmMedis->value()}' tidak dapat dihapus karena sudah memiliki riwayat kunjungan."
            );
        }

        $this->tx->transaction(function () use ($noRkmMedis): void {
            $this->pasienRepo->delete($noRkmMedis);
        });
    }
}
