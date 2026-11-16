<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class VoidBillingTransactionUseCase
{
    public function __construct(
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private AuditLogRepositoryInterface $auditRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @return array{stamp: string, reversal_jurnals: list<string>}
     */
    public function execute(
        NoRawat $noRawat,
        int $userId,
        string $username,
        string $reason,
        string $ipAddress = '',
        ?\DateTimeImmutable $voidAt = null
    ): array {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Alasan pembatalan (void) wajib diisi.');
        }

        if ($this->regRepo->hasClinicalRecords($noRawat)) {
            throw new \DomainException(
                "Full Void ditolak: kunjungan {$noRawat->value()} sudah memiliki layanan klinis (SOAP/Tindakan/Obat/Lab/Rad)."
            );
        }

        $now = $voidAt ?? new \DateTimeImmutable();

        return $this->tx->transaction(function () use ($noRawat, $userId, $username, $reason, $ipAddress, $now): array {
            $existingJurnals = $this->ledgerRepo->findJurnalsByNoBukti($noRawat->value());
            $reversalNos = [];

            foreach ($existingJurnals as $jurnal) {
                if (str_starts_with($jurnal->keterangan, '[REVERSAL')) {
                    continue;
                }
                $revNo = $this->ledgerRepo->getNextJurnalNumber($now);
                $reversal = $jurnal->createReversal($revNo, $now->format('Y-m-d'), $now->format('H:i:s'), $reason);
                $this->ledgerRepo->saveJurnal($reversal);
                $reversalNos[] = $revNo;
            }

            $this->ledgerRepo->updateStatusByNoRawat($noRawat, LedgerStatus::fromString('Cancelled'));

            $reg = $this->regRepo->findByNoRawat($noRawat);
            if ($reg !== null) {
                $this->regRepo->update($reg->withPaymentStatus('Belum Bayar'));
            }

            $this->auditRepo->log(
                $userId,
                $username,
                'BILLING_VOID',
                "Void billing {$noRawat->value()} alasan: {$reason}",
                $ipAddress
            );

            return [
                'stamp' => 'FAKTUR / KUITANSI TIDAK BERLAKU',
                'reversal_jurnals' => $reversalNos,
            ];
        });
    }
}
