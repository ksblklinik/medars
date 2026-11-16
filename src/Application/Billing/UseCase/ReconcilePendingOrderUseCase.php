<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class ReconcilePendingOrderUseCase
{
    public function __construct(
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private AuditLogRepositoryInterface $auditRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(
        NoRawat $noRawat,
        int $userId,
        string $username,
        string $reason,
        string $ipAddress = ''
    ): void {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Alasan pembatalan order rekonsiliasi wajib diisi.');
        }

        $this->tx->transaction(function () use ($noRawat, $userId, $username, $reason, $ipAddress): void {
            $this->ledgerRepo->updateStatusByNoRawat($noRawat, LedgerStatus::fromString('Cancelled'));
            $this->auditRepo->log(
                $userId,
                $username,
                'ORDER_RECONCILE_CANCEL',
                "Rekonsiliasi pembatalan order pending {$noRawat->value()}: {$reason}",
                $ipAddress
            );
        });
    }
}
