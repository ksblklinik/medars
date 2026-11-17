<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Support\Entity\HasilRadiologi;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveRadiologyResultUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private TransactionManagerInterface $tx,
        private ?BillingLedgerRepositoryInterface $ledgerRepo = null,
    ) {}

    public function isValidationUnlocked(string $noOrder): bool
    {
        $order = $this->labRepo->findRadiologiOrder($noOrder);
        if ($order === null) {
            return false;
        }
        if ($order->jenisKlaim->isKapitasi() || $this->ledgerRepo === null) {
            return true;
        }

        $ledgerItems = $this->ledgerRepo->findByNoRawat($order->noRawat);
        $matchingItems = [];
        foreach ($ledgerItems as $item) {
            if ($item->providerId === $noOrder) {
                $matchingItems[] = $item;
            }
        }
        if ($matchingItems === []) {
            foreach ($ledgerItems as $item) {
                if ($item->category === 'Radiologi' && $item->claimType->value() === $order->jenisKlaim->value()) {
                    $matchingItems[] = $item;
                }
            }
        }
        if ($matchingItems === []) {
            return false;
        }
        foreach ($matchingItems as $item) {
            if (!in_array($item->status->value(), ['Paid', 'Approved', 'Bundled'], true)) {
                return false;
            }
        }
        return true;
    }

    public function execute(
        string $noOrder,
        string $hasilExpertise,
        ?\DateTimeImmutable $resultAt = null,
    ): HasilRadiologi {
        $order = $this->labRepo->findRadiologiOrder($noOrder);
        if ($order === null) {
            throw new \DomainException("Order radiologi '{$noOrder}' tidak ditemukan.");
        }
        if (!$this->isValidationUnlocked($noOrder)) {
            throw new \DomainException(
                "Validasi hasil radiologi '{$noOrder}' terkunci: tagihan belum dilunasi atau disetujui oleh Kasir."
            );
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
