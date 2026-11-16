<?php

declare(strict_types=1);

namespace App\Application\Communication\UseCase;

use App\Domain\Communication\Repository\HomeCareRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class AssignHomeCarePreceptorUseCase
{
    public function __construct(
        private HomeCareRepositoryInterface $homeCareRepo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(
        NoRawat $noRawat,
        string $preceptorNip,
        string $institusi,
        string $mahasiswaNim,
        string $supervisorId,
    ): array {
        if (!$this->homeCareRepo->hasActivePreceptorSk($preceptorNip, $institusi)) {
            throw new \DomainException(
                "Nakes preceptor '{$preceptorNip}' tidak memiliki SK Penugasan resmi yang aktif untuk institusi '{$institusi}'."
            );
        }

        return $this->homeCareRepo->assignPreceptor(
            $noRawat,
            $preceptorNip,
            $institusi,
            $mahasiswaNim,
            $supervisorId,
        );
    }

    public function assertDpjpNotAssignedPreceptorTask(string $dpjpDoctorId, string $assignedPreceptorNip): void
    {
        if (trim($dpjpDoctorId) !== '' && trim($dpjpDoctorId) === trim($assignedPreceptorNip)) {
            throw new \DomainException(
                'Pemisahan Trias Preceptorship: Dokter DPJP murni bertindak sebagai klinisi medis penanggung jawab asuhan dan dilarang merangkap penugasan grading/evaluasi stase magang mahasiswa.'
            );
        }
    }
}
