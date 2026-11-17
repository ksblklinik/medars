<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteRadiologyOrderUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $repo,
        private TransactionManagerInterface $tx,
        private ?BillingLedgerRepositoryInterface $ledgerRepo = null,
    ) {}

    public function execute(string $noOrder): void
    {
        $order = $this->repo->findRadiologiOrder($noOrder);
        if ($order === null) {
            throw new \DomainException("Order radiologi '{$noOrder}' tidak ditemukan.");
        }

        if ($order->tglHasil !== null && $order->tglHasil !== '' && $order->tglHasil !== '0000-00-00') {
            throw new \DomainException("Order radiologi '{$noOrder}' sudah memiliki hasil dan tidak dapat dihapus.");
        }

        if ($this->ledgerRepo !== null) {
            foreach ($this->ledgerRepo->findByNoRawat($order->noRawat) as $item) {
                if ($item->providerId === $noOrder && in_array($item->status->value(), ['Paid', 'Approved', 'Bundled'], true)) {
                    throw new \DomainException("Order radiologi '{$noOrder}' sudah dilunasi/disetujui di Kasir dan tidak dapat dihapus.");
                }
            }
        }

        $this->tx->transaction(function () use ($noOrder): void {
            $this->repo->deleteRadiologiOrder($noOrder);
            $this->ledgerRepo?->cancelPendingByProviderId($noOrder);
        });
    }
}
