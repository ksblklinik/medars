<?php

declare(strict_types=1);

namespace App\Application\Pharmacy\UseCase;

use App\Domain\Pharmacy\Repository\ResepRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteResepUseCase
{
    public function __construct(
        private ResepRepositoryInterface $resepRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(NoResep $noResep): void
    {
        $resep = $this->resepRepo->findByNoResep($noResep);
        if ($resep === null) {
            throw new \DomainException("Resep tidak ditemukan: {$noResep->value()}.");
        }

        if ($resep->isDispensed()) {
            throw new \DomainException("Resep {$noResep->value()} sudah divalidasi/diserahkan dan tidak dapat dihapus.");
        }

        $this->tx->transaction(function () use ($noResep): void {
            $this->resepRepo->deleteHeaderAndItems($noResep);
        });
    }
}
