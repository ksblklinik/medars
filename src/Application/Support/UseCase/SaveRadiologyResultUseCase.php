<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Support\Entity\HasilRadiologi;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveRadiologyResultUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private TransactionManagerInterface $tx,
    ) {}

    public function execute(
        string $noOrder,
        string $hasilExpertise,
        ?\DateTimeImmutable $resultAt = null,
    ): HasilRadiologi {
        $order = $this->labRepo->findRadiologiOrder($noOrder);
        if ($order === null) {
            throw new \DomainException("Order radiologi '{$noOrder}' tidak ditemukan.");
        }

        $now = $resultAt ?? new \DateTimeImmutable();
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');

        return $this->tx->transaction(function () use ($order, $hasilExpertise, $tgl, $jam): HasilRadiologi {
            $hasil = new HasilRadiologi(
                noRawat: $order->noRawat,
                tglPeriksa: $tgl,
                jam: $jam,
                hasil: $hasilExpertise,
            );
            $this->labRepo->saveHasilRadiologi($hasil);
            $this->labRepo->markRadiologiResulted($order->noOrder, $tgl, $jam);

            return $hasil;
        });
    }
}
