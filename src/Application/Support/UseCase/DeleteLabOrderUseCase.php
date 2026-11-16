<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DeleteLabOrderUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private TransactionManagerInterface $tx,
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

        $this->tx->transaction(function () use ($noOrder): void {
            $this->labRepo->deleteLabOrder($noOrder);
        });
    }
}
