<?php

declare(strict_types=1);

namespace App\Application\Pharmacy\UseCase;

use App\Domain\Pharmacy\Repository\PharmacyReturRepositoryInterface;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class ReturnResepObatUseCase
{
    public function __construct(
        private PharmacyReturRepositoryInterface $returRepo,
        private AuditLogRepositoryInterface $auditRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(
        string $noResep,
        string $kodeBrng,
        float $qtyRetur,
        string $kdBangsal,
        int $userId,
        string $username,
        string $alasan,
        string $ipAddress = ''
    ): array {
        if (trim($alasan) === '') {
            throw new \InvalidArgumentException('Alasan retur obat wajib diisi.');
        }

        return $this->tx->transaction(function () use (
            $noResep, $kodeBrng, $qtyRetur, $kdBangsal, $userId, $username, $alasan, $ipAddress
        ): array {
            $result = $this->returRepo->returDispensedObat($noResep, $kodeBrng, $qtyRetur, $kdBangsal, $username, $alasan);
            $this->auditRepo->log(
                $userId,
                $username,
                'PHARMACY_RETUR_OBAT',
                "Retur obat {$kodeBrng} qty {$qtyRetur} dari resep {$noResep}: {$alasan}",
                $ipAddress
            );
            return $result;
        });
    }
}
