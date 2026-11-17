<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Application\Registration\Exception\ClinicalGuardBlockedException;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class UpdateRegistrationStatusUseCase
{
    private const ALLOWED_STATUSES = ['Belum', 'Sudah', 'Batal', 'Berkas Diterima', 'Dirujuk', 'Meninggal', 'Dirawat'];

    public function __construct(
        private RegPeriksaRepositoryInterface $regRepo,
        private ClinicalGuardShieldUseCase $clinicalGuard,
        private TransactionManagerInterface $tx,
    ) {}

    public function execute(
        NoRawat $noRawat,
        string $stts,
        ?string $kdPoli = null,
        ?string $kdDokter = null,
    ): void {
        if (!in_array($stts, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException("Status periksa '{$stts}' tidak valid.");
        }

        $existing = $this->regRepo->findByNoRawat($noRawat);
        if ($existing === null) {
            throw new \DomainException("Kunjungan '{$noRawat->value()}' tidak ditemukan.");
        }

        $changingPoli = $kdPoli !== null && $kdPoli !== '' && $kdPoli !== $existing->kdPoli;
        $changingDokter = $kdDokter !== null && $kdDokter !== '' && $kdDokter !== $existing->kdDokter;

        if (($changingPoli || $changingDokter) && $this->regRepo->hasClinicalRecords($noRawat)) {
            throw new ClinicalGuardBlockedException(
                $noRawat->value(),
                'Poli/Dokter tidak dapat diubah karena pasien sudah memiliki catatan klinis.'
            );
        }

        $this->tx->transaction(function () use ($noRawat, $stts, $kdPoli, $kdDokter): void {
            $this->regRepo->updateStatusAndAssignment($noRawat, $stts, $kdPoli, $kdDokter);
        });
    }
}
