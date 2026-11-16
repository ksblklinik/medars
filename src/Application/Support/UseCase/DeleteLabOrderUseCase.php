<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteLabOrderUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private TransactionManagerInterface $tx,
        private ?BillingLedgerRepositoryInterface $ledgerRepo = null,
    ) {}

    public function execute(string $noOrder): void
    {
        $order = $this->labRepo->findLabOrder($noOrder);
        if ($order === null) {
            throw new \DomainException("Order lab '{$noOrder}' tidak ditemukan.");
        }

        if ($order->tglHasil !== null && $order->tglHasil !== '' && $order->tglHasil !== '0000-00-00') {
            throw new \DomainException("Order lab '{$noOrder}' sudah memiliki hasil dan tidak dapat dihapus.");
        }

        if ($this->ledgerRepo !== null) {
            foreach ($this->ledgerRepo->findByNoRawat($order->noRawat) as $item) {
                if ($item->providerId === $noOrder && in_array($item->status->value(), ['Paid', 'Approved', 'Bundled'], true)) {
                    throw new \DomainException("Order lab '{$noOrder}' sudah dilunasi/disetujui di Kasir dan tidak dapat dihapus.");
                }
            }
        }

        $this->tx->transaction(function () use ($noOrder): void {
            $this->labRepo->deleteLabOrder($noOrder);
            $this->ledgerRepo?->cancelPendingByProviderId($noOrder);
        });
    }
}
