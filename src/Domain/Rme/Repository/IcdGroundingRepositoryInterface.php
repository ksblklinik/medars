<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\DiagnosaPasien;

interface IcdGroundingRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function searchSatuSehatIcd10(string $keyword, int $limit = 20): array;

    /**
     * @return ?array<string, mixed>
     */
    public function validateCode(string $kdPenyakit): ?array;

    public function saveDiagnosa(DiagnosaPasien $diagnosa): void;

    /**
     * @return list<DiagnosaPasien>
     */
    public function findDiagnosaByNoRawat(NoRawat $noRawat): array;
}
